<?php

declare(strict_types=1);

namespace App\Domain\TopRanking\Actions;

use App\Domain\TopRanking\Enums\SyncStatus;

/**
 * What one refresh did, in the terms an editor asks about.
 *
 * Counts rather than a boolean, because "it worked" is not the useful answer: a
 * run that removed 40 rows and added 40 is a restructured article and wants
 * looking at, even though nothing failed.
 */
final readonly class SyncResult
{
    public function __construct(
        public SyncStatus $status,
        public int $imported = 0,
        public int $added = 0,
        public int $updated = 0,
        public int $removed = 0,
        public int $kept = 0,
        public ?string $message = null,
    ) {}

    public static function failed(string $message): self
    {
        return new self(SyncStatus::Failed, message: $message);
    }

    /** One line, for the admin panel and for the command's output. */
    public function summary(): string
    {
        if ($this->status === SyncStatus::Failed) {
            return $this->message ?? 'Failed.';
        }

        return sprintf(
            '%d row(s) read — %d added, %d updated, %d removed, %d kept.%s',
            $this->imported,
            $this->added,
            $this->updated,
            $this->removed,
            $this->kept,
            $this->message !== null ? ' '.$this->message : '',
        );
    }
}
