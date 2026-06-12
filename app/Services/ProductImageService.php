<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;

class ProductImageService
{
    public static function storeBatch(Product $product, array $data): void
    {
        if (empty($data['images'])) {
            return;
        }

        // Получаем максимальный sort_order среди существующих изображений
        $maxSortOrder = $product->images()->max('sort_order') ?? -1;

        foreach ($data['images'] as $image) {
            if (!$image instanceof UploadedFile) {
                continue;
            }

            $path = ImageService::uploadProductImage($image, $product->id);

            $maxSortOrder++;
            $product->images()->create([
                'path' => $path,
                'sort_order' => $maxSortOrder,
            ]);
        }
    }

    /**
     * Копирование изображений из другого товара
     */
    public static function copyFromProduct(Product $targetProduct, array $imageIds): void
    {
        if (empty($imageIds)) {
            return;
        }

        // Получаем максимальный sort_order среди существующих изображений
        $maxSortOrder = $targetProduct->images()->max('sort_order') ?? -1;

        // Получаем изображения для копирования
        $sourceImages = ProductImage::whereIn('id', $imageIds)->get();

        foreach ($sourceImages as $sourceImage) {
            // Копируем файл изображения
            $newPath = ImageService::copyProductImage($sourceImage->path, $targetProduct->id);

            if ($newPath) {
                $maxSortOrder++;
                $targetProduct->images()->create([
                    'path' => $newPath,
                    'sort_order' => $maxSortOrder,
                ]);
            }
        }
    }

    public static function destroy($image): void
    {
        ImageService::delete($image->path);
        $image->delete();
    }
}
