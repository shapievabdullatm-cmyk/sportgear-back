<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\Brand\StoreRequest;
use App\Http\Requests\Api\Admin\Brand\UpdateRequest;
use App\Http\Resources\Brand\BrandResource;
use App\Models\Brand;
use App\Services\BrandService;
use App\Services\ImageService;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        $query = Brand::orderBy('name');

        // Поиск
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($q) use ($search) {
                // Используем translate для преобразования кириллицы и латиницы в нижний регистр
                $upper = 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $lower = 'абвгдеёжзийклмнопрстуфхцчшщъыьэюяabcdefghijklmnopqrstuvwxyz';

                $q->whereRaw("translate(name, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", ["%{$search}%"])
                  ->orWhereRaw("translate(slug, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", ["%{$search}%"]);
            });
        }

        // Пагинация
        if ($request->boolean('paginate', false)) {
            $perPage = $request->integer('per_page', 20);
            $brands = $query->paginate($perPage);

            return BrandResource::collection($brands);
        }

        // Для выпадающих списков возвращаем все
        return BrandResource::collection($query->get());
    }

    public function store(StoreRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image');
        }

        return BrandResource::make(BrandService::store($data));
    }

    public function show(Brand $brand)
    {
        return BrandResource::make($brand);
    }

    public function update(UpdateRequest $request, Brand $brand)
    {
        $data = $request->validated();

        if ($request->boolean('remove_image')) {
            $data['image'] = null;
        } elseif ($request->hasFile('image')) {
            $data['image'] = $request->file('image');
        }

        return BrandResource::make(BrandService::update($brand, $data));
    }

    public function destroy(Brand $brand)
    {
        if ($brand->image) {
            ImageService::delete($brand->image);
        }

        $brand->delete();

        return response()->json(['message' => 'Бренд удалён']);
    }
}