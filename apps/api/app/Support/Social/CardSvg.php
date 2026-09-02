<?php

declare(strict_types=1);

namespace App\Support\Social;

/**
 * The drawing primitives every mock-up card needs.
 *
 * The catalog has a growing family of "draw this post the way the platform draws
 * it" tools, and each one was otherwise going to carry its own copy of the same
 * four problems: SVG cannot reflow text, so the wrap has to be computed; XML has to
 * be escaped or a stray `&` in a caption breaks the whole document; counts have to
 * be abbreviated the way the platform abbreviates them; and an avatar has to fall
 * back to initials when nobody supplied one.
 *
 * What is deliberately *not* here is layout. Each platform's card is its own file,
 * because the thing that makes a mock-up convincing is the specific arrangement —
 * Instagram's square media above a two-line caption, Pinterest's tall pin, X's
 * reply rail — and a shared "card layout" abstraction would have to be bent out of
 * shape for every one of them.
 *
 * These cards are **mock-ups, not evidence**. Nothing here draws a verification
 * badge, and every tool that uses it says so in a warning on every run.
 */
final class CardSvg
{
    /** The system sans stack, matching what each app renders in on a stock device. */
    public const FONT = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * Greedy word wrap on an estimated glyph advance.
     *
     * SVG has no reflow, so the wrap happens here. The `0.55em` factor is a
     * pessimistic estimate for the system sans stack: a line that comes out
     * slightly short costs nothing, a line that runs off the edge of the card is
     * the only failure anybody would ever see.
     *
     * @return list<string>
     */
    public static function wrap(string $text, int $width, int $fontSize, float $advance = 0.55): array
    {
        $maxChars = max(8, (int) floor($width / ($fontSize * $advance)));
        $lines = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $paragraph) {
            if (trim($paragraph) === '') {
                $lines[] = '';

                continue;
            }

            $current = '';

            foreach (preg_split('/\s+/u', trim($paragraph), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                $candidate = $current === '' ? $word : "{$current} {$word}";

                if (mb_strlen($candidate) <= $maxChars) {
                    $current = $candidate;

                    continue;
                }

                if ($current !== '') {
                    $lines[] = $current;
                }

                // A single word wider than the line — a URL, almost always — is
                // broken rather than allowed to overrun the card.
                while (mb_strlen($word) > $maxChars) {
                    $lines[] = mb_substr($word, 0, $maxChars);
                    $word = mb_substr($word, $maxChars);
                }

                $current = $word;
            }

            if ($current !== '') {
                $lines[] = $current;
            }
        }

        return $lines === [] ? [''] : $lines;
    }

    /**
     * Draw wrapped text as one `<text>` element per line.
     *
     * `$highlight` colours the runs every social app colours — links, @mentions and
     * #hashtags — which is the single detail that most makes a drawn card read as a
     * real one.
     *
     * @param  list<string>  $lines
     */
    public static function lines(
        array $lines,
        int $x,
        int $y,
        int $lineHeight,
        string $class,
        ?string $highlight = null,
    ): string {
        $out = '';

        foreach ($lines as $index => $line) {
            $content = $highlight === null
                ? self::escape($line)
                : self::runs($line, $highlight);

            $out .= '<text x="'.$x.'" y="'.($y + $index * $lineHeight).'" class="'.$class.'">'.$content.'</text>';
        }

        return $out;
    }

    /** One line, with links, mentions and hashtags wrapped in an accent `tspan`. */
    public static function runs(string $line, string $accentClass): string
    {
        $parts = preg_split('/(\s+)/u', $line, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $out = '';

        foreach ($parts as $part) {
            // Non-breaking spaces: SVG collapses runs of whitespace, and a caption
            // that loses its indentation stops looking like the app's own.
            $escaped = str_replace(' ', '&#160;', self::escape($part));

            $out .= preg_match('/^(https?:\/\/|www\.|@\w|#\w)/u', $part) === 1
                ? '<tspan class="'.$accentClass.'">'.$escaped.'</tspan>'
                : '<tspan>'.$escaped.'</tspan>';
        }

        return $out;
    }

    /**
     * A circular avatar: the supplied image, or the name's initials on a flat disc.
     *
     * The image is referenced by URL rather than embedded, so a card built through
     * the API never copies somebody's profile picture into our storage. The web
     * app's canvas version reads a local file instead and uploads nothing at all.
     */
    public static function avatar(
        int $cx,
        int $cy,
        int $radius,
        string $name,
        ?string $imageUrl,
        string $discColor,
        string $textColor,
    ): string {
        if ($imageUrl !== null && $imageUrl !== '') {
            $id = 'clip'.$cx.'x'.$cy;
            $size = $radius * 2;

            return '<defs><clipPath id="'.$id.'"><circle cx="'.$cx.'" cy="'.$cy.'" r="'.$radius.'"/></clipPath></defs>'
                .'<image href="'.self::escape($imageUrl).'" x="'.($cx - $radius).'" y="'.($cy - $radius)
                .'" width="'.$size.'" height="'.$size.'" preserveAspectRatio="xMidYMid slice" clip-path="url(#'.$id.')"/>';
        }

        return '<circle cx="'.$cx.'" cy="'.$cy.'" r="'.$radius.'" fill="'.$discColor.'"/>'
            .'<text x="'.$cx.'" y="'.($cy + (int) round($radius * 0.36)).'" text-anchor="middle" '
            .'style="font-family:'.self::FONT.';font-size:'.(int) round($radius * 0.9).'px;font-weight:700;fill:'
            .$textColor.'">'.self::escape(self::initials($name)).'</text>';
    }

    public static function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim(ltrim($name, '@')), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return '?';
        }

        $initials = mb_strtoupper(mb_substr($words[0], 0, 1));

        return count($words) === 1 ? $initials : $initials.mb_strtoupper(mb_substr((string) end($words), 0, 1));
    }

    /**
     * A count as a feed abbreviates it: 999, then 1K, 5.2K, 1.4M.
     *
     * One decimal, and a trailing `.0` dropped rather than printed — every one of
     * these platforms writes "5K", never "5.0K", and that is the detail that gives
     * a mock-up away fastest.
     */
    public static function compact(int $value): string
    {
        $value = max(0, $value);

        if ($value >= 1_000_000) {
            return self::trimZero($value / 1_000_000).'M';
        }

        return $value >= 1_000 ? self::trimZero($value / 1_000).'K' : (string) $value;
    }

    /** `@handle`, with exactly one `@` however many the visitor typed. */
    public static function handle(string $value): string
    {
        return '@'.ltrim(trim($value), '@');
    }

    /** "5 hours ago", "1 hour ago" — singularised at one, as every app does. */
    public static function age(int $value, string $unit): string
    {
        $value = max(1, $value);

        return $value.' '.($value === 1 ? rtrim($unit, 's') : $unit).' ago';
    }

    /** Wrap drawn content in a finished SVG document. */
    public static function document(int $width, int $height, string $styles, string $body, string $label): string
    {
        $label = self::escape($label);
        $font = self::FONT;

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}" role="img" aria-label="{$label}">
          <style>text { font-family: {$font}; }{$styles}</style>
          {$body}
        </svg>
        SVG;
    }

    private static function trimZero(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
    }
}
