<?php

namespace App\Services;

use App\Models\SizeTable;
use Illuminate\Support\Facades\DB;

class SizeTableService
{
    public static function store(array $data): SizeTable
    {
        return SizeTable::create([
            'name' => $data['name'],
            'headers' => $data['headers'] ?? [],
            'rows' => $data['rows'] ?? [],
            'sort' => $data['sort'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public static function update(SizeTable $sizeTable, array $data): SizeTable
    {
        $sizeTable->update([
            'name' => $data['name'] ?? $sizeTable->name,
            'headers' => $data['headers'] ?? $sizeTable->headers,
            'rows' => $data['rows'] ?? $sizeTable->rows,
            'sort' => $data['sort'] ?? $sizeTable->sort,
            'is_active' => $data['is_active'] ?? $sizeTable->is_active,
        ]);

        return $sizeTable->fresh();
    }

    public static function destroy(SizeTable $sizeTable): void
    {
        DB::transaction(function () use ($sizeTable) {
            $sizeTable->products()->update(['size_table_id' => null]);
            $sizeTable->delete();
        });
    }
}