<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Notifications\Notifier;
use App\Domain\Users\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PasswordResetController extends Controller
{
    /** Same anti-enumeration contract as the magic link: one response, always. */
    public function forgot(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'website' => ['prohibited'],
        ]);

        Password::broker()->sendResetLink([
            'email' => mb_strtolower(trim($request->string('email')->toString())),
        ]);

        return response()->json([
            'data' => [
                'sent' => true,
                'message' => 'If that email has an account, a reset link is on its way.',
            ],
        ], 202);
    }

    public function reset(ResetPasswordRequest $request, Notifier $notifier): JsonResponse
    {
        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use ($notifier): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                // Every other session belonged to whoever the user is resetting away
                // from. Killing them is the point of the reset.
                $user->devices()->update(['session_id' => null, 'revoked_at' => now()]);

                event(new PasswordReset($user));

                $notifier->send($user, 'user.password_changed', [
                    'changed_at' => now()->toDayDateTimeString(),
                ]);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return response()->json([
            'data' => ['reset' => true, 'message' => 'Your password has been reset. You can sign in now.'],
        ]);
    }
}
