<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ManufacturingCountry\ManufacturingCountryResource;
use App\Models\ManufacturingCountry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ManufacturingCountryController extends Controller
{
    public function index(Request $request)
    {
        $query = ManufacturingCountry::orderBy('name');

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
            $countries = $query->paginate($perPage);

            return ManufacturingCountryResource::collection($countries);
        }

        // Для выпадающих списков возвращаем все
        $countries = $query->get();
        return ManufacturingCountryResource::collection($countries);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'flag' => 'nullable|file|mimes:jpg,jpeg,png,svg|max:2048',
        ]);

        $flagUrl = null;
        if ($request->hasFile('flag')) {
            $flagUrl = $request->file('flag')->store('flags/manufacturing-countries', 'public');
        }

        $country = ManufacturingCountry::create([
            'name' => $validated['name'],
            'flag_url' => $flagUrl,
        ]);

        return ManufacturingCountryResource::make($country);
    }

    public function show(ManufacturingCountry $manufacturingCountry)
    {
        return ManufacturingCountryResource::make($manufacturingCountry);
    }

    public function update(Request $request, ManufacturingCountry $manufacturingCountry)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'flag' => 'nullable|file|mimes:jpg,jpeg,png,svg|max:2048',
        ]);

        $data = ['name' => $validated['name']];

        if ($request->hasFile('flag')) {
            if ($manufacturingCountry->flag_url) {
                Storage::disk('public')->delete($manufacturingCountry->flag_url);
            }
            $data['flag_url'] = $request->file('flag')->store('flags/manufacturing-countries', 'public');
        }

        $manufacturingCountry->update($data);

        return ManufacturingCountryResource::make($manufacturingCountry);
    }

    public function destroy(ManufacturingCountry $manufacturingCountry)
    {
        if ($manufacturingCountry->flag_url) {
            Storage::disk('public')->delete($manufacturingCountry->flag_url);
        }

        $manufacturingCountry->delete();

        return response()->json(['message' => 'Manufacturing country deleted successfully']);
    }
}
