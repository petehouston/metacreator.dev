<?php

declare(strict_types=1);

namespace App\Domain\Support\Models;

use App\Domain\Support\Enums\TicketPriority;
use App\Domain\Support\Enums\TicketStatus;
use App\Domain\Users\Models\User;
use App\Support\Concerns\HasUlidKey;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property TicketStatus $status
 * @property TicketPriority $priority
 * @property CarbonImmutable|null $due_at
 * @property CarbonImmutable|null $resolved_at
 */
final class Ticket extends Model
{
    use HasUlidKey;

    protected function ulidPrefix(): string
    {
        return 'tkt';
    }

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'due_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return HasMany<TicketMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('created_at');
    }

    /**
     * Messages the customer is allowed to see — internal notes are excluded.
     *
     * @return HasMany<TicketMessage, $this>
     */
    public function publicMessages(): HasMany
    {
        return $this->messages()->where('is_internal_note', false);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [TicketStatus::Open->value, TicketStatus::Pending->value]);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereIn('status', [TicketStatus::Open->value, TicketStatus::OnHold->value]);
    }

    public function isOverdue(): bool
    {
        return $this->due_at?->isPast() === true && ! $this->status->isResolved();
    }
}
