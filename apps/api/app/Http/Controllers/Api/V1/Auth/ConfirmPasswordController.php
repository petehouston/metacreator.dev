<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Re-authentication for sensitive actions.
 *
 * Routes behind `password.confirm` answer 423 when the last confirmation is older
 * than `auth.password_timeout`; the client posts here and retries.
 */
final class ConfirmPasswordController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        // Passwordless accounts (magic link, Google) have nothing to confirm against.
        // A fresh magic link is the equivalent proof, so point them at it rather than
        // failing them into a dead end.
        if ($user->password === null) {
            return response()->json([
                'error' => [
                    'code' => 'auth.password_not_set',
                    'message' => 'Set a password first, or confirm with a fresh sign-in link.',
                    'status' => 409,
                ],
            ], 409);
        }

        $request->validate(['password' => ['required', 'string']]);

        if (! Auth::guard('web')->validate([
            'email' => $user->email,
            'password' => $request->string('password')->toString(),
        ])) {
            throw ValidationException::withMessages(['password' => 'That password is incorrect.']);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        return response()->json([
            'data' => [
                'confirmed' => true,
                'valid_for_seconds' => (int) config('auth.password_timeout'),
            ],
        ]);
    }
}
