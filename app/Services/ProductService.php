<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductParamValue;
use App\Models\ProductParamOptionValue;
use App\Enums\Param\ParamFilterTypeEnum;

class ProductService
{
    public static function store(array $data): Product
    {
        $product = Product::create($data);
        ProductImageService::storeBatch($product, $data);

        // Копируем изображения из другого товара, если указаны
        if (!empty($data['copy_image_ids']) && is_array($data['copy_image_ids'])) {
            ProductImageService::copyFromProduct($product, $data['copy_image_ids']);
        }

        if (!empty($data['params']) && is_array($data['params'])) {
            self::storeParams($product, $data['params']);
        }
        if (!empty($data['barcodes']) && is_array($data['barcodes'])) {
            self::storeBarcodes($product, $data['barcodes']);
        }
        if (isset($data['bought_together_ids']) && is_array($data['bought_together_ids'])) {
            self::syncBoughtTogetherProducts($product, $data['bought_together_ids']);
        }

        // Re-index after all relations are saved (params, barcodes use bulk ops
        // that bypass Eloquent events, so we need an explicit sync here).
        $product->load(['barcodes', 'category', 'paramValues.param', 'paramValues.paramOption', 'optionValues.param', 'optionValues.paramOption']);
        $product->searchable();

        return $product->fresh();
    }

    public static function update(Product $product, array $data): Product
    {
        $product->update($data);
        ProductImageService::storeBatch($product, $data);

        // Копируем изображения из другого товара, если указаны
        if (!empty($data['copy_image_ids']) && is_array($data['copy_image_ids'])) {
            ProductImageService::copyFromProduct($product, $data['copy_image_ids']);
        }

        if (!empty($data['params']) && is_array($data['params'])) {
            self::storeParams($product, $data['params']);
        }
        if (isset($data['barcodes']) && is_array($data['barcodes'])) {
            self::storeBarcodes($product, $data['barcodes']);
        }
        if (isset($data['bought_together_ids']) && is_array($data['bought_together_ids'])) {
            self::syncBoughtTogetherProducts($product, $data['bought_together_ids']);
        }

        $product->load(['barcodes', 'category', 'paramValues.param', 'paramValues.paramOption', 'optionValues.param', 'optionValues.paramOption']);
        $product->searchable();

        return $product->fresh(['images']);
    }

    /**
     * Сохранение значений параметров продукта.
     * params: массив объектов вида
     * { param_id, filter_type, multiple, value | option_id | option_ids[] }
     */
    protected static function storeParams(Product $product, array $params): void
    {
        foreach ($params as $p) {
            $paramId = (int)($p['param_id'] ?? 0);
            if ($paramId <= 0) continue;

            $type     = (int)($p['filter_type'] ?? 0);
            $multiple = (bool)($p['multiple'] ?? false);

            // Если multiple=true, но передан option_id (а не option_ids), значит это is_size=true
            // В этом случае обрабатываем как одиночный выбор
            $isSingleChoice = !$multiple || (isset($p['option_id']) && !isset($p['option_ids']));

            // MULTISELECT с множественным выбором (is_size=false)
            if ($multiple && !$isSingleChoice) {
                $product->optionValues()->where('param_id', $paramId)->delete();

                $ids = $p['option_ids'] ?? [];
                if (is_array($ids) && !empty($ids)) {
                    $now  = now();
                    $bulk = [];
                    foreach ($ids as $optId) {
                        $bulk[] = [
                            'product_id'      => $product->id,
                            'param_id'        => $paramId,
                            'param_option_id' => (int)$optId,
                            'created_at'      => $now,
                            'updated_at'      => $now,
                        ];
                    }
                    ProductParamOptionValue::insert($bulk);
                }

                // сбрасываем одиночное значение, если было
                $product->paramValues()->where('param_id', $paramId)->update([
                    'param_option_id' => null,
                    'value_string'    => null,
                    'value_text'      => null,
                    'value_int'       => null,
                    'value_float'     => null,
                ]);
                continue;
            }

            // Одиночные типы (включая MULTISELECT с is_size=true)
            $payload = [
                'param_option_id' => null,
                'value_string'    => null,
                'value_text'      => null,
                'value_int'       => null,
                'value_float'     => null,
            ];

            switch ($type) {
                case ParamFilterTypeEnum::STRING->value:
                    $payload['value_string'] = (string)($p['value'] ?? '');
                    break;

                case ParamFilterTypeEnum::TEXT->value:
                    $payload['value_text'] = (string)($p['value'] ?? '');
                    break;

                case ParamFilterTypeEnum::INTEGER->value:
                    $payload['value_int'] = isset($p['value']) ? (int)$p['value'] : null;
                    break;

                case ParamFilterTypeEnum::FLOAT->value:
                    $payload['value_float'] = isset($p['value']) ? (float)$p['value'] : null;
                    break;

                case ParamFilterTypeEnum::BOOLEAN->value:
                    $payload['value_int'] = isset($p['value']) ? (int)(bool)$p['value'] : null;
                    break;

                case ParamFilterTypeEnum::COLOR->value:
                case ParamFilterTypeEnum::MULTISELECT->value:
                    // одиночный выбор опции (цвет или размер с is_size=true)
                    $payload['param_option_id'] = isset($p['option_id']) ? (int)$p['option_id'] : null;
                    $product->optionValues()->where('param_id', $paramId)->delete();
                    break;
            }

            /** @var ProductParamValue $row */
            $row = $product->paramValues()->firstOrNew(['param_id' => $paramId]);
            $row->fill($payload + [
                    'product_id' => $product->id,
                    'param_id'   => $paramId,
                ]);
            $row->save();
        }
    }

    /**
     * Сохранение штрихкодов продукта.
     * barcodes: массив объектов вида { barcode, type }
     */
    protected static function storeBarcodes(Product $product, array $barcodes): void
    {
        // Удаляем все существующие штрихкоды
        $product->barcodes()->delete();

        // Добавляем новые
        foreach ($barcodes as $barcodeData) {
            if (empty($barcodeData['barcode'])) continue;

            $product->barcodes()->create([
                'barcode' => $barcodeData['barcode'],
                'type' => $barcodeData['type'] ?? 'ean-13',
            ]);
        }
    }

    /**
     * Синхронизация товаров "С этим покупают".
     * productIds: массив ID товаров
     */
    protected static function syncBoughtTogetherProducts(Product $product, array $productIds): void
    {
        // Подготовить данные с sort
        $syncData = [];
        foreach ($productIds as $index => $productId) {
            $syncData[$productId] = ['sort' => $index];
        }

        $product->boughtTogetherProducts()->sync($syncData);
    }
}
