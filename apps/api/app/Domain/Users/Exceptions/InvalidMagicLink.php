<?php

declare(strict_types=1);

namespace App\Domain\Users\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * One exception for expired, already-used and forged tokens alike.
 *
 * Distinguishing them in the response would tell an attacker which of their guesses
 * were real tokens.
 */
final class InvalidMagicLink extends RuntimeException
{
    public function render(): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => 'auth.magic_link_invalid',
                'message' => 'This sign-in link is no longer valid. Request a new one.',
                'status' => 422,
            ],
        ], 422);
    }
}
