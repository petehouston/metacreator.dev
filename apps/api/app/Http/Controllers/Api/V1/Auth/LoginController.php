<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Users\Actions\RecordSignInAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Email + password sign-in over Sanctum's stateful (cookie) guard.
 *
 * The throttle is applied by the `throttle:auth` middleware on the route, which is
 * keyed by email *and* IP — so one attacker cannot lock out a victim's account by
 * spraying their address from anywhere.
 */
final class LoginController extends Controller
{
    public function store(LoginRequest $request, RecordSignInAction $recordSignIn): UserResource
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials, $request->boolean('remember', true))) {
            // One message for "no such account" and "wrong password" alike.
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $user = $request->user();

        // Auth::attempt() just succeeded, so this cannot be null — but asserting it
        // is how a later refactor fails loudly instead of with a 500 on a null call.
        abort_if($user === null, 401);

        if (! $user->isActive()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'This account has been suspended. Contact support if you think that is a mistake.',
            ]);
        }

        $request->session()->regenerate();
        $recordSignIn->execute($user, $request);

        return new UserResource($user);
    }

    public function destroy(Request $request): JsonResponse
    {
        $sessionId = $request->hasSession() ? $request->session()->getId() : null;

        // Detach the device row from the dead session so the security screen does not
        // keep advertising a session that no longer exists.
        if ($sessionId !== null) {
            $request->user()?->devices()->where('session_id', $sessionId)
                ->update(['session_id' => null]);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['data' => ['signed_out' => true]]);
    }
}
