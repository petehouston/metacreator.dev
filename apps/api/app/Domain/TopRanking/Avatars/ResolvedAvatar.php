<?php

declare(strict_types=1);

namespace App\Domain\TopRanking\Avatars;

use App\Domain\TopRanking\Enums\AvatarStatus;
use Carbon\CarbonImmutable;

/**
 * The outcome of one attempt to find an account's picture.
 *
 * A failure is a value here, not an exception: resolving 50 avatars means 50
 * independent attempts, and the one that a platform refuses must not stop the
 * other 49. The status carries *why*, which is what the admin row reports.
 */
final readonly class ResolvedAvatar
{
    private function __construct(
        public AvatarStatus $status,
        public ?string $url,
        public ?string $source,
        public ?CarbonImmutable $expiresAt,
    ) {}

    public static function found(string $url, string $source, ?CarbonImmutable $expiresAt = null): self
    {
        return new self(AvatarStatus::Ok, $url, $source, $expiresAt);
    }

    public static function unavailable(): self
    {
        return new self(AvatarStatus::Unavailable, null, null, null);
    }

    public function isFound(): bool
    {
        return $this->status === AvatarStatus::Ok;
    }
}
