<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductBarcode;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductBarcodeController extends Controller
{
    public function index(Product $product)
    {
        return $product->barcodes;
    }

    public function store(Request $request, Product $product)
    {
        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:50'],
            'type' => ['nullable', 'string', 'max:20'],
        ]);

        // Check if barcode already exists
        $existingBarcode = ProductBarcode::where('barcode', $validated['barcode'])->first();
        $isDuplicate = $existingBarcode !== null;

        $barcode = $product->barcodes()->create($validated);

        return response()->json([
            'id' => $barcode->id,
            'product_id' => $barcode->product_id,
            'barcode' => $barcode->barcode,
            'type' => $barcode->type,
            'is_duplicate' => $isDuplicate,
            'created_at' => $barcode->created_at,
            'updated_at' => $barcode->updated_at,
        ], 201);
    }

    public function update(Request $request, Product $product, ProductBarcode $barcode)
    {
        if ($barcode->product_id !== $product->id) {
            return response()->json(['message' => 'Barcode does not belong to this product'], 403);
        }

        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:50'],
            'type' => ['nullable', 'string', 'max:20'],
        ]);

        // Check if barcode already exists (excluding current barcode)
        $existingBarcode = ProductBarcode::where('barcode', $validated['barcode'])
            ->where('id', '!=', $barcode->id)
            ->first();
        $isDuplicate = $existingBarcode !== null;

        $barcode->update($validated);

        return response()->json([
            'id' => $barcode->id,
            'product_id' => $barcode->product_id,
            'barcode' => $barcode->barcode,
            'type' => $barcode->type,
            'is_duplicate' => $isDuplicate,
            'created_at' => $barcode->created_at,
            'updated_at' => $barcode->updated_at,
        ]);
    }

    public function destroy(Product $product, ProductBarcode $barcode)
    {
        if ($barcode->product_id !== $product->id) {
            return response()->json(['message' => 'Barcode does not belong to this product'], 403);
        }

        $barcode->delete();

        return response()->json(['message' => 'Barcode deleted'], 200);
    }
}
