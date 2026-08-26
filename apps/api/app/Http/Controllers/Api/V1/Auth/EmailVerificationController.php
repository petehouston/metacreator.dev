<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Verification is confirmed against a signed URL rather than a stored token, so
 * there is no table to prune and no token to leak.
 */
final class EmailVerificationController extends Controller
{
    /** Route is behind `signed`, so a tampered link never reaches this method. */
    public function verify(Request $request, string $ulid, string $hash): JsonResponse
    {
        $user = User::query()->where('ulid', strtoupper($ulid))->firstOrFail();

        // The hash binds the link to the address it was sent to.
        abort_unless(hash_equals(sha1($user->email), $hash), 403);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return response()->json(['data' => ['verified' => true]]);
    }

    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        if ($user->hasVerifiedEmail()) {
            return response()->json(['data' => ['verified' => true, 'sent' => false]]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['data' => ['verified' => false, 'sent' => true]], 202);
    }
}
