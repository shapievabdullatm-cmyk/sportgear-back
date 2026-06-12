<?php

namespace App\Services;

use App\Models\Brand;
use Illuminate\Http\UploadedFile;

class BrandService
{
    public static function store(array $data): Brand
    {
        if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
            $data['image'] = ImageService::uploadBrandImage($data['image']);
        }

        return Brand::create($data);
    }

    public static function update(Brand $brand, array $data): Brand
    {
        // Удаление изображения
        if (isset($data['image']) && $data['image'] === null && $brand->image) {
            ImageService::delete($brand->image);
        }

        // Загрузка нового изображения
        if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
            if ($brand->image) {
                ImageService::delete($brand->image);
            }
            $data['image'] = ImageService::uploadBrandImage($data['image']);
        }

        $brand->update($data);

        return $brand;
    }
}