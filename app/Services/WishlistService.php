<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Support\Facades\DB;

class WishlistService
{
    /**
     * Сериализация избранного в формат, который ожидает фронт
     * (тот же что отдаёт WishlistController::index).
     */
    public static function format(Wishlist $wishlist): array
    {
        $wishlist->load(['items.product.images', 'items.product.parent.images']);

        return [
            'id'    => $wishlist->id,
            'items' => $wishlist->items->map(function ($item) {
                $product = $item->product;

                $size = null;
                if ($product->parent_id) {
                    foreach ($product->paramValues ?? [] as $paramValue) {
                        if ($paramValue->param && $paramValue->param->is_size) {
                            $size = $paramValue->value_string
                                ?? $paramValue->value_int
                                ?? $paramValue->value_float
                                ?? ($paramValue->paramOption?->value ?? null);
                            break;
                        }
                    }
                }

                $displayTitle = $product->title;
                $displaySlug  = $product->slug;
                $displayImage = $product->images->first()?->url ?? null;

                if ($product->parent_id && $product->parent) {
                    $displayTitle = $product->parent->title;
                    $displaySlug  = $product->parent->slug;
                    $displayImage = $product->parent->images->first()?->url ?? null;
                }

                return [
                    'id'          => $item->id,
                    'product_id'  => $product->id,
                    'title'       => $displayTitle,
                    'slug'        => $displaySlug,
                    'price'       => $product->price,
                    'old_price'   => $product->old_price,
                    'image'       => $displayImage,
                    'size'        => $size,
                    'parent_id'   => $product->parent_id,
                    'total_stock' => $product->total_stock,
                    'is_active'   => $product->is_active,
                ];
            }),
            'total_items' => $wishlist->total_items,
        ];
    }

    /**
     * Сливает гостевое избранное в избранное пользователя.
     * Идемпотентно.
     *
     * Гостевой wishlist ищется так:
     *   1) по явному $guestWishlistId (если передан) — с проверкой user_id IS NULL;
     *   2) иначе по $sessionId.
     *
     * Возвращает количество перенесённых уникальных позиций.
     */
    public static function mergeGuestWishlistIntoUser(User $user, ?string $sessionId, ?int $guestWishlistId = null): int
    {
        if (!$sessionId && !$guestWishlistId) {
            return 0;
        }

        return DB::transaction(function () use ($user, $sessionId, $guestWishlistId) {
            $guestWishlist = null;

            if ($guestWishlistId) {
                $guestWishlist = Wishlist::with('items')
                    ->where('id', $guestWishlistId)
                    ->whereNull('user_id')
                    ->first();
            }

            if (!$guestWishlist && $sessionId) {
                $guestWishlist = Wishlist::with('items')
                    ->where('session_id', $sessionId)
                    ->whereNull('user_id')
                    ->first();
            }

            if (!$guestWishlist) {
                return 0;
            }

            $userWishlist = Wishlist::firstOrCreate(
                ['user_id' => $user->id],
                ['session_id' => null]
            );

            if ($guestWishlist->id === $userWishlist->id) {
                return 0;
            }

            $existingProductIds = $userWishlist->items()->pluck('product_id')->all();

            $moved = 0;
            foreach ($guestWishlist->items as $guestItem) {
                if (in_array($guestItem->product_id, $existingProductIds, true)) {
                    continue;
                }
                $userWishlist->items()->create([
                    'product_id' => $guestItem->product_id,
                ]);
                $moved++;
            }

            $guestWishlist->delete();

            return $moved;
        });
    }
}