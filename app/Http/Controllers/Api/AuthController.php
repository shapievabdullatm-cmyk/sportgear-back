<?php

// app/Http/Controllers/Api/AuthController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\CartService;
use App\Services\WishlistService;
use App\Services\SmsService;
use App\Support\PhoneNormalizer;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function sendCode(Request $request, SmsService $sms)
    {
        $request->validate(['phone' => 'required|string']);
        $phone = PhoneNormalizer::normalize($request->phone);
        if (!$phone) {
            return response()->json(['message' => 'Неверный формат телефона'], 422);
        }
        $code = rand(1000, 9999);

        // Находим или создаем пользователя
        $user = User::firstOrCreate(
            ['phone' => $phone],
            ['role' => 'customer']
        );

        $user->update([
            'otp_code' => $code,
            'otp_expires_at' => now()->addMinutes(10)
        ]);

        $sms->send($phone, "Ваш код: $code");

        return response()->json([
            'status' => 'success',
            'debug_code' => config('sms.debug') ? $code : null
        ]);
    }

    public function verifyCode(Request $request)
    {
        $request->validate(['phone' => 'required', 'code' => 'required']);

        $phone = PhoneNormalizer::normalize($request->phone);
        if (!$phone) {
            return response()->json(['message' => 'Неверный формат телефона'], 422);
        }

        $user = User::where('phone', $phone)
            ->where('otp_code', $request->code)
            ->where('otp_expires_at', '>', now())
            ->first();

        if (!$user) return response()->json(['message' => 'Код неверный'], 422);

        $user->update(['otp_code' => null]);
        $token = $user->createToken('auth_token')->plainTextToken;

        // Сливаем гостевую корзину и избранное.
        $sessionId       = $request->hasSession() ? $request->session()->getId() : null;
        $guestCartId     = $request->integer('guest_cart_id') ?: null;
        $guestWishlistId = $request->integer('guest_wishlist_id') ?: null;

        CartService::mergeGuestCartIntoUser($user, $sessionId, $guestCartId);
        WishlistService::mergeGuestWishlistIntoUser($user, $sessionId, $guestWishlistId);

        $userCart = Cart::firstOrCreate(
            ['user_id' => $user->id],
            ['session_id' => null]
        );
        $userWishlist = Wishlist::firstOrCreate(
            ['user_id' => $user->id],
            ['session_id' => null]
        );

        return response()->json([
            'user'     => $user,
            'token'    => $token,
            'cart'     => CartService::format($userCart),
            'wishlist' => WishlistService::format($userWishlist),
        ]);
    }

    public function me(Request $request) {
        return $request->user();
    }
}
