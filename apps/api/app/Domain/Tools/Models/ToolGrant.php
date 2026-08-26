<?php

declare(strict_types=1);

namespace App\Domain\Tools\Models;

use App\Domain\Users\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\ToolGrantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An explicit, admin-issued permission for one user to use one tool regardless of
 * its tier. Used for support recovery, trials, partnerships and influencer seeding.
 *
 * Every write is mirrored into the activity log, and `access_reason = grant` on the
 * resulting runs keeps comped usage visible in reporting.
 *
 * @property CarbonImmutable|null $expires_at
 */
final class ToolGrant extends Model
{
    /** @use HasFactory<ToolGrantFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    /** Declared because our models live under App\Domain, not App\Models. */
    protected static function newFactory(): ToolGrantFactory
    {
        return ToolGrantFactory::new();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Tool, $this> */
    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    /** @return BelongsTo<User, $this> */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNull('expires_at')
            ->orWhere('expires_at', '>', now()));
    }

    public function isActive(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
