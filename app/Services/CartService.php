<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CartService
{
    /**
     * Сериализация корзины в формат, который ожидает фронт
     * (тот же что отдаёт CartController::index).
     */
    public static function format(Cart $cart): array
    {
        $cart->load([
            'items.product.images',
            'items.product.parent.images',
            'items.product.paramValues.param',
            'items.product.paramValues.paramOption',
            'items.product.optionValues.param',
            'items.product.optionValues.paramOption',
        ]);

        return [
            'id' => $cart->id,
            'items' => $cart->items->map(function ($item) {
                $product = $item->product;

                $size = null;
                if ($product->parent_id) {
                    foreach ($product->paramValues ?? [] as $paramValue) {
                        if ($paramValue->param && $paramValue->param->is_size) {
                            $size = $paramValue->value_string
                                ?? $paramValue->value_text
                                ?? $paramValue->value_int
                                ?? $paramValue->value_float
                                ?? ($paramValue->paramOption?->value ?? null);
                            break;
                        }
                    }

                    if ($size === null) {
                        foreach ($product->optionValues ?? [] as $optionValue) {
                            if ($optionValue->param && $optionValue->param->is_size && $optionValue->paramOption) {
                                $size = $optionValue->paramOption->value;
                                break;
                            }
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
                    'price'       => $item->price,
                    'quantity'    => $item->quantity,
                    'total'       => $item->total,
                    'image'       => $displayImage,
                    'size'        => $size,
                    'parent_id'   => $product->parent_id,
                    'total_stock' => $product->total_stock,
                ];
            }),
            'total_quantity' => $cart->total_quantity,
            'total_price'    => $cart->total_price,
        ];
    }


    /**
     * Переливает гостевую корзину в корзину аутентифицированного пользователя.
     * Идемпотентно.
     *
     * Гостевая корзина ищется так:
     *   1) по явному $guestCartId (если передан) — надёжно, не зависит от сессий;
     *   2) иначе по $sessionId — fallback, может не сработать в кросс-доменных
     *      сценариях, где cookie не доезжает между /cart и /auth.
     *
     * Возвращает количество перенесённых позиций.
     */
    public static function mergeGuestCartIntoUser(User $user, ?string $sessionId, ?int $guestCartId = null): int
    {
        if (!$sessionId && !$guestCartId) {
            return 0;
        }

        return DB::transaction(function () use ($user, $sessionId, $guestCartId) {
            $guestCart = null;

            if ($guestCartId) {
                // По явному id — но обязательно проверяем что это гостевая корзина
                $guestCart = Cart::with('items')
                    ->where('id', $guestCartId)
                    ->whereNull('user_id')
                    ->first();
            }

            if (!$guestCart && $sessionId) {
                $guestCart = Cart::with('items')
                    ->where('session_id', $sessionId)
                    ->whereNull('user_id')
                    ->first();
            }

            if (!$guestCart) {
                return 0;
            }

            $userCart = Cart::firstOrCreate(
                ['user_id' => $user->id],
                ['session_id' => null]
            );

            if ($guestCart->id === $userCart->id) {
                return 0;
            }

            $moved = 0;
            foreach ($guestCart->items as $guestItem) {
                $existingItem = $userCart->items()
                    ->where('product_id', $guestItem->product_id)
                    ->first();

                if ($existingItem) {
                    $existingItem->quantity += $guestItem->quantity;
                    $existingItem->save();
                } else {
                    $userCart->items()->create([
                        'product_id' => $guestItem->product_id,
                        'quantity'   => $guestItem->quantity,
                        'price'      => $guestItem->price,
                    ]);
                }
                $moved++;
            }

            $guestCart->delete();

            return $moved;
        });
    }
}