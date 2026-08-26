<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Users\Actions\ConsumeMagicLinkAction;
use App\Domain\Users\Actions\IssueMagicLinkAction;
use App\Domain\Users\Actions\RecordSignInAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\MagicLinkRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class MagicLinkController extends Controller
{
    /**
     * Always 202, whether or not the address has an account.
     *
     * The response deliberately carries no signal — see {@see IssueMagicLinkAction}.
     */
    public function store(MagicLinkRequest $request, IssueMagicLinkAction $issue): JsonResponse
    {
        $issue->execute(
            $request->string('email')->toString(),
            $request->input('redirect_to'),
        );

        return response()->json([
            'data' => [
                'sent' => true,
                'message' => 'If that email has an account, a sign-in link is on its way.',
            ],
        ], 202);
    }

    public function consume(
        Request $request,
        ConsumeMagicLinkAction $consume,
        RecordSignInAction $recordSignIn,
    ): JsonResponse {
        $request->validate(['token' => ['required', 'string', 'size:64']]);

        ['user' => $user, 'redirect_to' => $redirectTo] = $consume->execute(
            $request->string('token')->toString()
        );

        Auth::login($user, remember: true);
        $request->session()->regenerate();
        $recordSignIn->execute($user, $request);

        return (new UserResource($user))
            ->additional(['meta' => ['redirect_to' => $redirectTo]])
            ->response();
    }
}
