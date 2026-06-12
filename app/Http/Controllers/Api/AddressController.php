<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()
            ->addresses()
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($addresses);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->user()->addresses()->count() >= 100) {
            return response()->json(['message' => 'Достигнут лимит адресов (100)'], 422);
        }

        $validated = $request->validate([
            'title'        => 'required|string|max:100',
            'full_address' => 'required|string|max:500',
            'lat'          => 'nullable|numeric',
            'lon'          => 'nullable|numeric',
            'city'         => 'nullable|string|max:100',
            'street'       => 'nullable|string|max:200',
            'house'        => 'required|string|max:20',
            'apartment'    => 'nullable|string|max:20',
            'entrance'     => 'nullable|string|max:10',
            'floor'        => 'nullable|string|max:10',
            'intercom'     => 'nullable|string|max:20',
            'comment'      => 'nullable|string|max:500',
            'is_default'   => 'boolean',
        ]);

        if (!empty($validated['is_default'])) {
            $request->user()->addresses()->update(['is_default' => false]);
        }

        $address = $request->user()->addresses()->create($validated);

        return response()->json($address, 201);
    }

    public function update(Request $request, Address $address): JsonResponse
    {
        if ($address->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'title'        => 'required|string|max:100',
            'full_address' => 'required|string|max:500',
            'lat'          => 'nullable|numeric',
            'lon'          => 'nullable|numeric',
            'city'         => 'nullable|string|max:100',
            'street'       => 'nullable|string|max:200',
            'house'        => 'required|string|max:20',
            'apartment'    => 'nullable|string|max:20',
            'entrance'     => 'nullable|string|max:10',
            'floor'        => 'nullable|string|max:10',
            'intercom'     => 'nullable|string|max:20',
            'comment'      => 'nullable|string|max:500',
            'is_default'   => 'boolean',
        ]);

        if (!empty($validated['is_default'])) {
            $request->user()->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
        }

        $address->update($validated);

        return response()->json($address);
    }

    public function destroy(Request $request, Address $address): JsonResponse
    {
        if ($address->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $address->delete();

        return response()->json(null, 204);
    }

    public function setDefault(Request $request, Address $address): JsonResponse
    {
        if ($address->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->user()->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return response()->json($address);
    }
}