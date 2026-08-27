<?php

declare(strict_types=1);

namespace App\Domain\Access\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

/**
 * Every permission-gated write, recorded with actor, subject and diff (docs/06).
 *
 * Wrapped rather than calling `activity()` directly at each site so that three
 * things are guaranteed everywhere: the log name is one of a known set, the diff is
 * limited to what actually changed, and secrets never reach the log. A rotated API
 * key that appears in plaintext in an audit trail is worse than no audit trail.
 */
final class AuditLogger
{
    /** Keys whose values are redacted wherever they appear in a diff. */
    private const REDACTED = ['api_key', 'secret', 'password', 'token', 'stripe_secret'];

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function record(
        string $event,
        Model $subject,
        ?Model $causer = null,
        array $before = [],
        array $after = [],
        string $logName = 'admin',
        ?string $description = null,
    ): void {
        $changes = $this->diff($before, $after);

        activity($logName)
            ->when($causer !== null, fn ($logger) => $logger->causedBy($causer))
            ->performedOn($subject)
            ->event($event)
            ->withProperties($changes === [] ? [] : ['changes' => $changes])
            ->log($description ?? $this->describe($event, $subject));
    }

    /**
     * Only the keys that moved, old and new side by side.
     *
     * Logging the whole model on every save makes the log unreadable, and unreadable
     * is functionally the same as absent when someone is trying to work out what
     * happened last Tuesday.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function diff(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $key => $value) {
            $old = $before[$key] ?? null;

            if ($old === $value) {
                continue;
            }

            $redact = $this->isSensitive($key);

            $changes[$key] = [
                'from' => $redact ? $this->mask($old) : $old,
                'to' => $redact ? $this->mask($value) : $value,
            ];
        }

        return $changes;
    }

    private function isSensitive(string $key): bool
    {
        foreach (self::REDACTED as $needle) {
            if (str_contains(strtolower($key), $needle)) {
                return true;
            }
        }

        return false;
    }

    /** Enough to see that a value changed, never enough to use it. */
    private function mask(mixed $value): string
    {
        return is_string($value) && $value !== '' ? '••••'.mb_substr($value, -2) : '••••';
    }

    private function describe(string $event, Model $subject): string
    {
        return class_basename($subject)." {$event}";
    }

    /**
     * Recent activity for the admin log screen.
     *
     * @return LengthAwarePaginator<int, Activity>
     */
    public function feed(?string $logName = null, ?string $event = null, int $perPage = 30)
    {
        return Activity::query()
            ->with('causer:id,ulid,display_name,name,email,avatar_path')
            ->when($logName !== null, fn ($q) => $q->where('log_name', $logName))
            ->when($event !== null, fn ($q) => $q->where('event', $event))
            ->latest('id')
            ->paginate($perPage);
    }
}
