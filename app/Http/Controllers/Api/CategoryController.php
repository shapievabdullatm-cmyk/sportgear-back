<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Category\CategoryResource;
use App\Http\Resources\Product\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Получить категорию по slug с товарами
     */
    public function show(Request $request, $slug)
    {
        $category = Category::where('slug', $slug)
            ->with(['parent.parent', 'children' => function ($query) {
                $query->orderBy('position');
            }])
            ->firstOrFail();

        // Рекурсивно получаем все ID дочерних категорий
        $categoryIds = $this->getAllChildCategoryIds($category);

        // Получаем товары из текущей категории и всех дочерних (на всех уровнях)
        $query = Product::whereIn('category_id', $categoryIds)
            ->where('is_active', true)
            ->whereNull('parent_id') // Только родительские товары
            ->with([
                'images',
                'children.images',
                'children.paramValues.param',
                'children.paramValues.paramOption',
                'children.optionValues.param',
                'children.optionValues.paramOption',
            ]); // Загружаем дочерние товары с параметрами для размеров

        // Применяем фильтры
        $this->applyFilters($query, $request, $categoryIds);

        // Пагинация
        $perPage = $request->integer('per_page', 20);
        $products = $query->paginate($perPage);

        // Формируем хлебные крошки
        $breadcrumbs = [];
        $current = $category;
        while ($current) {
            array_unshift($breadcrumbs, [
                'id' => $current->id,
                'title' => $current->title,
                'slug' => $current->slug,
            ]);
            $current = $current->parent;
        }

        return response()->json([
            'category' => CategoryResource::make($category),
            'breadcrumbs' => $breadcrumbs,
            'children' => $category->children->map(fn($child) => [
                'id' => $child->id,
                'title' => $child->title,
                'slug' => $child->slug,
                'image' => $child->image ? \App\Services\ImageService::url($child->image) : null,
            ]),
            'products' => ProductResource::collection($products)->response()->getData(),
        ]);
    }

    /**
     * Получить доступные фильтры для категории
     */
    public function filters(Request $request, $slug)
    {
        $category = Category::where('slug', $slug)->firstOrFail();
        $categoryIds = $this->getAllChildCategoryIds($category);

        // Получаем все фильтруемые параметры для категории с учетом иерархии
        $params = \App\Services\ParamResolver::resolveForCategory($category);
        $filterableParams = array_filter($params, fn($p) => $p['is_filterable']);

        // Получаем уникальные значения параметров из товаров категории
        $filters = [];
        foreach ($filterableParams as $param) {
            $filterData = [
                'id' => $param['id'],
                'title' => $param['title'],
                'slug' => $param['slug'],
                'filter_type' => $param['filter_type'],
                'unit' => $param['unit'],
                'has_options' => $param['has_options'],
            ];

            // BOOLEAN параметры обрабатываем отдельно
            if ($param['filter_type'] === 7) {
                // Проверяем, есть ли хоть одно значение для этого параметра
                $hasValues = \DB::table('product_param_values')
                    ->join('products', 'products.id', '=', 'product_param_values.product_id')
                    ->where('product_param_values.param_id', $param['id'])
                    ->where('products.is_active', true)
                    ->where(function($q) use ($categoryIds) {
                        $q->where(function($subQ) use ($categoryIds) {
                            $subQ->whereIn('products.category_id', $categoryIds)
                                ->whereNull('products.parent_id');
                        })
                        ->orWhereIn('products.parent_id', function($subQ) use ($categoryIds) {
                            $subQ->select('id')
                                ->from('products as parent_products')
                                ->whereIn('parent_products.category_id', $categoryIds)
                                ->where('parent_products.is_active', true);
                        });
                    })
                    ->whereNotNull('product_param_values.value_int')
                    ->exists();

                if (!$hasValues) {
                    continue;
                }

                // Для BOOLEAN не добавляем опции - используется тумблер на frontend
                $filterData['has_options'] = false;
            } elseif ($param['has_options']) {
                // Для параметров с опциями (COLOR, MULTISELECT)
                // Проверяем как родительские, так и дочерние товары
                $usedOptionIds = \DB::table('product_param_option_values')
                    ->join('products', 'products.id', '=', 'product_param_option_values.product_id')
                    ->where('product_param_option_values.param_id', $param['id'])
                    ->where('products.is_active', true)
                    ->where(function($q) use ($categoryIds) {
                        // Родительские товары из категории
                        $q->where(function($subQ) use ($categoryIds) {
                            $subQ->whereIn('products.category_id', $categoryIds)
                                ->whereNull('products.parent_id');
                        })
                        // ИЛИ дочерние товары, чьи родители из категории
                        ->orWhereIn('products.parent_id', function($subQ) use ($categoryIds) {
                            $subQ->select('id')
                                ->from('products as parent_products')
                                ->whereIn('parent_products.category_id', $categoryIds)
                                ->where('parent_products.is_active', true);
                        });
                    })
                    ->distinct()
                    ->pluck('product_param_option_values.param_option_id');

                // Также проверяем одиночные значения
                $singleOptionIds = \DB::table('product_param_values')
                    ->join('products', 'products.id', '=', 'product_param_values.product_id')
                    ->where('product_param_values.param_id', $param['id'])
                    ->where('products.is_active', true)
                    ->whereNotNull('product_param_values.param_option_id')
                    ->where(function($q) use ($categoryIds) {
                        // Родительские товары из категории
                        $q->where(function($subQ) use ($categoryIds) {
                            $subQ->whereIn('products.category_id', $categoryIds)
                                ->whereNull('products.parent_id');
                        })
                        // ИЛИ дочерние товары, чьи родители из категории
                        ->orWhereIn('products.parent_id', function($subQ) use ($categoryIds) {
                            $subQ->select('id')
                                ->from('products as parent_products')
                                ->whereIn('parent_products.category_id', $categoryIds)
                                ->where('parent_products.is_active', true);
                        });
                    })
                    ->distinct()
                    ->pluck('product_param_values.param_option_id');

                $allUsedOptionIds = $usedOptionIds->merge($singleOptionIds)->unique();

                $filterData['options'] = array_values(array_filter($param['options'], function($opt) use ($allUsedOptionIds) {
                    return $allUsedOptionIds->contains($opt['id']);
                }));

                // Не добавляем фильтр, если нет опций
                if (empty($filterData['options'])) {
                    continue;
                }
            } else {
                // Для скалярных параметров (INTEGER, FLOAT) - получаем min/max
                if (in_array($param['filter_type'], [3, 4])) { // INTEGER, FLOAT
                    $column = $param['filter_type'] === 3 ? 'value_int' : 'value_float';

                    $minMax = \DB::table('product_param_values')
                        ->join('products', 'products.id', '=', 'product_param_values.product_id')
                        ->where('product_param_values.param_id', $param['id'])
                        ->whereIn('products.category_id', $categoryIds)
                        ->where('products.is_active', true)
                        ->where(function($q) use ($categoryIds) {
                            $q->where(function($subQ) use ($categoryIds) {
                                $subQ->whereIn('products.category_id', $categoryIds)
                                    ->whereNull('products.parent_id');
                            })
                            ->orWhereIn('products.parent_id', function($subQ) use ($categoryIds) {
                                $subQ->select('id')
                                    ->from('products as parent_products')
                                    ->whereIn('parent_products.category_id', $categoryIds)
                                    ->where('parent_products.is_active', true);
                            });
                        })
                        ->whereNotNull("product_param_values.{$column}")
                        ->selectRaw("MIN({$column}) as min, MAX({$column}) as max")
                        ->first();

                    if ($minMax && $minMax->min !== null) {
                        $filterData['min'] = $param['filter_type'] === 3 ? (int)$minMax->min : (float)$minMax->min;
                        $filterData['max'] = $param['filter_type'] === 3 ? (int)$minMax->max : (float)$minMax->max;
                    } else {
                        // Не добавляем фильтр, если нет значений
                        continue;
                    }
                } else {
                    // Для других типов без опций (STRING, TEXT) - пропускаем
                    continue;
                }
            }

            $filters[] = $filterData;
        }

        // Получаем ценовой диапазон
        $priceRange = Product::whereIn('category_id', $categoryIds)
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->whereNotNull('price')
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
            ->first();

        return response()->json([
            'filters' => $filters,
            'price_range' => [
                'min' => $priceRange->min_price ? (float)$priceRange->min_price : 0,
                'max' => $priceRange->max_price ? (float)$priceRange->max_price : 0,
            ],
        ]);
    }

    /**
     * Применить фильтры к запросу товаров
     */
    private function applyFilters($query, Request $request, array $categoryIds)
    {
        // Фильтр по цене
        if ($request->has('price_min')) {
            $query->where('price', '>=', $request->input('price_min'));
        }
        if ($request->has('price_max')) {
            $query->where('price', '<=', $request->input('price_max'));
        }

        // Получаем все параметры категории для маппинга slug -> id
        $params = \App\Services\ParamResolver::resolveForCategory(
            Category::where('slug', $request->route('slug'))->first()
        );
        $filterableParams = array_filter($params, fn($p) => $p['is_filterable']);

        // Создаем маппинг slug -> param data
        $paramsBySlug = [];
        foreach ($filterableParams as $param) {
            $paramsBySlug[$param['slug']] = $param;
        }

        // Парсим фильтры из query параметров
        foreach ($request->query() as $key => $value) {
            // Пропускаем служебные параметры
            if (in_array($key, ['price_min', 'price_max', 'page', 'per_page'])) {
                continue;
            }

            // Проверяем, является ли это параметром фильтра
            if (!isset($paramsBySlug[$key])) {
                continue;
            }

            $param = $paramsBySlug[$key];
            $paramId = $param['id'];

            // BOOLEAN фильтр (например: waterproof=true)
            if ($param['filter_type'] === 7) {
                // Принимаем: true, 1, yes
                $boolValue = in_array(strtolower($value), ['true', '1', 'yes']) ? 1 : 0;

                $query->where(function($q) use ($paramId, $boolValue) {
                    $q->whereHas('paramValues', function($subQ) use ($paramId, $boolValue) {
                        $subQ->where('param_id', $paramId)
                            ->where('value_int', $boolValue);
                    })
                    ->orWhereHas('children', function($childQ) use ($paramId, $boolValue) {
                        $childQ->where('is_active', true)
                            ->whereHas('paramValues', function($subQ) use ($paramId, $boolValue) {
                                $subQ->where('param_id', $paramId)
                                    ->where('value_int', $boolValue);
                            });
                    });
                });
            }
            // Фильтр с опциями (COLOR, MULTISELECT) (например: color=red,blue)
            elseif ($param['has_options']) {
                // Значение может быть строкой "red" или массивом ["red", "blue"]
                $optionSlugs = is_array($value) ? $value : explode(',', $value);

                // Находим ID опций по slug
                $optionIds = [];
                foreach ($param['options'] as $option) {
                    if (in_array($option['slug'], $optionSlugs)) {
                        $optionIds[] = $option['id'];
                    }
                }

                if (empty($optionIds)) continue;

                $query->where(function($q) use ($paramId, $optionIds) {
                    $q->where(function($subQ) use ($paramId, $optionIds) {
                        $subQ->whereHas('optionValues', function($optQ) use ($paramId, $optionIds) {
                            $optQ->where('param_id', $paramId)
                                ->whereIn('param_option_id', $optionIds);
                        })
                        ->orWhereHas('paramValues', function($optQ) use ($paramId, $optionIds) {
                            $optQ->where('param_id', $paramId)
                                ->whereIn('param_option_id', $optionIds);
                        });
                    })
                    ->orWhereHas('children', function($childQ) use ($paramId, $optionIds) {
                        $childQ->where('is_active', true)
                            ->where(function($paramQ) use ($paramId, $optionIds) {
                                $paramQ->whereHas('optionValues', function($optQ) use ($paramId, $optionIds) {
                                    $optQ->where('param_id', $paramId)
                                        ->whereIn('param_option_id', $optionIds);
                                })
                                ->orWhereHas('paramValues', function($optQ) use ($paramId, $optionIds) {
                                    $optQ->where('param_id', $paramId)
                                        ->whereIn('param_option_id', $optionIds);
                                });
                            });
                    });
                });
            }
            // Диапазон (INTEGER, FLOAT) (например: weight_min=1&weight_max=5)
            elseif (in_array($param['filter_type'], [3, 4])) {
                $minKey = $key . '_min';
                $maxKey = $key . '_max';

                if ($request->has($minKey) && $request->has($maxKey)) {
                    $column = $param['filter_type'] === 3 ? 'value_int' : 'value_float';
                    $min = (float)$request->input($minKey);
                    $max = (float)$request->input($maxKey);

                    $query->whereHas('paramValues', function($subQ) use ($paramId, $min, $max, $column) {
                        $subQ->where('param_id', $paramId)
                            ->whereBetween($column, [$min, $max]);
                    });
                }
            }
        }
    }

    /**
     * Рекурсивно получить все ID дочерних категорий
     */
    private function getAllChildCategoryIds(Category $category): array
    {
        $ids = [$category->id];

        $children = Category::where('parent_id', $category->id)->get();

        foreach ($children as $child) {
            $ids = array_merge($ids, $this->getAllChildCategoryIds($child));
        }

        return $ids;
    }

    /**
     * Список всех категорий
     */
    public function index()
    {
        $categories = Category::orderBy('position')->get();
        return CategoryResource::collection($categories);
    }
}