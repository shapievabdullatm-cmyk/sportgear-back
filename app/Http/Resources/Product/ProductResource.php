<?php

namespace App\Http\Resources\Product;

use App\Enums\Param\ParamFilterTypeEnum;
use App\Http\Resources\Image\ProductImageResource;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'external_id'      => $this->external_id,
            'name'             => $this->title, // Алиас для фронтенда
            'title'            => $this->title,
            'external_title'   => $this->external_title,
            'slug'             => $this->slug,
            'article'          => $this->article,
            'description'      => $this->description,
            'price'            => $this->price,
            'old_price'        => $this->old_price,
            'meta_title'       => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_keywords'    => $this->meta_keywords,
            'width'            => $this->width,
            'height'           => $this->height,
            'length'           => $this->length,
            'weight'           => $this->weight,
            'is_active'               => (bool) $this->is_active,
            'parent_id'               => $this->parent_id,
            'category_id'             => $this->category_id,
            'product_group_id'        => $this->product_group_id,
            'brand_id'                => $this->brand_id,
            'brand_origin_id'         => $this->brand_origin_id,
            'manufacturing_country_id' => $this->manufacturing_country_id,
            'size_table_id'           => $this->size_table_id,
            'images'                  => ProductImageResource::collection($this->images)->resolve(),
            'barcodes'         => $this->whenLoaded('barcodes'),
            'params'           => $this->formatParams(),
            'raw_params'       => $this->formatRawParams(), // Для копирования
            'total_stock'      => $this->total_stock,
            'stocks'           => $this->whenLoaded('stocks', function() {
                return $this->stocks->map(function($stock) {
                    return [
                        'warehouse_id' => $stock->warehouse_id,
                        'warehouse_title' => $stock->warehouse->title ?? null,
                        'quantity' => $stock->quantity,
                        'reserved_quantity' => $stock->reserved_quantity,
                        'available_quantity' => $stock->available_quantity,
                    ];
                });
            }),
            'children'         => $this->whenLoaded('children', function() {
                return ProductResource::collection($this->children);
            }),
            'sizes'            => $this->formatSizes(),
            'bought_together_products' => ProductResource::collection($this->whenLoaded('boughtTogetherProducts')),
        ];
    }

    protected function formatParams(): array
    {
        if (!$this->relationLoaded('paramValues') && !$this->relationLoaded('optionValues')) {
            return [];
        }

        $params = [];

        // Обрабатываем paramValues (одиночные значения)
        foreach ($this->paramValues ?? [] as $paramValue) {
            if (!$paramValue->param) continue;

            $param = $paramValue->param;
            $value = $paramValue->value_string
                ?? $paramValue->value_text
                ?? $paramValue->value_int
                ?? $paramValue->value_float
                ?? ($paramValue->paramOption?->value ?? null);

            // Для цветов передаем сырое значение (JSON), фронт сам распарсит
            $isColor = $param->filter_type === ParamFilterTypeEnum::COLOR->value;

            $params[] = [
                'title' => $param->title,
                'value' => $value,
                'unit'  => $param->unit,
                'is_color' => $isColor,
            ];
        }

        // Обрабатываем optionValues (множественные значения)
        $groupedOptions = [];
        foreach ($this->optionValues ?? [] as $optionValue) {
            if (!$optionValue->param || !$optionValue->paramOption) continue;

            $paramId = $optionValue->param_id;
            if (!isset($groupedOptions[$paramId])) {
                $groupedOptions[$paramId] = [
                    'title' => $optionValue->param->title,
                    'values' => [],
                    'unit' => $optionValue->param->unit,
                    'is_color' => $optionValue->param->filter_type === ParamFilterTypeEnum::COLOR->value,
                ];
            }
            $groupedOptions[$paramId]['values'][] = $optionValue->paramOption->value;
        }

        foreach ($groupedOptions as $grouped) {
            $params[] = [
                'title' => $grouped['title'],
                'value' => $grouped['values'], // Для множественных значений передаем массив
                'unit'  => $grouped['unit'],
                'is_color' => $grouped['is_color'],
            ];
        }

        return $params;
    }

    protected function formatRawParams(): array
    {
        if (!$this->relationLoaded('paramValues') && !$this->relationLoaded('optionValues')) {
            return [];
        }

        $params = [];

        // Обрабатываем paramValues (одиночные значения)
        foreach ($this->paramValues ?? [] as $paramValue) {
            if (!$paramValue->param) continue;

            $param = $paramValue->param;
            $value = $paramValue->value_string
                ?? $paramValue->value_text
                ?? $paramValue->value_int
                ?? $paramValue->value_float
                ?? null;

            $params[] = [
                'param_id' => $param->id,
                'value' => $value,
                'option_id' => $paramValue->param_option_id,
            ];
        }

        // Обрабатываем optionValues (множественные значения)
        $groupedOptions = [];
        foreach ($this->optionValues ?? [] as $optionValue) {
            if (!$optionValue->param || !$optionValue->paramOption) continue;

            $paramId = $optionValue->param_id;
            if (!isset($groupedOptions[$paramId])) {
                $groupedOptions[$paramId] = [
                    'param_id' => $paramId,
                    'option_ids' => [],
                ];
            }
            $groupedOptions[$paramId]['option_ids'][] = $optionValue->param_option_id;
        }

        foreach ($groupedOptions as $grouped) {
            $params[] = $grouped;
        }

        return $params;
    }

    protected function formatSizes(): array
    {
        // Возвращаем размеры только для родительских товаров
        if ($this->parent_id !== null) {
            return [];
        }

        if (!$this->relationLoaded('children')) {
            return [];
        }

        $sizes = [];

        foreach ($this->children as $child) {
            // Ищем параметр размера (is_size = true)
            $sizeValue = null;
            $sizeParamId = null;

            // Проверяем paramValues
            foreach ($child->paramValues ?? [] as $paramValue) {
                if ($paramValue->param && $paramValue->param->is_size) {
                    $sizeValue = $paramValue->value_string
                        ?? $paramValue->value_text
                        ?? $paramValue->value_int
                        ?? $paramValue->value_float
                        ?? ($paramValue->paramOption?->value ?? null);
                    $sizeParamId = $paramValue->param_id;
                    break;
                }
            }

            // Проверяем optionValues (если размер задан через опции)
            if (!$sizeValue) {
                foreach ($child->optionValues ?? [] as $optionValue) {
                    if ($optionValue->param && $optionValue->param->is_size && $optionValue->paramOption) {
                        $sizeValue = $optionValue->paramOption->value;
                        $sizeParamId = $optionValue->param_id;
                        break;
                    }
                }
            }

            // Если нашли размер, добавляем в список
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
