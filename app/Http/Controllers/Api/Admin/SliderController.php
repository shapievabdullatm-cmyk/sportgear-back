<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\Sliders\StoreRequest;
use App\Http\Requests\Api\Admin\Sliders\UpdateRequest;
use App\Http\Resources\Slider\SLiderResource;
use App\Models\Slider;
use App\Services\ImageService;
use App\Services\SliderService;
use Illuminate\Http\Request;

// app/Http/Controllers/Api/Admin/SliderController.php
class SliderController extends Controller
{
    public function index()
    {
        return SliderResource::collection(Slider::orderBy('position')->get());
    }

    public function store(StoreRequest $request)
    {
        $data          = $request->validated();
        $data['image'] = $request->file('image');

        if ($request->hasFile('mobile_image')) {
            $data['mobile_image'] = $request->file('mobile_image');
        }

        return SliderResource::make(SliderService::store($data));
    }

    public function update(UpdateRequest $request, Slider $slider)
    {
        $data = $request->validated();

        if ($request->boolean('remove_image')) {
            $data['image'] = null;
        } elseif ($request->hasFile('image')) {
            $data['image'] = $request->file('image');
        }

        if ($request->boolean('remove_mobile_image')) {
            $data['mobile_image'] = null;
        } elseif ($request->hasFile('mobile_image')) {
            $data['mobile_image'] = $request->file('mobile_image');
        }

        return SLiderResource::make(SliderService::update($slider, $data));
    }

    public function destroy(Slider $slider)
    {
        ImageService::delete($slider->image);
        ImageService::delete($slider->mobile_image);
        $slider->delete();
        return response()->json(['message' => 'Слайдер удалён']);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'items'            => 'required|array',
            'items.*.id'       => 'required|integer|exists:sliders,id',
            'items.*.position' => 'required|integer|min:0',
        ]);

        SliderService::reorder($request->input('items'));
        return response()->json(['message' => 'Порядок сохранён']);
    }
}
