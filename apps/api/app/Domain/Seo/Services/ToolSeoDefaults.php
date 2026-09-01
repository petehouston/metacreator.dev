<?php

declare(strict_types=1);

namespace App\Domain\Seo\Services;

use App\Domain\Settings\Settings;
use App\Domain\Tools\Enums\ToolTier;
use App\Domain\Tools\Models\Tool;

/**
 * Complete, publishable SEO for a tool that nobody has tuned by hand.
 *
 * Tool pages are the money pages (docs/16): they carry the organic traffic and they
 * are where the paywall converts. A tool with a blank meta description is a result
 * Google writes the snippet for, and a share that renders as a grey box with a URL
 * under it — so the answer to "what if the admin left it empty?" has to be a good
 * default rather than nothing.
 *
 * These are *defaults*, layered under whatever an admin has stored: any field they
 * have filled in wins, field by field, and every field they have not is filled from
 * here. That is why this returns the same key set as `seo_meta` holds.
 *
 * The rules behind the copy:
 *
 * - **Title** leads with the tool's own name, because that is the phrase people
 *   search for, and ends with the qualifier that earns the click — "Free" when the
 *   tool genuinely is, never when it is not. A title that overclaims gets the
 *   click and loses the visit.
 * - **Description** is the tagline (the promise) plus one clause saying what it
 *   costs to try. Search snippets are cut around 155 characters, so it is built to
 *   fit rather than truncated mid-word.
 * - **Social** copy is separate and shorter. A timeline is not a search result: the
 *   name alone reads as a link nobody clicks, so the og title carries the promise.
 */
