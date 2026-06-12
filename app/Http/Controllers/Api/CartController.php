<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    /**
     * Получить корзину текущего пользователя
     */
    public function index(Request $request)
    {
        $cart = $this->getOrCreateCart($request);

        return response()->json([
            'cart' => CartService::format($cart),
        ]);
    }

    /**
     * Добавить товар в корзину
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'integer|min:1',
        ]);

        $product = Product::findOrFail($request->product_id);

        // Проверка: нельзя добавлять родительский товар с детьми
        if ($product->children()->exists()) {
            return response()->json([
                'message' => 'Нельзя добавить товар с вариантами. Выберите конкретный вариант.',
            ], 422);
        }

        // Проверка цены
        if ($product->price === null || $product->price <= 0) {
            return response()->json([
                'message' => 'У товара не указана цена',
            ], 422);
        }

        // Проверка наличия
        if ($product->total_stock < 1) {
            return response()->json([
                'message' => 'Товар отсутствует на складе',
            ], 422);
        }

        $cart = $this->getOrCreateCart($request);
        $quantity = $request->quantity ?? 1;

        // Проверяем, есть ли уже этот товар в корзине
        $cartItem = $cart->items()->where('product_id', $product->id)->first();

        if ($cartItem) {
            // Проверяем, не превышает ли новое количество остаток
            $newQuantity = $cartItem->quantity + $quantity;
            if ($newQuantity > $product->total_stock) {
                return response()->json([
                    'message' => 'Недостаточно товара на складе',
                ], 422);
            }
            $cartItem->quantity = $newQuantity;
            $cartItem->save();
        } else {
            // Создаем новую позицию
            if ($quantity > $product->total_stock) {
                return response()->json([
                    'message' => 'Недостаточно товара на складе',
                ], 422);
            }

            $cart->items()->create([
                'product_id' => $product->id,
                'quantity' => $quantity,
                'price' => $product->price,
            ]);
        }

        return $this->index($request);
    }

    /**
     * Обновить количество товара в корзине
     */
    public function update(Request $request, $itemId)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = $this->getOrCreateCart($request);
        $cartItem = $cart->items()->findOrFail($itemId);

        // Проверяем наличие
        if ($request->quantity > $cartItem->product->total_stock) {
            return response()->json([
                'message' => 'Недостаточно товара на складе',
            ], 422);
        }

        $cartItem->quantity = $request->quantity;
        $cartItem->save();

        return $this->index($request);
    }

    /**
     * Удалить товар из корзины
     */
    public function destroy(Request $request, $itemId)
    {
        $cart = $this->getOrCreateCart($request);
        $cartItem = $cart->items()->findOrFail($itemId);
        $cartItem->delete();

        return $this->index($request);
    }

    /**
     * Очистить корзину
     */
    public function clear(Request $request)
    {
        $cart = $this->getOrCreateCart($request);
        $cart->items()->delete();

        return $this->index($request);
    }

    /**
     * Получить или создать корзину для текущего пользователя/сессии.
     *
     * Слияние гостевой корзины в пользовательскую теперь срабатывает
     * на событии логина (см. CartService::mergeGuestCartIntoUser), но здесь
     * остаётся defensive-вызов на случай старых сессий, где merge не прошёл.
     */
    private function getOrCreateCart(Request $request): Cart
    {
        $user = $request->user('sanctum');

        if ($user) {
            $sessionId = $request->hasSession() ? $request->session()->getId() : null;
            CartService::mergeGuestCartIntoUser($user, $sessionId);

            return Cart::firstOrCreate(
                ['user_id' => $user->id],
                ['session_id' => null]
            );
        }

        $sessionId = $request->session()->getId();
        return Cart::firstOrCreate(
            ['session_id' => $sessionId],
            ['user_id' => null]
        );
    }
}
