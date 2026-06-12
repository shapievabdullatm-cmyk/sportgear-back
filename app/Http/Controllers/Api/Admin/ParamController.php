<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\Param\ParamFilterTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\Param\StoreRequest;
use App\Http\Requests\Api\Admin\Param\UpdateRequest;
use App\Http\Resources\Param\ParamResource;
use App\Models\Param;
use App\Services\ParamService;

class ParamController extends Controller
{
    /** GET /admin/params */
    public function index(\Illuminate\Http\Request $request)
    {
        $query = Param::with('options')->ordered();

        // Поиск
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($q) use ($search) {
                // Используем translate для преобразования кириллицы и латиницы в нижний регистр
                $upper = 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $lower = 'абвгдеёжзийклмнопрстуфхцчшщъыьэюяabcdefghijklmnopqrstuvwxyz';

                $q->whereRaw("translate(title, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", ["%{$search}%"])
                  ->orWhereRaw("translate(slug, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", ["%{$search}%"]);
            });
        }

        // Пагинация
        $perPage = $request->integer('per_page', 20);
        $params = $query->paginate($perPage);

        return ParamResource::collection($params);
    }

    /** GET /admin/params/create */
    public function create()
    {
        return response()->json([
            'filterTypes' => ParamFilterTypeEnum::collection(),
        ]);
    }

    /** POST /admin/params */
    public function store(StoreRequest $request)
    {
        $data  = $request->validated();
        $param = ParamService::store($data);

        ParamService::syncOptions($param, $request->input('options', []));

        return ParamResource::make($param->fresh('options'));
    }

    /** GET /admin/params/{param}/edit */
    public function edit(Param $param)
    {
        $param->load('options');

        return response()->json([
            'param'       => ParamResource::make($param),
            'filterTypes' => ParamFilterTypeEnum::collection(),
        ]);
    }

    /** PATCH /admin/params/{param} */
    public function update(UpdateRequest $request, Param $param)
    {
        $data  = $request->validated();
        $param = ParamService::update($param, $data);

        ParamService::syncOptions($param, $request->input('options', []));

        return ParamResource::make($param->fresh('options'));
    }

    /** DELETE /admin/params/{param} */
    public function destroy(Param $param)
    {
        $param->delete();
        return response()->json(['message' => 'Атрибут удалён']);
    }

    /**
     * GET /admin/params/check-slug?slug=color&except_id=5
     * Быстрая проверка уникальности слага с фронта.
     */
    public function checkSlug(\Illuminate\Http\Request $request)
    {
        $slug     = ParamService::slugify($request->string('slug'));
        $exceptId = $request->integer('except_id') ?: null;

        $query = Param::where('slug', $slug);
        if ($exceptId) $query->where('id', '!=', $exceptId);

        return response()->json([
            'slug'      => $slug,
            'available' => !$query->exists(),
        ]);
    }
}
