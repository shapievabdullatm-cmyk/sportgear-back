<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\Product\StoreRequest;
use App\Http\Requests\Api\Admin\Product\UpdateRequest;
use App\Http\Resources\Category\CategoryResource;
use App\Http\Resources\Product\ProductResource;
use App\Http\Resources\Brand\BrandResource;
use App\Http\Resources\BrandOrigin\BrandOriginResource;
use App\Http\Resources\ManufacturingCountry\ManufacturingCountryResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\Brand;
use App\Models\BrandOrigin;
use App\Models\ManufacturingCountry;
use App\Services\ProductService;

class ProductController extends Controller
{
    public function index(\Illuminate\Http\Request $request)
    {
        $query = Product::with([
            'images',
            'barcodes',
            'stocks.warehouse',
            'children.images',
            'children.stocks.warehouse',
        ]);

        // Поиск по названию, внешнему названию, артикулу, ID или штрихкоду
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            // Используем translate для преобразования кириллицы и латиницы в нижний регистр
            $upper = 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $lower = 'абвгдеёжзийклмнопрстуфхцчшщъыьэюяabcdefghijklmnopqrstuvwxyz';

            // Если include_children=1, ищем среди всех товаров (родительских и дочерних)
            if ($request->boolean('include_children')) {
                $query->where(function($q) use ($search, $upper, $lower) {
                    $q->whereRaw("translate(title, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", ["%{$search}%"])
                      ->orWhereRaw("translate(external_title, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", ["%{$search}%"])
                      ->orWhereRaw("translate(article, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", ["%{$search}%"]);

                    if (is_numeric($search)) {
                        $q->orWhere('id', $search);
                    }

                    // Поиск по штрихкодам
                    $q->orWhereHas('barcodes', function($bq) use ($search, $upper, $lower) {
                        $bq->whereRaw("translate(barcode, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", ["%{$search}%"]);
                    });
                });
            } else {
                // Стандартный поиск: только родительские товары
                $query->where(function($q) use ($search, $upper, $lower) {
                    // Поиск среди родительских товаров
                    $q->where(function($parentQuery) use ($search, $upper, $lower) {
                        $parentQuery->whereNull('parent_id')
                            ->where(function($sq) use ($search, $upper, $lower) {
                                $sq->whereRaw("translate(title, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", ["%{$search}%"])
                                   ->orWhereRaw("translate(external_title, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", ["%{$search}%"])
                                   ->orWhereRaw("translate(article, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", ["%{$search}%"]);

                                if (is_numeric($search)) {
                                    $sq->orWhere('id', $search);
                                }

                                // Поиск по штрихкодам родительского товара
                                $sq->orWhereHas('barcodes', function($bq) use ($search, $upper, $lower) {
                                    $bq->whereRaw("translate(barcode, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", ["%{$search}%"]);
                                });
                            });
                    })
                    // ИЛИ найти родителя, у которого есть дочерний товар, подходящий под поиск
                    ->orWhereHas('children', function($childQuery) use ($search, $upper, $lower) {
                        $childQuery->where(function($sq) use ($search, $upper, $lower) {
                            $sq->whereRaw("translate(title, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", ["%{$search}%"])
                               ->orWhereRaw("translate(external_title, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", ["%{$search}%"])
                               ->orWhereRaw("translate(article, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", ["%{$search}%"]);

                            if (is_numeric($search)) {
                                $sq->orWhere('id', $search);
                            }

                            // Поиск по штрихкодам дочернего товара
                            $sq->orWhereHas('barcodes', function($bq) use ($search, $upper, $lower) {
                                $bq->whereRaw("translate(barcode, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", ["%{$search}%"]);
                            });
                        });
                    });
                });

                // Показываем только родительские товары
                $query->whereNull('parent_id');
            }
        } else {
            // Без поиска показываем только родительские товары
            $query->whereNull('parent_id');
        }

