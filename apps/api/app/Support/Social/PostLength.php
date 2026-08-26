<?php

declare(strict_types=1);

namespace App\Support\Social;

/**
 * Character counting the way social platforms actually do it.
 *
 * A naïve `strlen` is wrong on every platform that matters: X weights CJK
 * characters double and counts every URL as a fixed 23 regardless of length, and
 * emoji are multi-codepoint sequences that count as one character to a user and
 * several to `mb_strlen`. Getting this wrong means telling someone their post fits
 * when it does not.
 */
final class PostLength
{
    /** X counts every link as this many characters, however long it really is. */
    private const URL_WEIGHT = 23;

    /**
     * X's weighted count: most characters count 1, CJK and full-width count 2.
     */
    public static function weighted(string $text): int
    {
        $urlCount = 0;
        $text = self::normaliseUrls($text, $urlCount);
        $weight = 0;

        foreach (self::graphemes($text) as $grapheme) {
            $weight += self::graphemeWeight($grapheme);
        }

        return $weight + ($urlCount * self::URL_WEIGHT);
    }

    /** Plain user-perceived character count, used by platforms without weighting. */
    public static function graphemeCount(string $text): int
    {
        return count(self::graphemes($text));
    }

    /**
     * @return list<string>
     */
    public static function graphemes(string $text): array
    {
        if (function_exists('grapheme_str_split')) {
            $split = grapheme_str_split($text);

            if ($split !== false) {
                return array_values($split);
            }
        }

        // Fallback: split on extended grapheme clusters so emoji sequences such as
        // 👨‍👩‍👧‍👦 count as one character, not seven.
        $split = preg_split('/\X\K/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        return $split === false ? [] : $split;
    }

    /** Strips URLs and reports how many were found, so they can be weighted flat. */
    private static function normaliseUrls(string $text, int &$count): string
    {
        $result = preg_replace_callback(
            '#\bhttps?://\S+#iu',
            fn () => '',
            $text,
            -1,
            $count,
        );

        return $result ?? $text;
    }

    private static function graphemeWeight(string $grapheme): int
    {
        $codepoint = mb_ord($grapheme, 'UTF-8');

        // CJK, Hangul, Hiragana/Katakana and full-width forms count double.
        $doubleWidth = ($codepoint >= 0x1100 && $codepoint <= 0x115F)
            || ($codepoint >= 0x2E80 && $codepoint <= 0xA4CF)
            || ($codepoint >= 0xAC00 && $codepoint <= 0xD7A3)
            || ($codepoint >= 0xF900 && $codepoint <= 0xFAFF)
            || ($codepoint >= 0xFE30 && $codepoint <= 0xFE6F)
            || ($codepoint >= 0xFF00 && $codepoint <= 0xFF60)
            || ($codepoint >= 0x20000 && $codepoint <= 0x3FFFD);

        return $doubleWidth ? 2 : 1;
    }
}
