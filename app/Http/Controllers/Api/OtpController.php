<?php
namespace App\Http\Controllers\Api;

use App\Enums\Role\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Cart;
use App\Models\Wishlist;
use App\Services\CartService;
use App\Services\WishlistService;
use App\Services\SmsService;
use App\Services\CaptchaService;
use App\Support\PhoneNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class OtpController extends Controller
{
    public function __construct(
        protected SmsService $sms,
        protected CaptchaService $captcha
    ) {}

    public function sendCode(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|regex:/^\+?[0-9]{10,15}$/',
            'captcha_key' => 'required|string',
            'captcha_answer' => 'required|string',
        ]);

        // Проверяем капчу
        if (!$this->captcha->verify($request->captcha_key, $request->captcha_answer)) {
            return response()->json(['message' => 'Неверный ответ на капчу'], 422);
        }

        $phone = PhoneNormalizer::normalize($request->phone);
        if (!$phone) {
            return response()->json(['message' => 'Неверный формат телефона'], 422);
        }

        if (Cache::has("otp_lock:{$phone}")) {
            return response()->json(['message' => 'Подождите минуту'], 429);
        }

        $code = rand(1000, 9999);
        Cache::put("otp:{$phone}",      $code, now()->addMinutes(5));
        Cache::put("otp_lock:{$phone}", true,  now()->addMinute());

        $this->sms->send($phone, "Ваш код: {$code}");

        return response()->json([
            'message' => 'Код отправлен',
            ...(config('sms.debug') ? ['debug_code' => $code] : []),
        ]);
    }

    public function verifyCode(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'code'  => 'required|string|size:4',
        ]);

        $phone = PhoneNormalizer::normalize($request->phone);
        if (!$phone) {
            return response()->json(['message' => 'Неверный формат телефона'], 422);
        }

        $cached = Cache::get("otp:{$phone}");

        if (!$cached || (string) $cached !== $request->code) {
            return response()->json(['message' => 'Неверный или истёкший код'], 422);
        }

        Cache::forget("otp:{$phone}");
        Cache::forget("otp_lock:{$phone}");

        // Если пользователя нет — создаём нового
        $user = User::firstOrCreate(
            ['phone' => $phone],
            ['role' => 'user']
        );

        if ($user->roles()->count() === 0) {
            $user->assignRole(RoleEnum::CLIENT);
        }

        // Сливаем гостевую корзину и избранное в пользовательские.
        $sessionId       = $request->hasSession() ? $request->session()->getId() : null;
        $guestCartId     = $request->integer('guest_cart_id') ?: null;
        $guestWishlistId = $request->integer('guest_wishlist_id') ?: null;

        CartService::mergeGuestCartIntoUser($user, $sessionId, $guestCartId);
        WishlistService::mergeGuestWishlistIntoUser($user, $sessionId, $guestWishlistId);

        // Возвращаем готовые корзину и избранное — фронт ставит их в сторы синхронно.
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
            'token'    => $user->createToken('client-token')->plainTextToken,
            'cart'     => CartService::format($userCart),
            'wishlist' => WishlistService::format($userWishlist),
        ]);
    }
}
