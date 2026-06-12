<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Models\Product;
use App\Services\WishlistService;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    /**
     * Получить wishlist текущего пользователя
     */
    public function index(Request $request)
    {
        $wishlist = $this->getOrCreateWishlist($request);

        return response()->json([
            'wishlist' => WishlistService::format($wishlist),
        ]);
    }

    /**
     * Добавить товар в wishlist
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $product = Product::findOrFail($request->product_id);

        $wishlist = $this->getOrCreateWishlist($request);

        // Проверяем, есть ли уже этот товар в wishlist
        $existingItem = $wishlist->items()->where('product_id', $product->id)->first();

        if ($existingItem) {
            return response()->json([
                'message' => 'Товар уже в избранном',
            ], 422);
        }

        // Добавляем товар
        $wishlist->items()->create([
            'product_id' => $product->id,
        ]);

        return $this->index($request);
    }

    /**
     * Удалить товар из wishlist
     */
    public function destroy(Request $request, $itemId)
    {
        $wishlist = $this->getOrCreateWishlist($request);
        $item = $wishlist->items()->findOrFail($itemId);
        $item->delete();

        return $this->index($request);
    }

    /**
     * Удалить товар из wishlist по product_id
     */
    public function removeByProduct(Request $request, $productId)
    {
        $wishlist = $this->getOrCreateWishlist($request);
        $item = $wishlist->items()->where('product_id', $productId)->first();

        if ($item) {
            $item->delete();
        }

        return $this->index($request);
    }

    /**
     * Удалить все варианты размеров из wishlist по parent_id
     */
    public function removeByParent(Request $request, $parentId)
    {
        $wishlist = $this->getOrCreateWishlist($request);

        $wishlist->items()
            ->whereHas('product', fn($q) => $q->where('parent_id', $parentId))
            ->delete();

        return $this->index($request);
    }

    /**
     * Очистить wishlist
     */
    public function clear(Request $request)
    {
        $wishlist = $this->getOrCreateWishlist($request);
        $wishlist->items()->delete();

        return $this->index($request);
    }

    /**
     * Проверить, есть ли товар в wishlist
     */
    public function check(Request $request, $productId)
    {
        $wishlist = $this->getOrCreateWishlist($request);
        $exists = $wishlist->items()->where('product_id', $productId)->exists();

        return response()->json([
            'in_wishlist' => $exists,
        ]);
    }

    /**
     * Получить или создать wishlist для текущего пользователя/сессии.
     *
     * Слияние гостевого wishlist срабатывает на событии логина
     * (WishlistService::mergeGuestWishlistIntoUser), здесь — defensive fallback
     * на случай старых сессий, где merge не прошёл.
     */
    private function getOrCreateWishlist(Request $request): Wishlist
    {
        $user = $request->user('sanctum');

        if ($user) {
            $sessionId = $request->hasSession() ? $request->session()->getId() : null;
            WishlistService::mergeGuestWishlistIntoUser($user, $sessionId);

            return Wishlist::firstOrCreate(
                ['user_id' => $user->id],
                ['session_id' => null]
            );
        }

        $sessionId = $request->session()->getId();
        return Wishlist::firstOrCreate(
            ['session_id' => $sessionId],
            ['user_id' => null]
        );
    }
}