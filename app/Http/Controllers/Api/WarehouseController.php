<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function index()
    {
        $warehouses = Warehouse::orderBy('created_at', 'desc')->get();
        return response()->json($warehouses);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'external_id' => 'nullable|string|unique:warehouses,external_id',
            'title' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        $warehouse = Warehouse::create($validated);
        return response()->json($warehouse, 201);
    }

    public function show(Warehouse $warehouse)
    {
        return response()->json($warehouse);
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $validated = $request->validate([
            'external_id' => 'nullable|string|unique:warehouses,external_id,' . $warehouse->id,
            'title' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        $warehouse->update($validated);
        return response()->json($warehouse);
    }

    public function destroy(Warehouse $warehouse)
    {
        $warehouse->delete();
        return response()->json(null, 204);
    }

    public function toggle(Warehouse $warehouse)
    {
        $warehouse->update(['is_active' => !$warehouse->is_active]);
        return response()->json($warehouse);
    }
}
