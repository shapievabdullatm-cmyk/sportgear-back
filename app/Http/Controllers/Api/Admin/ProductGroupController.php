<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Product\ProductResource;
use App\Models\Product;
use App\Models\ProductGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductGroupController extends Controller
{
    public function members(Product $product)
    {
        if ($product->product_group_id === null) {
            return ProductResource::collection(collect());
        }

        $members = Product::with('images')
            ->where('product_group_id', $product->product_group_id)
            ->orderBy('id')
            ->get();

        return ProductResource::collection($members);
    }

    public function merge(Request $request)
    {
        $data = $request->validate([
            'product_ids'   => 'required|array|min:2',
            'product_ids.*' => 'integer|distinct|exists:products,id',
        ]);

        $products = Product::with('images')
            ->whereIn('id', $data['product_ids'])
            ->get();

        if ($products->count() < 2) {
            throw ValidationException::withMessages([
                'product_ids' => 'Нужно минимум два товара для объединения.',
            ]);
        }

        $hasChild = $products->first(fn($p) => $p->parent_id !== null);
        if ($hasChild) {
            throw ValidationException::withMessages([
                'product_ids' => 'Нельзя объединять товары, у которых есть родительский товар.',
            ]);
        }

        $existingGroupIds = $products
            ->pluck('product_group_id')
            ->filter()
            ->unique()
            ->values();

        if ($existingGroupIds->count() > 1) {
            throw ValidationException::withMessages([
                'product_ids' => 'Эти товары уже состоят в разных группах. Сначала открепите один из них.',
            ]);
        }

        $groupId = DB::transaction(function () use ($products, $existingGroupIds) {
            $group = $existingGroupIds->count() === 1
                ? ProductGroup::lockForUpdate()->find($existingGroupIds->first())
                : ProductGroup::create(['title' => null]);

            Product::whereIn('id', $products->pluck('id'))
                ->update(['product_group_id' => $group->id]);

            return $group->id;
        });

        $members = Product::with('images')
            ->where('product_group_id', $groupId)
            ->orderBy('id')
            ->get();

        return ProductResource::collection($members);
    }

    public function detach(ProductGroup $productGroup, Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
        ]);

        $product = Product::find($data['product_id']);

        if ($product->product_group_id !== $productGroup->id) {
            throw ValidationException::withMessages([
                'product_id' => 'Товар не состоит в этой группе.',
            ]);
        }

        DB::transaction(function () use ($product, $productGroup) {
            $product->update(['product_group_id' => null]);

            $remaining = Product::where('product_group_id', $productGroup->id)->get();

            if ($remaining->count() <= 1) {
                Product::where('product_group_id', $productGroup->id)
                    ->update(['product_group_id' => null]);
                $productGroup->delete();
            }
        });

        $members = $productGroup->exists
            ? Product::with('images')
                ->where('product_group_id', $productGroup->id)
                ->orderBy('id')
                ->get()
            : collect();

        return ProductResource::collection($members);
    }
}