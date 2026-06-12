<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Param;
use App\Models\Product;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    private const MAX_PRODUCTS    = 50;
    private const MAX_CATEGORIES  = 10;
    private const MAX_SUGGESTIONS = 8;

    // ── Public endpoints ──────────────────────────────────────────────────────

    public function search(Request $request): JsonResponse
    {
        $query = trim($request->input('q', ''));

        if (mb_strlen($query) < 1) {
            return response()->json([
                'products'    => [],
                'categories'  => [],
                'suggestions' => $this->popularSuggestions(),
            ]);
        }

        $products   = $this->searchProducts($query, $request);
        $categories = $this->searchCategories($query);

        return response()->json([
            'products'    => $products,
            'categories'  => $categories,
            'suggestions' => $this->buildSuggestions($products, $categories),
        ]);
    }

    public function suggestions(Request $request): JsonResponse
    {
        $query = trim($request->input('q', ''));

        if (mb_strlen($query) < 1) {
            return response()->json(['suggestions' => $this->popularSuggestions()]);
        }

        $products = Product::search($query)
            ->query(fn($q) => $q->whereNull('parent_id')->where('is_active', true))
            ->take(5)
            ->get();

        $categories = Category::search($query)->take(3)->get();

        $suggestions = collect($products->pluck('title'))
            ->merge($categories->pluck('title'))
            ->unique()
            ->take(self::MAX_SUGGESTIONS)
            ->values()
            ->all();

        return response()->json(['suggestions' => $suggestions]);
    }

    public function filters(Request $request): JsonResponse
    {
        $query = trim($request->input('q', ''));

        if (mb_strlen($query) < 1) {
            return response()->json(['filters' => []]);
        }

        // Fetch products matching the query (IDs only for efficiency).
        $productIds = Product::search($query)
            ->take(self::MAX_PRODUCTS)
            ->keys();

        if ($productIds->isEmpty()) {
            return response()->json(['filters' => []]);
        }

        // Drop any child or inactive products that may have leaked through a stale index.
        $productIds = Product::whereIn('id', $productIds)
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->pluck('id');

        if ($productIds->isEmpty()) {
            return response()->json(['filters' => []]);
        }

        // Aggregate filterable params from matching products.
        $params = Param::where('is_filterable', true)
            ->whereHas('categories', function ($q) use ($productIds) {
                $q->whereHas('products', fn($pq) => $pq->whereIn('products.id', $productIds));
            })
            ->with(['options' => fn($q) => $q->orderBy('sort')])
            ->ordered()
            ->get();

        return response()->json(['filters' => $params]);
    }

    // ── Internal: products ────────────────────────────────────────────────────

    private function searchProducts(string $query, Request $request): array
    {
        $search = Product::search($query)
            ->query(fn($q) => $q->whereNull('parent_id')->where('is_active', true));

        if ($request->filled('price_min')) {
            $search->where('price', '>=', (float) $request->input('price_min'));
        }

        if ($request->filled('price_max')) {
            $search->where('price', '<=', (float) $request->input('price_max'));
        }

        $results = $search->take(self::MAX_PRODUCTS)->get();
        $results->load(['images', 'category']);

        return $results->map(fn(Product $p) => $this->formatProduct($p))->all();
    }

    private function formatProduct(Product $product): array
    {
        return [
            'id'        => $product->id,
            'title'     => $product->title,
            'slug'      => $product->slug,
            'article'   => $product->article,
            'price'     => $product->price,
            'old_price' => $product->old_price,
            'images'    => $product->images->map(fn($img) => ['url' => $img->url])->all(),
            'category'  => $product->category ? [
                'id'    => $product->category->id,
                'title' => $product->category->title,
                'slug'  => $product->category->slug,
            ] : null,
        ];
    }

    // ── Internal: categories ──────────────────────────────────────────────────

    private function searchCategories(string $query): array
    {
        return Category::search($query)
            ->take(self::MAX_CATEGORIES)
            ->get()
            ->map(fn(Category $c) => $this->formatCategory($c))
            ->all();
    }

    private function formatCategory(Category $category): array
    {
        return [
            'id'    => $category->id,
            'title' => $category->title,
            'slug'  => $category->slug,
            'image' => $category->image ? ImageService::url($category->image) : null,
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function buildSuggestions(array $products, array $categories): array
    {
        return array_values(array_slice(
            array_unique(array_merge(
                array_column($categories, 'title'),
                array_slice(array_unique(array_column($products, 'title')), 0, 5),
            )),
            0,
            self::MAX_SUGGESTIONS,
        ));
    }

    private function popularSuggestions(): array
    {
        // TODO: replace with real analytics
        return ['Кроссовки', 'Футболки', 'Куртки', 'Джинсы', 'Аксессуары', 'Новинки'];
    }
}