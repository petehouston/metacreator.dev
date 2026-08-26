<?php

declare(strict_types=1);

namespace App\Domain\Users\Actions;

use App\Domain\Notifications\Notifier;
use App\Domain\Users\Models\User;
use App\Domain\Users\Models\UserDevice;
use Illuminate\Http\Request;

/**
 * Everything that happens *after* credentials check out, regardless of which of the
 * four sign-in methods was used.
 *
 * Keeping it in one place is what stops the new-device alert from existing on the
 * password path and quietly not on the magic-link path.
 */
final readonly class RecordSignInAction
{
    public function __construct(private Notifier $notifier) {}

    public function execute(User $user, Request $request): void
    {
        $fingerprint = UserDevice::fingerprintFor($request);

        $device = UserDevice::query()
            ->where('user_id', $user->id)
            ->where('fingerprint', $fingerprint)
            ->first();

        $isNewDevice = $device === null;

        if ($isNewDevice) {
            $device = new UserDevice([
                'user_id' => $user->id,
                'fingerprint' => $fingerprint,
                'label' => UserDevice::labelFor($request->userAgent()),
            ]);
        }

        $device->forceFill([
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
            'last_seen_at' => now(),
            'revoked_at' => null,
        ])->save();

        $user->forceFill(['last_seen_at' => now()])->save();

        // The very first sign-in is not "a new device" from the user's point of view —
        // they are standing at it. Alerting then teaches people the alert is noise.
        if ($isNewDevice && $user->devices()->count() > 1) {
            $this->notifier->send($user, 'user.new_device_login', [
                'device' => $device->label,
                'ip' => (string) $request->ip(),
                'signed_in_at' => now()->toDayDateTimeString(),
            ]);
        }
    }
}
