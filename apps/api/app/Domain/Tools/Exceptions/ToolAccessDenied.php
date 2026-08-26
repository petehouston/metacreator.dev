<?php

declare(strict_types=1);

namespace App\Domain\Tools\Exceptions;

use App\Domain\Tools\Data\AccessDecision;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Carries the full {@see AccessDecision} so the API response can tell the frontend
 * precisely which prompt to show — account wall or paywall — instead of leaving it
 * to guess from a bare 403.
 */
final class ToolAccessDenied extends RuntimeException
{
    public function __construct(public readonly AccessDecision $decision)
    {
        parent::__construct($decision->message ?? 'Access denied.');
    }

    public function render(): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => $this->decision->errorCode,
                'message' => $this->decision->message,
                'status' => $this->decision->httpStatus(),
                'details' => [
                    'required_tier' => $this->decision->requiredTier?->value,
                ],
            ],
        ], $this->decision->httpStatus());
    }
}
