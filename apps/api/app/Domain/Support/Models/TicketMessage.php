<?php

declare(strict_types=1);

namespace App\Domain\Support\Models;

use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $author_type
 * @property bool $is_internal_note
 */
final class TicketMessage extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_internal_note' => 'boolean'];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return HasMany<TicketAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function isFromStaff(): bool
    {
        return $this->author_type === 'staff';
    }
}
