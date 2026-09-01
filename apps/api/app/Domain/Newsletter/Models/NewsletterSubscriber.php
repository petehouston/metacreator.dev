<?php

declare(strict_types=1);

namespace App\Domain\Newsletter\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A newsletter list member and the consent record behind them.
 *
 * The consent columns are not decoration: under GDPR the defensible position is
 * being able to show *what* was agreed to, *when*, and from where — so they travel
 * with the subscriber rather than living in a log that gets rotated away.
 *
 * @property string $email
 * @property string $status
 * @property string $sync_status
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $unsubscribed_at
 * @property CarbonImmutable|null $synced_at
 * @property CarbonImmutable|null $created_at
 */
final class NewsletterSubscriber extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['confirm_token_hash', 'consent_ip_hash'];

    /**
     * Confirmation tokens are stored hashed for the same reason password reset
     * tokens are: a database copy must not be a set of working links.
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSubscribed(Builder $query): Builder
    {
        return $query->where('status', 'subscribed');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->where('email', 'like', "%{$term}%")
            ->orWhere('name', 'like', "%{$term}%"));
    }
}
