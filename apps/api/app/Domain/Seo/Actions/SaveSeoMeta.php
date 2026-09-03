<?php

declare(strict_types=1);

namespace App\Domain\Seo\Actions;

use App\Domain\Seo\Models\SeoMeta;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes the SEO overrides for any entity that has a `seo` morph.
 *
 * Extracted from the tool editor when the ranking pages needed the same thing.
 * The three rules below are not obvious and each was learned the hard way, which
 * is exactly why they belong in one place rather than being reimplemented per
 * controller:
 *
 *  - **An empty string is not a value.** It is how a cleared text input arrives,
 *    and storing it makes every `?? fallback` on the frontend stop firing — so a
 *    page that used to publish its own title starts publishing nothing.
 *  - **Two columns are NOT NULL with a default.** A cleared `robots` means "back
 *    to index,follow", not "write null and hit a constraint".
 *  - **Only known fields are written.** The payload comes from a form request that
 *    may grow; an unrecognised key reaching `updateOrCreate` is a column that does
 *    not exist.
 */
final class SaveSeoMeta
{
    private const FIELDS = [
        'title', 'description', 'canonical_url', 'robots', 'focus_keyword',
        'og_title', 'og_description', 'og_media_id', 'twitter_card',
        'schema_type', 'schema_overrides',
    ];

    /** Columns that must always hold a value. */
    private const DEFAULTS = [
        'robots' => 'index,follow',
        'twitter_card' => 'summary_large_image',
    ];

    /** @param  array<string, mixed>  $seo */
    public function handle(Model $entity, array $seo): void
    {
        $fields = array_intersect_key($seo, array_flip(self::FIELDS));

        if ($fields === []) {
            return;
        }

        $fields = array_map(
            fn ($value) => is_string($value) && trim($value) === '' ? null : $value,
            $fields,
        );

        foreach (self::DEFAULTS as $column => $default) {
            if (array_key_exists($column, $fields) && $fields[$column] === null) {
                $fields[$column] = $default;
            }
        }

        SeoMeta::query()->updateOrCreate(
            ['seoable_type' => $entity->getMorphClass(), 'seoable_id' => $entity->getKey()],
            $fields,
        );
    }
}
