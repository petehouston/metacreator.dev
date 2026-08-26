<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Who am I?" — the frontend's bootstrap call.
 *
 * Answers 200 with `null` for a guest rather than 401, because on a public page a
 * signed-out visitor is a completely normal state, not an error the client should
 * have to catch.
 */
final class SessionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => $user === null ? null : (new UserResource($user))->toArray($request),
        ]);
    }
}
