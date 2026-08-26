<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Domain\Notifications\Notifier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ChangePasswordRequest;
use App\Http\Resources\UserResource;

/**
 * Behind `password.confirm` (docs/06: sensitive actions require authentication
 * within the last 15 minutes), so a walked-away-from laptop is not enough.
 */
final class PasswordController extends Controller
{
    public function update(ChangePasswordRequest $request, Notifier $notifier): UserResource
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $user->forceFill(['password' => $request->string('password')->toString()])->save();

        // AuthenticateSession compares the session's recorded password hash against
        // the user's current one and logs out any session where they differ. Updating
        // it here is what keeps *this* session alive while every other one dies on its
        // next request — done by hand rather than via logoutOtherDevices(), which
        // would hash the password a second time.
        $request->session()->put('password_hash_web', $user->getAuthPassword());

        $user->devices()
            ->where('session_id', '!=', $request->session()->getId())
            ->update(['session_id' => null, 'revoked_at' => now()]);

        $notifier->send($user, 'user.password_changed', [
            'changed_at' => now()->toDayDateTimeString(),
        ]);

        return new UserResource($user->fresh());
    }
}
