<?php

declare(strict_types=1);

namespace App\Domain\Support\Models;

use App\Domain\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A message from the public contact form.
 *
 * Deliberately not a ticket: the sender may have no account, so there is nobody to
 * notify and nothing to thread against. Staff triage these and open a real ticket
 * when one is warranted.
 *
 * @property string $email
 * @property CarbonImmutable|null $handled_at
 * @property CarbonImmutable|null $created_at
 */
final class ContactMessage extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['ip_hash'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeUnhandled(Builder $query): Builder
    {
        return $query->whereNull('handled_at');
    }
}
