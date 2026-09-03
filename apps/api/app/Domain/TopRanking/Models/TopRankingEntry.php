<?php

declare(strict_types=1);

namespace App\Domain\TopRanking\Models;

use App\Domain\TopRanking\Enums\AvatarStatus;
use App\Domain\TopRanking\Enums\EntrySource;
use App\Support\Social\CdnImage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One account on one ranking page.
 *
 * No ULID and no public id: an entry is never addressed on its own from outside.
 * Admin reaches it through its page, which is what every route here is keyed on.
 *
 * @property int $id
 * @property int $page_id
 * @property int $sort_order
 * @property string $name
 * @property string|null $handle
 * @property string|null $owner
 * @property string|null $profile_url
 * @property string|null $metric_value
 * @property string|null $secondary_metric_value
 * @property string|null $country
 * @property string|null $category
 * @property string|null $language
 * @property string|null $description
 * @property string|null $avatar_url
 * @property AvatarStatus $avatar_status
 * @property string|null $avatar_source
 * @property CarbonImmutable|null $avatar_checked_at
 * @property CarbonImmutable|null $avatar_expires_at
 * @property EntrySource $source
 * @property bool $is_pinned
 * @property string $match_key
 * @property-read TopRankingPage $page
 */
final class TopRankingEntry extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'metric_value' => 'decimal:3',
            'secondary_metric_value' => 'decimal:3',
            'avatar_status' => AvatarStatus::class,
            'source' => EntrySource::class,
            'is_pinned' => 'boolean',
            'avatar_checked_at' => 'datetime',
            'avatar_expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TopRankingPage, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(TopRankingPage::class, 'page_id');
    }

    /**
     * The key a sync reconciles this row on.
     *
     * The handle where there is one, the name otherwise. Normalised hard — case,
     * punctuation and the leading @ all removed — because the same account is
     * written `@khaby.lame`, `khaby.lame` and `Khaby Lame` across the articles and
     * across a year of edits to one article, and a reconciliation that treats those
     * as three accounts re-imports the row every week and throws away its avatar
     * each time.
     */
    public static function matchKeyFor(?string $handle, string $name): string
    {
        $basis = $handle !== null && trim($handle) !== '' ? $handle : $name;

        $key = Str::of($basis)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->limit(180, '')
            ->value();

        // A name that normalises to nothing at all — CJK-only, or punctuation —
        // still needs a stable key, and a hash of the original is stable in a way
        // that an empty string is not.
        return $key !== '' ? $key : substr(md5($basis), 0, 32);
    }

    /**
     * Whether the stored picture link should be put in front of a reader.
     *
     * The expiry check is not belt-and-braces: Meta and TikTok links carry their
     * own death date in the query string, and a link past it answers 403. Catching
     * that here means the page draws a monogram — which looks deliberate — instead
     * of a broken image icon, without waiting for the weekly job to notice.
     */
    public function hasUsableAvatar(): bool
    {
        if ($this->avatar_url === null || ! $this->avatar_status->isUsable()) {
            return false;
        }

        return $this->avatar_expires_at === null || $this->avatar_expires_at->isFuture();
    }

    /**
     * When this picture link stops working, read out of the link itself.
     *
     * {@see CdnImage} already knows how to read Meta's `oe` parameter — it was
     * written for the image downloaders, which face exactly this problem — so this
     * is a lookup, not a second implementation.
     */
    public static function expiryFor(string $url): ?CarbonImmutable
    {
        $timestamp = CdnImage::expiresAt($url);

        return $timestamp === null ? null : CarbonImmutable::createFromTimestamp($timestamp);
    }

    /** The letters a monogram falls back to when there is no picture. */
    public function initials(): string
    {
        $basis = trim((string) ($this->owner ?? '')) !== '' ? (string) $this->owner : $this->name;

        $words = preg_split('/[\s._-]+/', trim(ltrim($basis, '@'))) ?: [];
        $letters = '';

        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }

            $letters .= mb_strtoupper(mb_substr($word, 0, 1));

            if (mb_strlen($letters) === 2) {
                break;
            }
        }

        return $letters !== '' ? $letters : '#';
    }
}
