<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SizeTable;
use App\Services\SizeTableService;
use Illuminate\Http\Request;

class SizeTableController extends Controller
{
    /** GET /admin/size-tables */
    public function index(Request $request)
    {
        $query = SizeTable::query()->orderBy('sort')->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where('name', 'LIKE', "%{$search}%");
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = $request->integer('per_page', 20);
        $sizeTables = $query->paginate($perPage);

        return response()->json($sizeTables);
    }

    /** GET /admin/size-tables/{id} */
    public function show(SizeTable $sizeTable)
    {
        return response()->json($sizeTable);
    }

    /** POST /admin/size-tables */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'headers' => 'nullable|array',
            'rows' => 'nullable|array',
            'sort' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $sizeTable = SizeTableService::store($validated);

        return response()->json($sizeTable, 201);
    }

    /** PATCH /admin/size-tables/{id} */
    public function update(Request $request, SizeTable $sizeTable)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'headers' => 'nullable|array',
            'rows' => 'nullable|array',
            'sort' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $sizeTable = SizeTableService::update($sizeTable, $validated);

        return response()->json($sizeTable);
    }

    /** DELETE /admin/size-tables/{id} */
    public function destroy(SizeTable $sizeTable)
    {
        SizeTableService::destroy($sizeTable);

        return response()->json(['message' => 'Size table deleted successfully']);
    }

    /** GET /admin/size-tables/list - для селектов */
    public function list()
    {
        $sizeTables = SizeTable::where('is_active', true)
            ->orderBy('sort')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($sizeTables);
    }
}