<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Domain\Billing\Services\EntitlementService;
use App\Domain\Tools\Services\QuotaService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The single source of truth the frontend uses for gating.
 *
 * Composes plan entitlements with live usage here rather than having either service
 * depend on the other — that mutual dependency is a container cycle waiting to happen.
 */
final class EntitlementsController extends Controller
{
    public function __invoke(
        Request $request,
        EntitlementService $entitlements,
        QuotaService $quota,
    ): JsonResponse {
        $user = $request->user();

        // The route is behind `auth:sanctum`, but relying on middleware for a type
        // guarantee is how a refactor turns into a 500. Assert it here.
        abort_if($user === null, 401);

        return response()->json([
            'data' => $entitlements->describe($user, $quota->status($user)),
        ]);
    }
}
