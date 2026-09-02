<?php

declare(strict_types=1);

namespace App\Support\Social;

/**
 * A stand-in picture for a preview that has no real one yet.
 *
 * Some preview tools are handed an image — a channel's live banner, the artwork on
 * a podcast — and draw it. Others are asked about a design that does not exist
 * outside the visitor's head, and for those a grey rectangle labelled "your image"
 * makes the geometry hard to read: it is precisely the *content* moving under the
 * crop that a safe-area preview exists to show, and nothing moves under a flat
 * box.
 *
 * So a frame with no image gets one of these instead: an abstract card built from
 * the seed, emitted as an inline SVG data URI. Inline rather than a file on disk
 * because it costs no request, cannot 404, and is not a third-party image loading
 * on a page that promised not to load one. Deterministic rather than random
 * because a cached result must not change picture between two runs of the same
 * input — "random" here means "arbitrary but stable", which is the only kind a
 * cacheable tool can have.
 */
final class PreviewImage
{
    /**
     * Colour pairs to wash the card with, chosen to read as photography rather than
     * as UI: warm/cool pairs at similar values, so nothing looks like an error state.
     *
     * @var list<array{0: string, 1: string}>
     */
    private const PALETTES = [
        ['#1e3a8a', '#0ea5e9'],
        ['#7c2d12', '#f59e0b'],
        ['#14532d', '#84cc16'],
        ['#4c1d95', '#e879f9'],
        ['#0f172a', '#38bdf8'],
        ['#831843', '#fb7185'],
    ];

    /** @var array<string, array{0: int, 1: int}> */
    private const ASPECTS = [
        '16:9' => [1600, 900],
        '1:1' => [1200, 1200],
        '4:5' => [1080, 1350],
        '9:16' => [1080, 1920],
        '1.91:1' => [1200, 628],
    ];

    public static function placeholder(string $seed, string $aspect = '16:9'): string
    {
        [$width, $height] = self::ASPECTS[$aspect] ?? self::ASPECTS['16:9'];

        $hash = crc32($seed);
        [$from, $to] = self::PALETTES[$hash % count(self::PALETTES)];

        // Two off-centre circles and a diagonal band: enough structure that a crop
        // visibly takes something away, which is the whole job.
        $cx = 0.25 + (($hash >> 3) % 50) / 100;
        $cy = 0.30 + (($hash >> 7) % 40) / 100;
        $angle = ($hash >> 11) % 60 - 30;

        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$width} {$height}">
            <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="{$from}"/><stop offset="1" stop-color="{$to}"/>
            </linearGradient></defs>
            <rect width="{$width}" height="{$height}" fill="url(#g)"/>
            <g fill="#ffffff" opacity="0.14">
            <circle cx="{$cx}" cy="{$cy}" r="0.28" transform="scale({$width} {$height})"/>
            <circle cx="0.78" cy="0.72" r="0.34" transform="scale({$width} {$height})"/>
            </g>
            <rect x="-10%" y="46%" width="120%" height="8%" fill="#ffffff" opacity="0.10"
            transform="rotate({$angle} {$width} {$height})"/>
            </svg>
            SVG;

        $svg = (string) preg_replace('/\s+/', ' ', trim($svg));

        return 'data:image/svg+xml;charset=utf-8,'.rawurlencode($svg);
    }
}
