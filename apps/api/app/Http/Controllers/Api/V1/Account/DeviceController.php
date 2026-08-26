<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiCollection;
use App\Http\Resources\UserDeviceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The "where am I signed in?" screen from docs/06.
 */
final class DeviceController extends Controller
{
    /** @return ApiCollection<UserDeviceResource> */
    public function index(Request $request): ApiCollection
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $devices = $user->devices()
            ->whereNull('revoked_at')
            ->orderByDesc('last_seen_at')
            ->paginate(perPage: 25);

        return new ApiCollection($devices, UserDeviceResource::class);
    }

    public function destroy(Request $request, int $device): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $record = $user->devices()->whereKey($device)->firstOrFail();

        // Revoking the device you are holding is just a sign-out, and the client
        // needs to know that so it can clear its own state.
        $isCurrent = $record->isCurrent($request);

        $record->forceFill(['revoked_at' => now(), 'session_id' => null])->save();

        if ($isCurrent) {
            auth()->guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['data' => ['revoked' => true, 'was_current' => $isCurrent]]);
    }
}