        // Фильтр по активности: active | inactive | all (по умолчанию)
        $status = $request->string('status')->toString();
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        // Фильтр по наличию: in_stock | out_of_stock | all (по умолчанию)
        // Учитываем и собственные остатки, и остатки активных дочерних товаров.
        $stock = $request->string('stock')->toString();
        if ($stock === 'in_stock') {
            $query->where(function ($q) {
                $q->whereHas('stocks', fn($s) => $s->where('quantity', '>', 0))
                  ->orWhereHas('children', function ($c) {
                      $c->where('is_active', true)
                        ->whereHas('stocks', fn($s) => $s->where('quantity', '>', 0));
                  });
            });
        } elseif ($stock === 'out_of_stock') {
            $query->whereDoesntHave('stocks', fn($s) => $s->where('quantity', '>', 0))
                  ->whereDoesntHave('children', function ($c) {
                      $c->where('is_active', true)
                        ->whereHas('stocks', fn($s) => $s->where('quantity', '>', 0));
                  });
        }

        // Стабильная сортировка: сначала новые товары, чтобы порядок не «прыгал»
        // после обновлений (без ORDER BY Postgres возвращает строки в произвольном порядке).
        $query->orderByDesc('created_at')->orderByDesc('id');

        // Пагинация
        $perPage = $request->integer('per_page', 20);
        $products = $query->paginate($perPage);

        return ProductResource::collection($products);
    }

    public function create()
    {
        return response()->json([
            'categories'             => CategoryResource::collection(Category::orderBy('title')->get()),
            'brands'                 => BrandResource::collection(Brand::orderBy('name')->get()),
            'brandOrigins'           => BrandOriginResource::collection(BrandOrigin::orderBy('name')->get()),
            'manufacturingCountries' => ManufacturingCountryResource::collection(ManufacturingCountry::orderBy('name')->get()),
            'products'               => ProductResource::collection(Product::whereNull('parent_id')->orderBy('title')->get()),
        ]);
    }

    public function store(StoreRequest $request)
    {
        $validated = $request->validated();

        $data = collect($validated)->except(['params', 'images', 'bought_together_ids', 'copy_image_ids'])->toArray();
        $data['images'] = $request->file('images', []);
        $data['params'] = $validated['params'] ?? [];
        $data['bought_together_ids'] = $validated['bought_together_ids'] ?? [];
        $data['copy_image_ids'] = $validated['copy_image_ids'] ?? [];

        $product = ProductService::store($data);

        return ProductResource::make($product->load('images'));
    }

    public function show(Product $product)
    {
        return ProductResource::make($product->load([
            'images',
            'category',
            'productGroup',
            'paramValues.param',
            'paramValues.paramOption',
            'optionValues.param',
            'optionValues.paramOption'
        ]));
    }

    public function edit(Product $product)
    {
        $groupMembers = $product->product_group_id
            ? Product::with('images')
                ->where('product_group_id', $product->product_group_id)
                ->orderBy('id')
                ->get()
            : collect();

        return response()->json([
            'product'                => ProductResource::make($product->load([
                'images',
                'barcodes',
                'stocks.warehouse',
                'boughtTogetherProducts.images',
                'paramValues.param',
                'paramValues.paramOption',
                'optionValues.param',
                'optionValues.paramOption'
            ])),
            'categories'             => CategoryResource::collection(Category::orderBy('title')->get()),
            'brands'                 => BrandResource::collection(Brand::orderBy('name')->get()),
            'brandOrigins'           => BrandOriginResource::collection(BrandOrigin::orderBy('name')->get()),
            'manufacturingCountries' => ManufacturingCountryResource::collection(ManufacturingCountry::orderBy('name')->get()),
            'products'               => ProductResource::collection(Product::whereNull('parent_id')->where('id', '!=', $product->id)->orderBy('title')->get()),
            'groupMembers'           => ProductResource::collection($groupMembers),
        ]);
    }

    public function update(UpdateRequest $request, Product $product)
    {
        $validated = $request->validated();

        // Обновляем порядок существующих изображений
        foreach ($request->input('image_order', []) as $imageId => $position) {
            $product->images()->where('id', (int) $imageId)
                ->update(['sort_order' => (int) $position]);
        }

        $data = collect($validated)->except(['params', 'images', 'image_order', 'bought_together_ids', 'copy_image_ids'])->toArray();
        $data['images'] = $request->file('images', []);
        $data['params'] = $validated['params'] ?? [];
        $data['bought_together_ids'] = $validated['bought_together_ids'] ?? [];
        $data['copy_image_ids'] = $validated['copy_image_ids'] ?? [];

        $product = ProductService::update($product, $data);

        // Принудительно перезагружаем продукт с изображениями
        $product = Product::with('images')->find($product->id);

        return ProductResource::make($product);
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return response()->json(['message' => 'Успешно удалено']);
    }
}
