<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductCollection;

class ProductCollectionController extends Controller
{
    /**
     * GET /product-collections
     * Получить все активные коллекции с товарами для главной страницы
     */
    public function index()
    {
        $collections = ProductCollection::active()
            ->ordered()
            ->with([
                'items.product' => function ($query) {
                    $query->where('is_active', true)
                        ->with([
                            'images',
                            'brand',
                            'children.paramValues.param',
                            'children.paramValues.paramOption',
                            'children.optionValues.param',
                            'children.optionValues.paramOption',
                        ]);
                }
            ])
            ->get()
            ->map(function ($collection) {
                return [
                    'id' => $collection->id,
                    'name' => $collection->name,
                    'position' => $collection->position,
                    'products' => $collection->items
                        ->filter(fn($item) => $item->product !== null)
                        ->sortBy('position')
                        ->map(function ($item) {
                            $product = $item->product;
                            return [
                                'id' => $product->id,
                                'title' => $product->title,
                                'slug' => $product->slug,
                                'price' => $product->price,
                                'old_price' => $product->old_price,
                                'article' => $product->article,
                                'brand' => $product->brand ? [
                                    'id' => $product->brand->id,
                                    'name' => $product->brand->name,
                                ] : null,
                                'images' => $product->images->map(fn($img) => ['url' => $img->url])->toArray(),
                                'sizes' => $this->formatSizes($product),
                            ];
                        })
                        ->values()
                ];
            });

        return response()->json(['data' => $collections]);
    }

    private function formatSizes($product): array
    {
        $sizes = [];

        foreach ($product->children ?? [] as $child) {
            $sizeValue = null;

            foreach ($child->paramValues ?? [] as $paramValue) {
                if ($paramValue->param && $paramValue->param->is_size) {
                    $sizeValue = $paramValue->value_string
                        ?? $paramValue->value_text
                        ?? $paramValue->value_int
                        ?? $paramValue->value_float
                        ?? ($paramValue->paramOption?->value ?? null);
                    break;
                }
            }

            if (!$sizeValue) {
                foreach ($child->optionValues ?? [] as $optionValue) {
                    if ($optionValue->param && $optionValue->param->is_size && $optionValue->paramOption) {
                        $sizeValue = $optionValue->paramOption->value;
                        break;
                    }
                }
            }

            if ($sizeValue) {
                $sizes[] = [
                    'product_id' => $child->id,
                    'size' => $sizeValue,
                    'slug' => $child->slug,
                    'price' => $child->price,
                    'old_price' => $child->old_price,
                    'total_stock' => $child->total_stock,
                    'is_active' => $child->is_active,
                ];
            }
        }

        return $sizes;
    }
}
