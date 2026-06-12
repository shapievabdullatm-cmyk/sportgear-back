<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Services\FuzzySearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    /**
     * Умный поиск с учетом опечаток
     * Ищет по: title, article, штрихкодам, параметрам (is_filterable), категориям
     */
    public function search(Request $request)
    {
        $query = trim($request->input('q', ''));

        if (strlen($query) < 1) {
            return response()->json([
                'products' => [],
                'categories' => [],
                'suggestions' => $this->getPopularSuggestions(),
            ]);
        }

        // Поиск товаров (обычный + fuzzy)
        $products = $this->searchProducts($query, $request);

        // Если обычный поиск не дал результатов, пробуем fuzzy search
        if (empty($products) && strlen($query) >= 3) {
            $products = $this->fuzzySearchProducts($query);
        }

        // Поиск категорий
        $categories = $this->searchCategories($query);

        // Если категории не найдены, пробуем fuzzy
        if (empty($categories) && strlen($query) >= 3) {
            $categories = $this->fuzzySearchCategories($query);
        }

        // Генерация подсказок
        $suggestions = $this->generateSuggestions($query, $products, $categories);

        return response()->json([
            'products' => $products,
            'categories' => $categories,
            'suggestions' => $suggestions,
        ]);
    }

    /**
     * Быстрые подсказки (автокомплит) - только названия
     */
    public function suggestions(Request $request)
    {
        $query = trim($request->input('q', ''));

        if (strlen($query) < 1) {
            return response()->json([
                'suggestions' => $this->getPopularSuggestions(),
            ]);
        }

        $queryLower = mb_strtolower($query);
        $queryFirstUpper = mb_strtoupper(mb_substr($query, 0, 1)) . mb_substr($query, 1);
        $searchTerm = "%{$queryLower}%";
        $searchTermFirstUpper = "%{$queryFirstUpper}%";

        $suggestions = [];

        // Подсказки из товаров
        $productSuggestions = Product::where('is_active', true)
            ->where(function ($q) use ($query, $queryLower, $queryFirstUpper, $searchTerm, $searchTermFirstUpper) {
                $q->where('title', 'LIKE', $searchTerm)
                    ->orWhere('title', 'LIKE', $searchTermFirstUpper)
                    ->orWhere('article', 'LIKE', "%{$query}%")
                    ->orWhere('article', 'LIKE', "%{$queryLower}%");
            })
            ->select('title')
            ->distinct()
            ->limit(5)
            ->pluck('title')
            ->toArray();

        // Подсказки из категорий
        $categorySuggestions = Category::where(function ($q) use ($queryLower, $queryFirstUpper, $searchTerm, $searchTermFirstUpper) {
            $q->where('title', 'LIKE', $searchTerm)
                ->orWhere('title', 'LIKE', $searchTermFirstUpper)
                ->orWhere('keywords', 'LIKE', $searchTerm)
                ->orWhere('keywords', 'LIKE', $searchTermFirstUpper);
        })
            ->select('title')
            ->limit(3)
            ->pluck('title')
            ->toArray();

        $suggestions = array_merge($productSuggestions, $categorySuggestions);
        $suggestions = array_unique($suggestions);
        $suggestions = array_slice($suggestions, 0, 8);

        return response()->json([
            'suggestions' => array_values($suggestions),
        ]);
    }

    /**
     * Поиск товаров с учетом всех требований
     */
    private function searchProducts(string $query, ?Request $request = null): array
    {
        // Нормализуем запрос - убираем лишние пробелы
        $query = trim($query);
        $queryLower = mb_strtolower($query);
        $queryUpper = mb_strtoupper($query);
        $queryFirstUpper = mb_strtoupper(mb_substr($query, 0, 1)) . mb_substr($query, 1);

        $searchTerm = "%{$query}%";
        $searchTermLower = "%{$queryLower}%";
        $searchTermUpper = "%{$queryUpper}%";
        $searchTermFirstUpper = "%{$queryFirstUpper}%";

        $searchStart = "{$query}%";
        $searchStartLower = "{$queryLower}%";
        $searchStartUpper = "{$queryUpper}%";
        $searchStartFirstUpper = "{$queryFirstUpper}%";

        // Разбиваем запрос на слова для более умного поиска
        $words = array_filter(explode(' ', $queryLower));

        $productsQuery = Product::with(['images', 'category'])
            ->where('is_active', true)
            ->whereNull('parent_id') // Исключаем дочерние товары
            ->where(function ($q) use ($query, $queryLower, $queryUpper, $queryFirstUpper,
                $searchStart, $searchStartLower, $searchStartUpper, $searchStartFirstUpper,
                $searchTerm, $searchTermLower, $searchTermUpper, $searchTermFirstUpper, $words) {

                // Поиск с начала строки (высший приоритет) - все варианты регистра
                $q->where('title', 'LIKE', $searchStart)
                    ->orWhere('title', 'LIKE', $searchStartLower)
                    ->orWhere('title', 'LIKE', $searchStartUpper)
                    ->orWhere('title', 'LIKE', $searchStartFirstUpper)
                    ->orWhere('article', 'LIKE', $searchStart)
                    ->orWhere('article', 'LIKE', $searchStartLower)
                    ->orWhere('article', 'LIKE', $searchStartUpper);

                // Точное совпадение в любом месте
                $q->orWhere('title', 'LIKE', $searchTerm)
                    ->orWhere('title', 'LIKE', $searchTermLower)
                    ->orWhere('title', 'LIKE', $searchTermUpper)
                    ->orWhere('title', 'LIKE', $searchTermFirstUpper)
                    ->orWhere('article', 'LIKE', $searchTerm)
                    ->orWhere('meta_keywords', 'LIKE', $searchTerm);

                // Поиск по отдельным словам
                foreach ($words as $word) {
                    if (strlen($word) >= 2) {
                        $wordLower = mb_strtolower($word);
                        $wordUpper = mb_strtoupper($word);
                        $wordFirstUpper = mb_strtoupper(mb_substr($word, 0, 1)) . mb_substr($word, 1);

                        $q->orWhere('title', 'LIKE', "{$wordLower}%")
                            ->orWhere('title', 'LIKE', "{$wordUpper}%")
                            ->orWhere('title', 'LIKE', "{$wordFirstUpper}%")
                            ->orWhere('title', 'LIKE', "%{$wordLower}%")
                            ->orWhere('title', 'LIKE', "%{$wordFirstUpper}%");
                    }
                }

                // Поиск по штрихкодам
                $q->orWhereHas('barcodes', function ($bq) use ($searchStart, $searchTerm) {
                    $bq->where('barcode', 'LIKE', $searchStart)
                        ->orWhere('barcode', 'LIKE', $searchTerm);
                });

                // Поиск по параметрам с is_filterable
                $q->orWhereHas('paramValues', function ($pq) use ($searchTerm, $searchTermFirstUpper, $searchStart, $searchStartFirstUpper, $words) {
                    $pq->whereHas('param', function ($paramQ) {
                        $paramQ->where('is_filterable', true);
                    })
                        ->where(function ($valueQ) use ($searchTerm, $searchTermFirstUpper, $searchStart, $searchStartFirstUpper, $words) {
                            $valueQ->where('value_string', 'LIKE', $searchStart)
                                ->orWhere('value_string', 'LIKE', $searchStartFirstUpper)
                                ->orWhere('value_string', 'LIKE', $searchTerm)
                                ->orWhere('value_string', 'LIKE', $searchTermFirstUpper)
                                ->orWhere('value_text', 'LIKE', $searchTerm);

                            // Поиск по словам в параметрах
                            foreach ($words as $word) {
                                if (strlen($word) >= 2) {
                                    $wordFirstUpper = mb_strtoupper(mb_substr($word, 0, 1)) . mb_substr($word, 1);
                                    $valueQ->orWhere('value_string', 'LIKE', "%{$word}%")
                                        ->orWhere('value_string', 'LIKE', "%{$wordFirstUpper}%");
                                }
                            }

                            // Поиск по опциям параметров
                            $valueQ->orWhereHas('option', function ($optQ) use ($searchTerm, $searchTermFirstUpper, $searchStart, $searchStartFirstUpper, $words) {
                                $optQ->where('title', 'LIKE', $searchStart)
                                    ->orWhere('title', 'LIKE', $searchStartFirstUpper)
                                    ->orWhere('title', 'LIKE', $searchTerm)
                                    ->orWhere('title', 'LIKE', $searchTermFirstUpper);

                                foreach ($words as $word) {
                                    if (strlen($word) >= 2) {
                                        $wordFirstUpper = mb_strtoupper(mb_substr($word, 0, 1)) . mb_substr($word, 1);
                                        $optQ->orWhere('title', 'LIKE', "%{$word}%")
                                            ->orWhere('title', 'LIKE', "%{$wordFirstUpper}%");
                                    }
                                }
                            });
                        });
                });
            });

        // Применяем фильтры если есть
        if ($request) {
            $this->applySearchFilters($productsQuery, $request);
        }

        $products = $productsQuery
            // Сортировка по релевантности
            ->orderByRaw("
                CASE
                    WHEN title LIKE ? OR title LIKE ? THEN 1
                    WHEN article LIKE ? OR article LIKE ? THEN 2
                    WHEN title LIKE ? THEN 3
                    ELSE 4
                END
            ", [
                $searchStartFirstUpper, $searchStart,
                $searchStartFirstUpper, $searchStart,
                $searchTermFirstUpper
            ])
            ->limit(50)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'title' => $product->title,
                    'slug' => $product->slug,
                    'article' => $product->article,
                    'price' => $product->price,
                    'old_price' => $product->old_price,
                    'images' => $product->images->map(fn($img) => ['url' => $img->url])->toArray(),
                    'category' => $product->category ? [
                        'id' => $product->category->id,
                        'title' => $product->category->title,
                        'slug' => $product->category->slug,
                    ] : null,
                ];
            })
            ->toArray();

        return $products;
    }

    /**
     * Получить ID товаров из результатов поиска (без фильтров)
     */
    private function getSearchProductIds(string $query): array
    {
        $products = $this->searchProducts($query);
        return array_column($products, 'id');
    }

    /**
     * Применить фильтры к поиску товаров
     */
    private function applySearchFilters($query, Request $request)
    {
        // Фильтр по цене
        if ($request->has('price_min')) {
            $query->where('price', '>=', $request->input('price_min'));
        }
        if ($request->has('price_max')) {
            $query->where('price', '<=', $request->input('price_max'));
        }

        // Фильтры по параметрам
        $filters = $request->input('filters', []);
        if (!empty($filters) && is_array($filters)) {
            foreach ($filters as $paramId => $values) {
                if (empty($values)) continue;

                $paramId = (int)$paramId;
                $param = \App\Models\Param::find($paramId);
                if (!$param) continue;

                if ($param->has_options) {
                    $optionIds = is_array($values) ? array_map('intval', $values) : [(int)$values];

                    $query->where(function($q) use ($paramId, $optionIds) {
                        $q->whereHas('optionValues', function($optQ) use ($paramId, $optionIds) {
                            $optQ->where('param_id', $paramId)
                                ->whereIn('param_option_id', $optionIds);
                        })
                            ->orWhereHas('paramValues', function($optQ) use ($paramId, $optionIds) {
                                $optQ->where('param_id', $paramId)
                                    ->whereIn('param_option_id', $optionIds);
                            });
                    });
                } else {
                    if ($param->filter_type === 7) {
                        // BOOLEAN
                        $boolValue = is_array($values) ? (int)$values[0] : (int)$values;
                        $query->whereHas('paramValues', function($subQ) use ($paramId, $boolValue) {
                            $subQ->where('param_id', $paramId)
                                ->where('value_int', $boolValue);
                        });
                    } elseif (is_array($values) && isset($values['min']) && isset($values['max'])) {
                        // INTEGER, FLOAT диапазон
                        $column = $param->filter_type === 3 ? 'value_int' : 'value_float';
                        $query->whereHas('paramValues', function($subQ) use ($paramId, $values, $column) {
                            $subQ->where('param_id', $paramId)
                                ->whereBetween($column, [(float)$values['min'], (float)$values['max']]);
                        });
                    }
                }
            }
        }
    }

    /**
     * Поиск категорий с учетом ключевых слов
     */
    private function searchCategories(string $query): array
    {
        $query = trim($query);
        $queryLower = mb_strtolower($query);
        $queryFirstUpper = mb_strtoupper(mb_substr($query, 0, 1)) . mb_substr($query, 1);

        $searchTerm = "%{$queryLower}%";
        $searchTermFirstUpper = "%{$queryFirstUpper}%";
        $searchStart = "{$queryLower}%";
        $searchStartFirstUpper = "{$queryFirstUpper}%";

        // Разбиваем запрос на слова
        $words = array_filter(explode(' ', $queryLower));

        $categories = Category::where(function ($q) use ($query, $queryLower, $queryFirstUpper,
            $searchTerm, $searchTermFirstUpper,
            $searchStart, $searchStartFirstUpper, $words) {
            // Поиск с начала строки (высший приоритет)
            $q->where('title', 'LIKE', $searchStart)
                ->orWhere('title', 'LIKE', $searchStartFirstUpper)
                ->orWhere('keywords', 'LIKE', $searchStart)
                ->orWhere('keywords', 'LIKE', $searchStartFirstUpper);

            // Точное совпадение в любом месте
            $q->orWhere('title', 'LIKE', $searchTerm)
                ->orWhere('title', 'LIKE', $searchTermFirstUpper)
                ->orWhere('keywords', 'LIKE', $searchTerm)
                ->orWhere('keywords', 'LIKE', $searchTermFirstUpper);

            // Поиск по отдельным словам
            foreach ($words as $word) {
                if (strlen($word) >= 2) {
                    $wordFirstUpper = mb_strtoupper(mb_substr($word, 0, 1)) . mb_substr($word, 1);

                    $q->orWhere('title', 'LIKE', "{$word}%")
                        ->orWhere('title', 'LIKE', "{$wordFirstUpper}%")
                        ->orWhere('title', 'LIKE', "%{$word}%")
                        ->orWhere('title', 'LIKE', "%{$wordFirstUpper}%")
                        ->orWhere('keywords', 'LIKE', "%{$word}%")
                        ->orWhere('keywords', 'LIKE', "%{$wordFirstUpper}%");
                }
            }
        })
            // Сортировка по релевантности
            ->orderByRaw("
                CASE
                    WHEN title LIKE ? OR title LIKE ? THEN 1
                    WHEN title LIKE ? THEN 2
                    WHEN title LIKE ? OR title LIKE ? THEN 3
                    ELSE 4
                END
            ", [
                $searchStartFirstUpper, $searchStart,
                $queryFirstUpper,
                $searchTermFirstUpper, $searchTerm
            ])
            ->limit(10)
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'title' => $category->title,
                    'slug' => $category->slug,
                    'image' => $category->image ? \App\Services\ImageService::url($category->image) : null,
                ];
            })
            ->toArray();

        return $categories;
    }

    /**
     * Генерация умных подсказок на основе результатов
     */
    private function generateSuggestions(string $query, array $products, array $categories): array
    {
        $suggestions = [];

        // Добавляем названия категорий
        foreach ($categories as $category) {
            $suggestions[] = $category['title'];
        }

        // Добавляем уникальные названия товаров
        $productTitles = array_unique(array_column($products, 'title'));
        $suggestions = array_merge($suggestions, array_slice($productTitles, 0, 5));

        // Убираем дубликаты и ограничиваем
        $suggestions = array_unique($suggestions);
        $suggestions = array_slice($suggestions, 0, 8);

        return array_values($suggestions);
    }

    /**
     * Популярные запросы (когда поиск пустой)
     */
    private function getPopularSuggestions(): array
    {
        // Можно заменить на реальные популярные запросы из аналитики
        return [
            'Кроссовки',
            'Футболки',
            'Куртки',
            'Джинсы',
            'Аксессуары',
            'Новинки',
        ];
    }

    /**
     * Fuzzy поиск товаров (с учетом опечаток)
     */
    private function fuzzySearchProducts(string $query): array
    {
        // Получаем все активные товары (ограничиваем для производительности)
        $products = Product::with(['images', 'category'])
            ->where('is_active', true)
            ->whereNull('parent_id') // Исключаем дочерние товары
            ->limit(200)
            ->get();

        // Фильтруем через fuzzy search
        $filtered = $products->filter(function ($product) use ($query) {
            return FuzzySearchService::isSimilar($query, $product->title);
        });

        // Сортируем по similarity score
        $sorted = $filtered->sortByDesc(function ($product) use ($query) {
            return FuzzySearchService::similarityScore($query, $product->title);
        });

        return $sorted->take(20)->map(function ($product) {
            return [
                'id' => $product->id,
                'title' => $product->title,
                'slug' => $product->slug,
                'article' => $product->article,
                'price' => $product->price,
                'old_price' => $product->old_price,
                'images' => $product->images->map(fn($img) => ['url' => $img->url])->toArray(),
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'title' => $product->category->title,
                    'slug' => $product->category->slug,
                ] : null,
            ];
        })->values()->toArray();
    }

    /**
     * Fuzzy поиск категорий (с учетом опечаток)
     */
    private function fuzzySearchCategories(string $query): array
    {
        // Получаем все категории
        $categories = Category::all();

        // Фильтруем через fuzzy search
        $filtered = $categories->filter(function ($category) use ($query) {
            return FuzzySearchService::isSimilar($query, $category->title) ||
                ($category->keywords && FuzzySearchService::isSimilar($query, $category->keywords));
        });

        // Сортируем по similarity score
        $sorted = $filtered->sortByDesc(function ($category) use ($query) {
            return FuzzySearchService::similarityScore($query, $category->title);
        });

        return $sorted->take(5)->map(function ($category) {
            return [
                'id' => $category->id,
                'title' => $category->title,
                'slug' => $category->slug,
                'image' => $category->image ? \App\Services\ImageService::url($category->image) : null,
            ];
        })->values()->toArray();
    }
}
