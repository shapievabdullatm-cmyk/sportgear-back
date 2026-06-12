<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Product\ProductResource;
use App\Models\Product;
use App\Models\ProductBarcode;

class ProductController extends Controller
{
    /**
     * GET /products/by-barcode/{barcode}
     * Найти товар по штрихкоду. Возвращает slug для редиректа на страницу товара.
     * Если товар — вариант (имеет parent_id), возвращает slug родителя.
     */
    public function findByBarcode(string $barcode)
    {
        $code = trim($barcode);

        if ($code === '') {
            return response()->json(['message' => 'Пустой штрихкод'], 422);
        }

        $record = ProductBarcode::where('barcode', $code)
            ->with('product:id,slug,parent_id,is_active,title')
            ->first();

        $product = $record?->product;

        if (!$product || !$product->is_active) {
            return response()->json(['message' => 'Товар по этому штрихкоду не найден'], 404);
        }

        $slug = $product->parent_id
            ? Product::where('id', $product->parent_id)->value('slug')
            : $product->slug;

        if (!$slug) {
            return response()->json(['message' => 'Товар по этому штрихкоду не найден'], 404);
        }

        return response()->json([
            'slug' => $slug,
            'product_id' => $product->id,
            'title' => $product->title,
        ]);
    }

    /**
     * GET /products/{slug}
     * Получить товар по slug для публичной страницы
     */
    public function show(string $slug)
    {
        $product = Product::where('slug', $slug)
            ->where('is_active', true)
            ->with([
                'images',
                'category.parent.parent',
                'productGroup',
                'brand',
                'brandOrigin',
                'manufacturingCountry',
                'paramValues.param',
                'paramValues.paramOption',
                'optionValues.param',
                'optionValues.paramOption',
                'children.images',
                'children.barcodes',
                'children.paramValues.param',
                'children.paramValues.paramOption',
                'children.optionValues.param',
                'children.optionValues.paramOption',
                'parent.images',
                'barcodes',
                'stocks.warehouse',
                'boughtTogetherProducts.images'
            ])
            ->firstOrFail();

        // Если это дочерний товар, загружаем всех детей родителя
        if ($product->parent_id) {
            $product->load('parent.children.images');
        }

        // Формируем хлебные крошки
        $breadcrumbs = [];
        if ($product->category) {
            $current = $product->category;
            while ($current) {
                array_unshift($breadcrumbs, [
                    'id' => $current->id,
                    'title' => $current->title,
                    'slug' => $current->slug,
                ]);
                $current = $current->parent;
            }
        }

        // Товары из той же группы (варианты товара)
        $relatedProducts = [];
        if ($product->product_group_id) {
            $relatedProducts = Product::where('product_group_id', $product->product_group_id)
                ->where('is_active', true)
                ->whereNull('parent_id')
                ->with([
                    'images',
                    'paramValues.param',
                    'paramValues.paramOption',
                    'optionValues.param',
                    'optionValues.paramOption',
                ])
                ->orderBy('id')
                ->get();
        }

        // Похожие товары из той же категории (случайные 10)
        $similarProducts = [];
        if ($product->category_id) {
            $similarProducts = Product::where('category_id', $product->category_id)
                ->where('id', '!=', $product->id)
                ->where('is_active', true)
                ->whereNull('parent_id')
                ->with(['images'])
                ->inRandomOrder()
                ->limit(10)
                ->get();
        }

        // Товары "С этим покупают"
        $boughtTogetherProducts = $product->boughtTogetherProducts()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->with(['images'])
            ->get();

        return response()->json([
            'product' => ProductResource::make($product),
            'breadcrumbs' => $breadcrumbs,
            'related_products' => ProductResource::collection($relatedProducts),
            'similar_products' => ProductResource::collection($similarProducts),
            'bought_together_products' => ProductResource::collection($boughtTogetherProducts),
        ]);
    }
}