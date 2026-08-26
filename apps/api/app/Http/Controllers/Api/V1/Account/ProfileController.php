<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Domain\Notifications\Notifier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Http\Resources\UserResource;

final class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request, Notifier $notifier): UserResource
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $changes = $request->safe()->only(['display_name', 'timezone', 'locale', 'marketing_opt_in']);

        $user->fill($changes)->save();

        // Only notify when something actually moved — saving a form unchanged should
        // not produce a notification.
        $changed = array_keys($user->getChanges());
        $changed = array_values(array_diff($changed, ['updated_at']));

        if ($changed !== []) {
            $notifier->send($user, 'user.profile_updated', [
                'fields' => implode(', ', $changed),
            ]);
        }

        return new UserResource($user->fresh());
    }
}
