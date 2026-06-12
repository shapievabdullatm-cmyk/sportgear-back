<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        $query = Shop::query()->with('warehouses');

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('address', 'ilike', "%{$search}%")
                    ->orWhere('city', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%")
                    ->orWhere('external_id', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $shops = $query->orderBy('sort_order')->orderBy('id')->get();

        return response()->json($shops);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $data['slug']       = $this->resolveSlug($data['slug'] ?? null, $data['name']);
        $data['sort_order'] = $data['sort_order'] ?? ((int) Shop::max('sort_order') + 1);

        $warehouses = $this->extractWarehouses($data);

        $shop = Shop::create($data);

        if ($warehouses !== null) {
            $shop->warehouses()->sync($warehouses);
        }

        return response()->json($shop->load('warehouses'), 201);
    }

    public function show(Shop $shop)
    {
        return response()->json($shop->load('warehouses'));
    }

    public function update(Request $request, Shop $shop)
    {
        $data = $this->validateData($request, $shop->id);

        if (array_key_exists('slug', $data)) {
            $data['slug'] = $this->resolveSlug($data['slug'], $data['name'] ?? $shop->name, $shop->id);
        }

        $warehouses = $this->extractWarehouses($data);

        $shop->update($data);

        if ($warehouses !== null) {
            $shop->warehouses()->sync($warehouses);
        }

        return response()->json($shop->fresh()->load('warehouses'));
    }

    public function destroy(Shop $shop)
    {
        $shop->delete();
        return response()->json(null, 204);
    }

    public function toggle(Shop $shop)
    {
        $shop->update(['is_active' => !$shop->is_active]);
        return response()->json($shop->load('warehouses'));
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'items'              => 'required|array',
            'items.*.id'         => 'required|integer|exists:shops,id',
            'items.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($request->input('items') as $row) {
            Shop::where('id', $row['id'])->update(['sort_order' => $row['sort_order']]);
        }

        return response()->json(['message' => 'Порядок сохранён']);
    }

    // ── Helpers ────────────────────────────────────────────────────────

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'external_id'   => ['nullable', 'string', 'max:255', Rule::unique('shops', 'external_id')->ignore($ignoreId)],
            'slug'          => ['nullable', 'string', 'max:255', Rule::unique('shops', 'slug')->ignore($ignoreId)],
            'name'          => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string'],
            'address'       => ['nullable', 'string', 'max:500'],
            'city'          => ['nullable', 'string', 'max:255'],
            'latitude'      => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'     => ['nullable', 'numeric', 'between:-180,180'],
            'phone'         => ['nullable', 'string', 'max:50'],
            'email'         => ['nullable', 'string', 'email', 'max:255'],

            'working_hours'                  => ['nullable', 'array'],
            'working_hours.*.day'            => ['required_with:working_hours', 'integer', 'between:1,7'],
            'working_hours.*.is_open'        => ['required_with:working_hours', 'boolean'],
            'working_hours.*.open'           => ['nullable', 'string'],
            'working_hours.*.close'          => ['nullable', 'string'],
            'working_hours.*.break'          => ['nullable', 'array'],
            'working_hours.*.break.*.from'   => ['nullable', 'string'],
            'working_hours.*.break.*.to'     => ['nullable', 'string'],
            'working_hours.*.note'           => ['nullable', 'string', 'max:255'],

            'metadata'      => ['nullable', 'array'],
            'is_active'     => ['boolean'],
            'sort_order'    => ['nullable', 'integer', 'min:0'],

            'pickup_enabled'          => ['nullable', 'boolean'],
            'pickup_min_lead_minutes' => ['nullable', 'integer', 'min:0',  'max:10080'],
            'pickup_slot_minutes'     => ['nullable', 'integer', 'min:15', 'max:240'],
            'pickup_max_per_slot'     => ['nullable', 'integer', 'min:1',  'max:1000'],
            'pickup_advance_days'     => ['nullable', 'integer', 'min:1',  'max:30'],

            'warehouse_ids'   => ['nullable', 'array'],
            'warehouse_ids.*' => ['integer', 'exists:warehouses,id'],
        ]);
    }

    private function extractWarehouses(array &$data): ?array
    {
        if (!array_key_exists('warehouse_ids', $data)) {
            return null;
        }
        $ids = $data['warehouse_ids'] ?? [];
        unset($data['warehouse_ids']);

        $sync = [];
        foreach (array_values($ids) as $idx => $id) {
            $sync[(int) $id] = ['sort_order' => $idx, 'is_primary' => false];
        }
        return $sync;
    }

    private function resolveSlug(?string $slug, string $fallbackName, ?int $ignoreId = null): string
    {
        $base = $slug ? Str::slug($slug) : Str::slug($fallbackName);
        if ($base === '') {
            $base = 'shop';
        }

        $candidate = $base;
        $i = 2;
        while (
            Shop::where('slug', $candidate)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $candidate = $base . '-' . $i++;
        }
        return $candidate;
    }
}