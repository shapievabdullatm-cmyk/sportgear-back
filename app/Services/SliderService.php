<?php

namespace App\Services;

use App\Models\Slider;
use Illuminate\Http\UploadedFile; // ← ГЛАВНЫЙ БАГ: без этого instanceof всегда false

class SliderService
{
    public static function store(array $data): Slider
    {
        $image       = $data['image'] ?? null;
        $mobileImage = $data['mobile_image'] ?? null;
        unset($data['image'], $data['mobile_image']);

        if ($image instanceof UploadedFile) {
            $data['image'] = ImageService::uploadSliderImage($image);
        }

        if ($mobileImage instanceof UploadedFile) {
            $data['mobile_image'] = ImageService::uploadSliderMobileImage($mobileImage);
        }

        return Slider::create($data);
    }

    public static function update(Slider $slider, array $data): Slider
    {
        $image                  = $data['image'] ?? null;
        $hasExplicitImage       = array_key_exists('image', $data);
        $mobileImage            = $data['mobile_image'] ?? null;
        $hasExplicitMobileImage = array_key_exists('mobile_image', $data);
        unset($data['image'], $data['mobile_image'], $data['remove_image'], $data['remove_mobile_image']);

        if ($image instanceof UploadedFile) {
            ImageService::delete($slider->image);
            $data['image'] = ImageService::uploadSliderImage($image);
        } elseif ($hasExplicitImage && is_null($image)) {
            ImageService::delete($slider->image);
            $data['image'] = null;
        }

        if ($mobileImage instanceof UploadedFile) {
            ImageService::delete($slider->mobile_image);
            $data['mobile_image'] = ImageService::uploadSliderMobileImage($mobileImage);
        } elseif ($hasExplicitMobileImage && is_null($mobileImage)) {
            ImageService::delete($slider->mobile_image);
            $data['mobile_image'] = null;
        }

        $slider->update($data);
        return $slider->fresh();
    }

    public static function reorder(array $items): void
    {
        foreach ($items as $item) {
            Slider::where('id', $item['id'])->update(['position' => $item['position']]);
        }
    }
}
