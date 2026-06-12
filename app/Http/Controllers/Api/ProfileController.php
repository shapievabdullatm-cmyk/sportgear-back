<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProfileUpdateRequest;
use App\Mail\EmailChangeMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return response()->json($request->user());
    }

    public function update(ProfileUpdateRequest $request)
    {
        $data = $request->validated();
        $request->user()->update($data);
        return response()->json($request->user()->fresh());
    }

    // POST /api/profile/request-email-change — запросить смену email
    public function requestEmailChange(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email,' . $request->user()->id,
        ]);

        $token = Str::random(64);

        $request->user()->update([
            'pending_email'                  => $request->email,
            'email_verify_token'             => hash('sha256', $token),
            'email_verify_token_expires_at'  => now()->addMinutes(30),
        ]);

        Mail::to($request->email)->send(new EmailChangeMail($token, $request->email));

        return response()->json(['message' => 'Письмо с подтверждением отправлено на ' . $request->email]);
    }

    // POST /api/profile/confirm-email — подтвердить смену email по токену
    public function confirmEmail(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);
        $user = $request->user();
        $hashedToken = hash('sha256', $request->token);

        if (
            $user->email_verify_token !== $hashedToken ||
            !$user->email_verify_token_expires_at ||
            $user->email_verify_token_expires_at->isPast()
        ) {
            return response()->json(['message' => 'Ссылка недействительна или истекла'], 422);
        }

        $user->update([
            'email'                          => $user->pending_email,
            'pending_email'                  => null,
            'email_verify_token'             => null,
            'email_verify_token_expires_at'  => null,
        ]);

        return response()->json([
            'message' => 'Email успешно подтверждён',
            'user'    => $user->fresh(),
        ]);
    }

    // GET /api/profile/sessions — список активных сессий
    public function sessions(Request $request)
    {
        $tokens = $request->user()->tokens()->get()->map(function ($token) use ($request) {
            return [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at,
                'created_at' => $token->created_at,
                'is_current' => $token->id === $request->user()->currentAccessToken()->id,
            ];
        });

        return response()->json(['sessions' => $tokens]);
    }

    // DELETE /api/profile/sessions/{id} — удалить конкретную сессию
    public function revokeSession(Request $request, $tokenId)
    {
        $token = $request->user()->tokens()->find($tokenId);

        if (!$token) {
            return response()->json(['message' => 'Сессия не найдена'], 404);
        }

        // Запрещаем удалять текущую сессию через этот эндпоинт
        if ($token->id === $request->user()->currentAccessToken()->id) {
            return response()->json(['message' => 'Нельзя удалить текущую сессию. Используйте logout.'], 422);
        }

        $token->delete();

        return response()->json(['message' => 'Сессия удалена']);
    }

    // DELETE /api/profile/sessions — удалить все сессии кроме текущей
    public function revokeAllSessions(Request $request)
    {
        $currentTokenId = $request->user()->currentAccessToken()->id;

        $request->user()->tokens()
            ->where('id', '!=', $currentTokenId)
            ->delete();

        return response()->json(['message' => 'Все остальные сессии завершены']);
    }
}
