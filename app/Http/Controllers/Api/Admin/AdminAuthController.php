<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::with('roles')
            ->where('email', $request->email)
            ->whereHas('roles', fn($q) => $q->where('title', 'admin'))
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Неверные данные.'],
            ]);
        }

        // Не удаляем старые токены - разрешаем множественные сессии
        // $user->tokens()->delete();

        return response()->json([
            'user'  => $user,
            'token' => $user->createToken('admin-token')->plainTextToken,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Вышли успешно']);
    }
}
