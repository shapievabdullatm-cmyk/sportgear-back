<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandOrigin\BrandOriginResource;
use App\Models\BrandOrigin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BrandOriginController extends Controller
{
    public function index(Request $request)
    {
        $query = BrandOrigin::orderBy('name');

        // Поиск
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($q) use ($search) {
                // Используем translate для преобразования кириллицы и латиницы в нижний регистр
                $upper = 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $lower = 'абвгдеёжзийклмнопрстуфхцчшщъыьэюяabcdefghijklmnopqrstuvwxyz';

                $q->whereRaw("translate(name, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", ["%{$search}%"]);
            });
        }

        // Пагинация
        if ($request->boolean('paginate', false)) {
            $perPage = $request->integer('per_page', 20);
            $origins = $query->paginate($perPage);

            return BrandOriginResource::collection($origins);
        }

        // Для выпадающих списков возвращаем все
        $origins = $query->get();
        return BrandOriginResource::collection($origins);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'flag' => 'nullable|file|mimes:jpg,jpeg,png,svg|max:2048',
        ]);

        $flagUrl = null;
        if ($request->hasFile('flag')) {
            $flagUrl = $request->file('flag')->store('flags/brand-origins', 'public');
        }

        $origin = BrandOrigin::create([
            'name' => $validated['name'],
            'flag_url' => $flagUrl,
        ]);

        return BrandOriginResource::make($origin);
    }

    public function show(BrandOrigin $brandOrigin)
    {
        return BrandOriginResource::make($brandOrigin);
    }

    public function update(Request $request, BrandOrigin $brandOrigin)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'flag' => 'nullable|file|mimes:jpg,jpeg,png,svg|max:2048',
        ]);

        $data = ['name' => $validated['name']];

        if ($request->hasFile('flag')) {
            if ($brandOrigin->flag_url) {
                Storage::disk('public')->delete($brandOrigin->flag_url);
            }
            $data['flag_url'] = $request->file('flag')->store('flags/brand-origins', 'public');
        }

        $brandOrigin->update($data);

        return BrandOriginResource::make($brandOrigin);
    }

    public function destroy(BrandOrigin $brandOrigin)
    {
        if ($brandOrigin->flag_url) {
            Storage::disk('public')->delete($brandOrigin->flag_url);
        }

        $brandOrigin->delete();

        return response()->json(['message' => 'Brand origin deleted successfully']);
    }
}
