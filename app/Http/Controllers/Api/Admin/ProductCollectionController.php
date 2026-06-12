<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductCollection;
use App\Models\ProductCollectionItem;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductCollectionController extends Controller
{
    /**
     * GET /admin/product-collections
     * Получить все коллекции для админки
     */
    public function index()
    {
        $collections = ProductCollection::ordered()
            ->withCount('items')
            ->get()
            ->map(function ($collection) {
                return [
                    'id' => $collection->id,
                    'name' => $collection->name,
                    'is_active' => $collection->is_active,
                    'position' => $collection->position,
                    'items_count' => $collection->items_count,
                    'created_at' => $collection->created_at,
                    'updated_at' => $collection->updated_at,
                ];
            });

        return response()->json(['data' => $collections]);
    }

    /**
     * GET /admin/product-collections/{id}
     * Получить коллекцию с товарами
     */
    public function show($id)
    {
        $collection = ProductCollection::with([
            'items.product' => function ($query) {
                $query->with(['images', 'brand']);
            }
        ])->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $collection->id,
                'name' => $collection->name,
                'is_active' => $collection->is_active,
                'position' => $collection->position,
                'products' => $collection->items->sortBy('position')->map(function ($item) {
                    $product = $item->product;
                    return [
                        'item_id' => $item->id,
                        'position' => $item->position,
                        'id' => $product->id,
                        'title' => $product->title,
                        'slug' => $product->slug,
                        'article' => $product->article,
                        'price' => $product->price,
                        'is_active' => $product->is_active,
                        'brand' => $product->brand ? [
                            'id' => $product->brand->id,
                            'name' => $product->brand->name,
                        ] : null,
                        'image' => $product->images->first()?->url ?? null,
                    ];
                })->values(),
            ]
        ]);
    }

    /**
     * POST /admin/product-collections
     * Создать новую коллекцию
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        $position = ProductCollection::max('position') + 1;

        $collection = ProductCollection::create([
            'name' => $request->input('name'),
            'is_active' => $request->boolean('is_active', true),
            'position' => $position,
        ]);

        return response()->json([
            'data' => $collection,
            'message' => 'Коллекция создана'
        ], 201);
    }

    /**
     * PATCH /admin/product-collections/{id}
     * Обновить коллекцию
     */
    public function update(Request $request, $id)
    {
        $collection = ProductCollection::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $collection->update($request->only(['name', 'is_active']));

        return response()->json([
            'data' => $collection,
            'message' => 'Коллекция обновлена'
        ]);
    }

    /**
     * DELETE /admin/product-collections/{id}
     * Удалить коллекцию
     */
    public function destroy($id)
    {
        $collection = ProductCollection::findOrFail($id);
        $collection->delete();

        // Пересчитываем позиции
        ProductCollection::ordered()
            ->get()
            ->each(function (ProductCollection $item, int $idx) {
                $item->update(['position' => $idx]);
            });

        return response()->json(['message' => 'Коллекция удалена']);
    }

    /**
     * POST /admin/product-collections/reorder
     * Изменить порядок коллекций
     */
    public function reorder(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:product_collections,id',
            'items.*.position' => 'required|integer|min:0',
        ]);

        foreach ($request->input('items') as $row) {
            ProductCollection::where('id', $row['id'])
                ->update(['position' => $row['position']]);
        }

        return response()->json(['message' => 'Порядок сохранён']);
    }

    /**
     * POST /admin/product-collections/{id}/products
     * Добавить товар в коллекцию
     */
    public function addProduct(Request $request, $id)
    {
        $collection = ProductCollection::findOrFail($id);

        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
        ]);

        // Проверяем, не добавлен ли уже товар
        $exists = ProductCollectionItem::where('collection_id', $id)
            ->where('product_id', $request->integer('product_id'))
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Товар уже добавлен в коллекцию'], 422);
        }

        $position = ProductCollectionItem::where('collection_id', $id)->max('position') + 1;

        $item = ProductCollectionItem::create([
            'collection_id' => $id,
            'product_id' => $request->integer('product_id'),
            'position' => $position,
        ]);

        $item->load('product.images', 'product.brand');

        return response()->json([
            'data' => [
                'item_id' => $item->id,
                'position' => $item->position,
                'id' => $item->product->id,
                'title' => $item->product->title,
                'slug' => $item->product->slug,
                'article' => $item->product->article,
                'price' => $item->product->price,
                'is_active' => $item->product->is_active,
                'brand' => $item->product->brand ? [
                    'id' => $item->product->brand->id,
                    'name' => $item->product->brand->name,
                ] : null,
                'image' => $item->product->images->first()?->url ?? null,
            ],
            'message' => 'Товар добавлен в коллекцию'
        ]);
    }

    /**
     * DELETE /admin/product-collections/{id}/products/{itemId}
     * Удалить товар из коллекции
     */
    public function removeProduct($id, $itemId)
    {
        $item = ProductCollectionItem::where('collection_id', $id)
            ->where('id', $itemId)
            ->firstOrFail();

        $item->delete();

        // Пересчитываем позиции
        ProductCollectionItem::where('collection_id', $id)
            ->orderBy('position')
            ->get()
            ->each(function (ProductCollectionItem $item, int $idx) {
                $item->update(['position' => $idx]);
            });

        return response()->json(['message' => 'Товар удалён из коллекции']);
    }

    /**
     * POST /admin/product-collections/{id}/products/reorder
     * Изменить порядок товаров в коллекции
     */
    public function reorderProducts(Request $request, $id)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:product_collection_items,id',
            'items.*.position' => 'required|integer|min:0',
        ]);

        foreach ($request->input('items') as $row) {
            ProductCollectionItem::where('id', $row['id'])
                ->where('collection_id', $id)
                ->update(['position' => $row['position']]);
        }

        return response()->json(['message' => 'Порядок товаров сохранён']);
    }

    /**
     * GET /admin/product-collections/available-products
     * Получить все доступные товары для добавления в коллекцию
     */
    public function availableProducts(Request $request)
    {
        $query = Product::where('is_active', true)
            ->whereNull('parent_id')
            ->with(['images', 'brand', 'category', 'stocks']);

        // Фильтр по бренду
        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->integer('brand_id'));
        }

        // Фильтр по категории (только конечные категории без дочерних)
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        // Фильтр по наличию
        if ($request->filled('in_stock')) {
            if ($request->boolean('in_stock')) {
                $query->whereHas('stocks', function($q) {
                    $q->where('quantity', '>', 0);
                });
            } else {
                $query->whereDoesntHave('stocks')
                    ->orWhereHas('stocks', function($q) {
                        $q->havingRaw('SUM(quantity) = 0');
                    });
            }
        }

        // Поиск
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $upper = 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $lower = 'абвгдеёжзийклмнопрстуфхцчшщъыьэюяabcdefghijklmnopqrstuvwxyz';

            $query->where(function ($q) use ($search, $upper, $lower) {
                $q->whereRaw("translate(title, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", ["%{$search}%"])
                    ->orWhereRaw("translate(article, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", ["%{$search}%"]);

                if (is_numeric($search)) {
                    $q->orWhere('id', $search);
                }
            });
        }

        $perPage = 20;
        $products = $query->orderBy('title')
            ->paginate($perPage)
            ->through(function ($product) {
                $totalStock = $product->stocks->sum('quantity');

                return [
                    'id' => $product->id,
                    'title' => $product->title,
                    'slug' => $product->slug,
                    'article' => $product->article,
                    'price' => $product->price,
                    'brand' => $product->brand ? [
                        'id' => $product->brand->id,
                        'name' => $product->brand->name,
                    ] : null,
                    'category' => $product->category ? [
                        'id' => $product->category->id,
                        'title' => $product->category->title,
                    ] : null,
                    'image' => $product->images->first()?->url ?? null,
                    'total_stock' => $totalStock,
                    'in_stock' => $totalStock > 0,
                ];
            });

        return response()->json([
            'data' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ]
        ]);
    }

    /**
     * GET /admin/product-collections/filters
     * Получить данные для фильтров (бренды и категории)
     */
    public function filters()
    {
        // Получаем только конечные категории (у которых нет дочерних)
        $categories = \App\Models\Category::whereDoesntHave('children')
            ->orderBy('title')
            ->get(['id', 'title']);

        $brands = \App\Models\Brand::orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'data' => [
                'categories' => $categories,
                'brands' => $brands,
            ]
        ]);
    }
}
