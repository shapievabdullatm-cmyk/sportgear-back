<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\Param\ParamFilterTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\Category\StoreRequest;
use App\Http\Requests\Api\Admin\Category\UpdateRequest;
use App\Http\Resources\Category\CategoryResource;
use App\Models\Category;
use App\Models\Param;
use App\Models\Product;
use App\Services\CategoryService;
use App\Services\ImageService;
use App\Services\ParamResolver;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    // GET /admin/categories
    public function index(Request $request)
    {
        $query = Category::without(['parent', 'children'])
            ->orderBy('position');

        // Поиск
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($q) use ($search) {
                // Используем translate для преобразования кириллицы и латиницы в нижний регистр
                $upper = 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $lower = 'абвгдеёжзийклмнопрстуфхцчшщъыьэюяabcdefghijklmnopqrstuvwxyz';

                $q->whereRaw("translate(title, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", ["%{$search}%"])
                  ->orWhereRaw("translate(slug, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", ["%{$search}%"]);
            });
        }

        // Если запрашивается список (не иерархия), используем пагинацию
        if ($request->boolean('paginate', false)) {
            $perPage = $request->integer('per_page', 20);
            $categories = $query->paginate($perPage);

            return CategoryResource::collection($categories);
        }

        // Для иерархии возвращаем все
        $categories = $query->get();
        return CategoryResource::collection($categories);
    }

    // GET /admin/categories/create
    public function create()
    {
        return response()->json([
            'category'   => CategoryResource::make(new Category()),
            'categories' => CategoryResource::collection(Category::orderBy('position')->get()),
            'params'     => $this->paramsList(),
        ]);
    }

    // POST /admin/categories
    public function store(StoreRequest $request)
    {
        $data = $request->validated();
        $data['image'] = $request->file('image');

        $category = CategoryService::store($data);

        return CategoryResource::make($category);
    }

    // GET /admin/categories/{category}/edit
    public function edit(Category $category)
    {
        return response()->json([
            'category'   => CategoryResource::make($category),
            'categories' => CategoryResource::collection(
                Category::orderBy('position')->get()->except($category->id)
            ),
            'params'     => $this->paramsList(),
        ]);
    }

    // POST /admin/categories/{category} + _method=PATCH
    public function update(UpdateRequest $request, Category $category)
    {
        $data = $request->validated();

        if ($request->boolean('remove_image')) {
            $data['image'] = null;
        } elseif ($request->hasFile('image')) {
            $data['image'] = $request->file('image');
        }

        $category = CategoryService::update($category, $data);

        return CategoryResource::make($category);
    }

    // DELETE /admin/categories/{category}
    public function destroy(Category $category)
    {
        // Проверяем количество продуктов в категории
        $productsCount = Product::where('category_id', $category->id)->count();

        // Удаляем изображение категории
        ImageService::delete($category->image);

        // Удаляем категорию (у продуктов category_id станет null благодаря nullOnDelete)
        $category->delete();

        $message = 'Категория успешно удалена';
        if ($productsCount > 0) {
            $message .= ". У {$productsCount} " .
                        ($productsCount === 1 ? 'товара' : 'товаров') .
                        ' категория была обнулена';
        }

        return response()->json([
            'message' => $message,
            'products_affected' => $productsCount,
        ]);
    }

    // POST /admin/categories/reorder
    public function reorder(Request $request)
    {
        $request->validate([
            'items'            => 'required|array',
            'items.*.id'       => 'required|integer|exists:categories,id',
            'items.*.position' => 'required|integer|min:0',
        ]);

        CategoryService::reorder($request->input('items'));

        return response()->json(['message' => 'Порядок сохранён']);
    }

    /**
     * GET /admin/categories/{category}/params
     *
     * Возвращает список параметров категории с учётом наследования.
     * Если передан ?product_id={id} — параметры заполняются значениями этого товара.
     */
    public function resolvedParams(Request $request, Category $category): \Illuminate\Http\JsonResponse
    {
        if ($request->filled('product_id')) {
            $product = Product::with(['paramValues', 'optionValues'])
                ->findOrFail($request->integer('product_id'));

            $params = ParamResolver::resolveForCategoryWithValues($category, $product);
        } else {
            $params = ParamResolver::resolveForCategory($category);
        }

        return response()->json(['params' => $params]);
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private function paramsList(): array
    {
        return Param::query()
            ->select(['id', 'title', 'filter_type'])
            ->orderBy('title')
            ->get()
            ->map(function (Param $p) {
                $enum = ParamFilterTypeEnum::tryFrom($p->filter_type);
                return [
                    'id'         => $p->id,
                    'title'      => $p->title,
                    'type_label' => $enum?->label(),
                ];
            })
            ->toArray();
    }
}