final readonly class ToolSeoDefaults
{
    /** Where Google reliably cuts a title, in characters. */
    private const TITLE_LIMIT = 60;

    private const DESCRIPTION_LIMIT = 155;

    private const OG_TITLE_LIMIT = 70;

    private const OG_DESCRIPTION_LIMIT = 200;

    /** Platform keys as they should read in a title. */
    private const PLATFORM_LABELS = [
        'youtube' => 'YouTube',
        'instagram' => 'Instagram',
        'tiktok' => 'TikTok',
        'x' => 'X',
        'twitter' => 'X',
        'facebook' => 'Facebook',
        'linkedin' => 'LinkedIn',
        'pinterest' => 'Pinterest',
        'threads' => 'Threads',
        'twitch' => 'Twitch',
        'snapchat' => 'Snapchat',
    ];

    public function __construct(private Settings $settings) {}

    /**
     * The full default payload for one tool.
     *
     * @return array{
     *     title: string, description: string, canonical_url: null, robots: string,
     *     focus_keyword: string, og_title: string, og_description: string,
     *     twitter_card: string, schema_type: string
     * }
     */
    public function for(Tool $tool): array
    {
        return [
            'title' => $this->title($tool),
            'description' => $this->description($tool),
            // Deliberately null: the frontend derives the canonical from the route,
            // and a stored one that drifts from a renamed slug is worse than none.
            'canonical_url' => null,
            'robots' => 'index,follow',
            'focus_keyword' => $this->focusKeyword($tool),
            'og_title' => $this->ogTitle($tool),
            'og_description' => $this->ogDescription($tool),
            // Large card: a tool page's share is a screenshot-shaped promise, and
            // the summary card gives it a thumbnail nobody can read.
            'twitter_card' => 'summary_large_image',
            'schema_type' => 'SoftwareApplication',
        ];
    }

    /**
     * An admin's stored values, with every gap filled from the defaults.
     *
     * Blank strings count as gaps, not as choices — a cleared input is how someone
     * says "use the default", and storing the empty string would publish it.
     *
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    public function merge(Tool $tool, array $stored): array
    {
        $defaults = $this->for($tool);
        $merged = $stored;

        foreach ($defaults as $key => $default) {
            $value = $stored[$key] ?? null;

            if ($value === null || (is_string($value) && trim($value) === '')) {
                $merged[$key] = $default;
            }
        }

        return $merged;
    }

    // ── The copy ─────────────────────────────────────────────────────────────

    private function title(Tool $tool): string
    {
        $name = trim($tool->name);
        $qualifier = match ($tool->tier) {
            ToolTier::Free => 'Free Online Tool',
            ToolTier::Account => 'Free Tool, No Card Needed',
            ToolTier::Premium => 'Pro Creator Tool',
        };

        // Naming the platform lifts a long-tail query ("free instagram bio counter")
        // onto the title, but only when the name has not already said it and only
        // when it still fits — a truncated title converts worse than a shorter one.
        $platform = $this->soloPlatform($tool);

        if ($platform !== null && ! $this->mentions($name, $platform) && $tool->tier === ToolTier::Free) {
            $withPlatform = "{$name} — Free {$platform} Tool";

            if (mb_strlen($withPlatform) <= self::TITLE_LIMIT) {
                return $withPlatform;
            }
        }

        $full = "{$name} — {$qualifier}";

        // The name alone still beats a title cut mid-qualifier.
        return mb_strlen($full) <= self::TITLE_LIMIT ? $full : $name;
    }

    private function description(Tool $tool): string
    {
        $promise = $this->sentence($tool->tagline ?? $tool->description ?? $tool->name);

        $cta = match ($tool->tier) {
            ToolTier::Free => 'Free to use — no sign-up, no watermark.',
            ToolTier::Account => 'Free with a MetaCreator account.',
            ToolTier::Premium => 'Included with MetaCreator Pro.',
        };

        return $this->fit($promise, $cta, self::DESCRIPTION_LIMIT);
    }

    /**
     * The share headline.
     *
     * A timeline gives a link one line. The tool's name alone is a label; the name
     * plus what it does is a reason to tap, so the hook is the tagline's first
     * clause rather than the tier boilerplate that belongs in search results.
     */
    private function ogTitle(Tool $tool): string
    {
        $name = trim($tool->name);
        $hook = $this->clause($tool->tagline ?? '');

        if ($hook !== '' && ! $this->mentions($hook, $name)) {
            $combined = "{$name} — {$hook}";

            if (mb_strlen($combined) <= self::OG_TITLE_LIMIT) {
                return $combined;
            }
        }

        $suffix = $tool->tier === ToolTier::Free ? ' — Free, No Sign-Up' : '';

        return mb_strlen($name.$suffix) <= self::OG_TITLE_LIMIT ? $name.$suffix : $name;
    }

    private function ogDescription(Tool $tool): string
    {
        $promise = $this->sentence($tool->tagline ?? $tool->description ?? $tool->name);
        $site = $this->settings->string('site.name', 'MetaCreator.Dev');

        $cta = match ($tool->tier) {
            ToolTier::Free => "Run it in your browser on {$site} — nothing to install.",
            ToolTier::Account => "Free with a {$site} account.",
            ToolTier::Premium => "Part of {$site} Pro.",
        };

        return $this->fit($promise, $cta, self::OG_DESCRIPTION_LIMIT);
    }

    /**
     * The phrase this page is trying to rank for.
     *
     * The tool's own name, lowercased: these are named after the query on purpose
     * ("engagement rate calculator", "youtube thumbnail downloader"), so the name
     * *is* the keyword and inventing a different one would only fight it.
     */
    private function focusKeyword(Tool $tool): string
    {
        return mb_strtolower(trim($tool->name));
    }

    // ── Text helpers ─────────────────────────────────────────────────────────

    /**
     * Join a promise and a call to action inside a character budget, dropping the
     * call to action rather than cutting either one mid-word.
     */
    private function fit(string $promise, string $cta, int $limit): string
    {
        $promise = trim($promise);

        if ($promise === '') {
            return $cta;
        }

        $combined = $promise.' '.$cta;

        if (mb_strlen($combined) <= $limit) {
            return $combined;
        }

        return mb_strlen($promise) <= $limit ? $promise : $this->truncate($promise, $limit);
    }

    /** Ensure a fragment reads as a sentence, so the join above does not run on. */
    private function sentence(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        if ($text === '') {
            return '';
        }

        return in_array(mb_substr($text, -1), ['.', '!', '?'], true) ? $text : $text.'.';
    }

    /** The first clause of a sentence — up to the first full stop, comma or dash. */
    private function clause(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        if ($text === '') {
            return '';
        }

        $parts = preg_split('/[.,—–]|\s+-\s+/u', $text, 2);
        $first = trim($parts[0] ?? $text);

        // Sentence case, not Title Case: a share headline shouting in title case
        // reads as an ad, which is exactly what gets scrolled past.
        return $first === '' ? '' : mb_strtoupper(mb_substr($first, 0, 1)).mb_substr($first, 1);
    }

    /** Cut at the last word boundary inside the budget, leaving room for the ellipsis. */
    private function truncate(string $text, int $limit): string
    {
        $cut = mb_substr($text, 0, $limit - 1);
        $lastSpace = mb_strrpos($cut, ' ');

        return rtrim($lastSpace === false ? $cut : mb_substr($cut, 0, $lastSpace), ' ,.;:-').'…';
    }

    /**
     * The platform this tool is *about*, when it is about exactly one.
     *
     * A cross-platform tool names no platform in its title: "Free YouTube Tool" on
     * a page that also serves TikTok is a mismatch between the promise and the page,
     * and that is a bounce.
     */
    private function soloPlatform(Tool $tool): ?string
    {
        $platforms = $tool->platformList();

        if (count($platforms) !== 1) {
            return null;
        }

        return self::PLATFORM_LABELS[$platforms[0]] ?? null;
    }

    private function mentions(string $haystack, string $needle): bool
    {
        return $needle !== '' && str_contains(mb_strtolower($haystack), mb_strtolower($needle));
    }
}
