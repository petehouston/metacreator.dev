<?php

declare(strict_types=1);

namespace App\Domain\Tools\Models;

use App\Domain\Tools\Actions\RunToolAction;
use App\Domain\Tools\Data\ToolResult;
use App\Domain\Tools\Enums\AccessReason;
use App\Domain\Tools\Enums\RunStatus;
use App\Domain\Users\Models\User;
use App\Support\Concerns\HasUlidKey;
use Database\Factories\ToolRunFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One recorded execution.
 *
 * Written asynchronously on the `analytics` queue so measurement never sits in the
 * user's request path, and deliberately free of personal data: no IP, and — for an
 * anonymous run — no raw input either, only its hash.
 *
 * A run made by a signed-in member is the exception: `input_payload` and
 * `result_payload` hold what they asked for and what they got, because that is what
 * makes run history worth having. They belong to an account that can be deleted,
 * which is what makes keeping them defensible; an anonymous run has no such owner
 * to answer to.
 *
 * @property RunStatus $status
 * @property AccessReason $access_reason
 * @property string $ulid
 * @property string|null $result_ref
 * @property array<string, mixed>|null $input_payload
 * @property array<string, mixed>|null $result_payload
 * @property string|null $visitor_hash
 * @property int|null $user_id
 * @property bool $cache_hit
 * @property int|null $duration_ms
 * @property string|null $error_code
 * @property-read Tool $tool
 * @property-read User|null $user
 */
final class ToolRun extends Model
{
    /** @use HasFactory<ToolRunFactory> */
    use HasFactory;

    use HasUlidKey;

    protected function ulidPrefix(): string
    {
        return 'run';
    }

    protected $guarded = ['id'];

    /**
     * The result of *this* execution, attached in-memory by
     * {@see RunToolAction}.
     *
     * Declared as a real property rather than left dynamic so Eloquent does not treat
     * it as an attribute and try to persist it to a column that does not exist.
     */
    public ?ToolResult $result = null;

    protected function casts(): array
    {
        return [
            'status' => RunStatus::class,
            'access_reason' => AccessReason::class,
            'input_preview' => 'array',
            'input_payload' => 'array',
            'result_payload' => 'array',
            'cache_hit' => 'boolean',
            'duration_ms' => 'integer',
            'provider_calls' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** Declared because our models live under App\Domain, not App\Models. */
    protected static function newFactory(): ToolRunFactory
    {
        return ToolRunFactory::new();
    }

    /** @return BelongsTo<Tool, $this> */
    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSucceeded(Builder $query): Builder
    {
        return $query->where('status', RunStatus::Succeeded->value);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeBetween(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): Builder
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /** True for runs that consumed paid-tier value without paid-tier revenue. */
    public function isComped(): bool
    {
        return $this->access_reason->isComped();
    }
}
