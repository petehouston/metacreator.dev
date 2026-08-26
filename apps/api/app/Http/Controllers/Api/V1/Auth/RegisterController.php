<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Users\Actions\RecordSignInAction;
use App\Domain\Users\Actions\RegisterUserAction;
use App\Http\Controllers\Controller;
use App\Http\Middleware\IdentifyVisitor;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

final class RegisterController extends Controller
{
    public function __invoke(
        RegisterRequest $request,
        RegisterUserAction $register,
        RecordSignInAction $recordSignIn,
    ): JsonResponse {
        $user = $register->execute([
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
            'display_name' => $request->filled('display_name')
                ? $request->string('display_name')->toString()
                : null,
            'marketing_opt_in' => $request->boolean('marketing_opt_in'),
            'locale' => $request->string('locale', 'en')->toString(),
            'timezone' => $request->string('timezone', 'UTC')->toString(),
        ], visitorHash: (string) $request->attributes->get(IdentifyVisitor::ATTRIBUTE));

        Auth::login($user, remember: true);
        $request->session()->regenerate();
        $recordSignIn->execute($user, $request);

        return (new UserResource($user))->response()->setStatusCode(201);
    }
}
