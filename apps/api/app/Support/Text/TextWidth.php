<?php

declare(strict_types=1);

namespace App\Support\Text;

/**
 * How wide a string is when it is drawn, in CSS pixels.
 *
 * Two of the truncation previews in this catalog cut on width rather than on a
 * character count, because the surfaces they model do: Google gives a result title
 * a fixed-width column and stops when the glyphs run out of it, and every mail
 * client does the same to a subject line. A character count is the wrong unit for
 * both — "Will it fit?" has different answers for "WWWWWWWWWWWW" and "iiiiiiiiiiii"
 * at the same length, and a preview that says they are equally safe is lying about
 * the only thing it was asked.
 *
 * The table is Arial's own advance widths in units of 1/1000 em, which is what
 * Helvetica-metric fonts have used since PostScript. It is not the exact font any
 * one client ships — Google renders Arial on the desktop web and Roboto on Android,
 * Apple Mail is on SF — but they are all humanist sans faces within a few percent
 * of these advances, and the alternative (shipping a font rasteriser to a tool that
 * draws a rectangle) buys nothing a reader could see.
 */
final class TextWidth
{
    /** Advance widths for Arial regular, in 1/1000 em. */
    private const ADVANCE = [
        ' ' => 278, '!' => 278, '"' => 355, '#' => 556, '$' => 556, '%' => 889, '&' => 667,
        "'" => 191, '(' => 333, ')' => 333, '*' => 389, '+' => 584, ',' => 278, '-' => 333,
        '.' => 278, '/' => 278,
        '0' => 556, '1' => 556, '2' => 556, '3' => 556, '4' => 556,
        '5' => 556, '6' => 556, '7' => 556, '8' => 556, '9' => 556,
        ':' => 278, ';' => 278, '<' => 584, '=' => 584, '>' => 584, '?' => 556, '@' => 1015,
        'A' => 667, 'B' => 667, 'C' => 722, 'D' => 722, 'E' => 667, 'F' => 611, 'G' => 778,
        'H' => 722, 'I' => 278, 'J' => 500, 'K' => 667, 'L' => 556, 'M' => 833, 'N' => 722,
        'O' => 778, 'P' => 667, 'Q' => 778, 'R' => 722, 'S' => 667, 'T' => 611, 'U' => 722,
        'V' => 667, 'W' => 944, 'X' => 667, 'Y' => 667, 'Z' => 611,
        '[' => 278, '\\' => 278, ']' => 278, '^' => 469, '_' => 556, '`' => 333,
        'a' => 556, 'b' => 556, 'c' => 500, 'd' => 556, 'e' => 556, 'f' => 278, 'g' => 556,
        'h' => 556, 'i' => 222, 'j' => 222, 'k' => 500, 'l' => 222, 'm' => 833, 'n' => 556,
        'o' => 556, 'p' => 556, 'q' => 556, 'r' => 333, 's' => 500, 't' => 278, 'u' => 556,
        'v' => 500, 'w' => 722, 'x' => 500, 'y' => 500, 'z' => 500,
        '{' => 334, '|' => 260, '}' => 334, '~' => 584,
    ];

    /** Anything outside the table: a Latin letter with a diacritic, a quote, a dash. */
    private const DEFAULT_ADVANCE = 556;

    /** An emoji occupies a full em box in every client that draws one. */
    private const EMOJI_ADVANCE = 1000;

    /** CJK is full-width by definition, which is why a Japanese subject line fits so few words. */
    private const WIDE_ADVANCE = 1000;

    public static function px(string $text, float $fontSize): float
    {
        $total = 0.0;

        foreach (self::graphemes($text) as $character) {
            $total += self::advance($character);
        }

        return $total / 1000 * $fontSize;
    }

    /**
     * The longest prefix of `$text` that fits in `$maxPx`, and the rest.
     *
     * The cut lands on a word boundary when one is available in the last quarter of
     * the fitted text, because that is what the clients themselves do — Google
     * ellipsises a whole word, not a half of one. When no boundary is near enough
     * (one very long word, or a URL) the cut is exact, which is also what they do.
     *
     * @return array{visible: string, hidden: string, width: float, truncated: bool}
     */
    public static function fit(string $text, float $maxPx, float $fontSize): array
    {
        $width = self::px($text, $fontSize);

        if ($width <= $maxPx) {
            return ['visible' => $text, 'hidden' => '', 'width' => $width, 'truncated' => false];
        }

        $graphemes = self::graphemes($text);
        $fitted = '';
        $used = 0.0;

        foreach ($graphemes as $character) {
            $advance = self::advance($character) / 1000 * $fontSize;

            if ($used + $advance > $maxPx) {
                break;
            }

            $fitted .= $character;
            $used += $advance;
        }

        $boundary = mb_strrpos($fitted, ' ');

        if ($boundary !== false && $boundary > mb_strlen($fitted) * 0.75) {
            $fitted = mb_substr($fitted, 0, $boundary);
        }

        return [
            'visible' => rtrim($fitted),
            'hidden' => mb_substr($text, mb_strlen($fitted)),
            'width' => $width,
            'truncated' => true,
        ];
    }

    /** @return list<string> */
    private static function graphemes(string $text): array
    {
        $split = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);

        return $split === false ? [] : $split;
    }

    private static function advance(string $character): int
    {
        if (isset(self::ADVANCE[$character])) {
            return self::ADVANCE[$character];
        }

        $codepoint = mb_ord($character, 'UTF-8');

        // Emoji and pictographs.
        if ($codepoint >= 0x1F000 || ($codepoint >= 0x2190 && $codepoint <= 0x2BFF)) {
            return self::EMOJI_ADVANCE;
        }

        // CJK, Hangul, kana and the full-width Latin block.
        if (($codepoint >= 0x1100 && $codepoint <= 0x11FF)
            || ($codepoint >= 0x2E80 && $codepoint <= 0xA4CF)
            || ($codepoint >= 0xAC00 && $codepoint <= 0xD7AF)
            || ($codepoint >= 0xF900 && $codepoint <= 0xFAFF)
            || ($codepoint >= 0xFF00 && $codepoint <= 0xFF60)) {
            return self::WIDE_ADVANCE;
        }

        return self::DEFAULT_ADVANCE;
    }
}
