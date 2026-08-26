<?php

declare(strict_types=1);

namespace App\Domain\Tools\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

final class QuotaExceeded extends RuntimeException
{
    public function __construct(
        public readonly int $limit,
        public readonly \DateTimeInterface $resetsAt,
        public readonly bool $upgradeAvailable = true,
    ) {
        parent::__construct("Daily limit of {$limit} runs reached.");
    }

    public function render(): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => 'tool.quota_exceeded',
                'message' => "You've used all {$this->limit} runs for today.",
                'status' => 429,
                'details' => [
                    'limit' => $this->limit,
                    'resets_at' => $this->resetsAt->format(DATE_ATOM),
                    'upgrade_available' => $this->upgradeAvailable,
                ],
            ],
        ], 429, ['Retry-After' => (string) max(1, $this->resetsAt->getTimestamp() - time())]);
    }
}
