<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;

/**
 * Styled text for bios and captions, built from real Unicode characters.
 *
 * Nothing here is a font: these are separate Unicode codepoints, which is why they
 * survive being pasted into an Instagram bio. It is also why screen readers cope
 * badly with them — the result says so rather than pretending otherwise.
 */
final class FancyTextGeneratorRunner implements Cacheable, ToolRunner
{
    /**
     * Each style is the codepoint that 'A', 'a' and '0' map to. `null` digits mean
     * the style has no digit forms, so digits pass through unchanged.
     *
     * @var array<string, array{label: string, upper: int, lower: int, digit: int|null, holes?: array<string, string>}>
     */
    private const STYLES = [
        'bold' => ['label' => 'Bold', 'upper' => 0x1D400, 'lower' => 0x1D41A, 'digit' => 0x1D7CE],
        'italic' => ['label' => 'Italic', 'upper' => 0x1D434, 'lower' => 0x1D44E, 'digit' => null,
            'holes' => ['h' => 'ℎ']],
        'bold_italic' => ['label' => 'Bold italic', 'upper' => 0x1D468, 'lower' => 0x1D482, 'digit' => null],
        'sans_bold' => ['label' => 'Sans bold', 'upper' => 0x1D5D4, 'lower' => 0x1D5EE, 'digit' => 0x1D7EC],
        'script' => ['label' => 'Script', 'upper' => 0x1D49C, 'lower' => 0x1D4B6, 'digit' => null,
            'holes' => ['B' => 'ℬ', 'E' => 'ℰ', 'F' => 'ℱ', 'H' => 'ℋ', 'I' => 'ℐ', 'L' => 'ℒ',
                'M' => 'ℳ', 'R' => 'ℛ', 'e' => 'ℯ', 'g' => 'ℊ', 'o' => 'ℴ']],
        'fraktur' => ['label' => 'Fraktur', 'upper' => 0x1D504, 'lower' => 0x1D51E, 'digit' => null,
            'holes' => ['C' => 'ℭ', 'H' => 'ℌ', 'I' => 'ℑ', 'R' => 'ℜ', 'Z' => 'ℨ']],
        'double_struck' => ['label' => 'Outline', 'upper' => 0x1D538, 'lower' => 0x1D552, 'digit' => 0x1D7D8,
            'holes' => ['C' => 'ℂ', 'H' => 'ℍ', 'N' => 'ℕ', 'P' => 'ℙ', 'Q' => 'ℚ', 'R' => 'ℝ', 'Z' => 'ℤ']],
        'monospace' => ['label' => 'Monospace', 'upper' => 0x1D670, 'lower' => 0x1D68A, 'digit' => 0x1D7F6],
        'fullwidth' => ['label' => 'Wide', 'upper' => 0xFF21, 'lower' => 0xFF41, 'digit' => 0xFF10],
        'circled' => ['label' => 'Circled', 'upper' => 0x24B6, 'lower' => 0x24D0, 'digit' => null],
    ];

    /** Letters that have a dedicated small-capital form. */
    private const SMALL_CAPS = [
        'a' => 'ᴀ', 'b' => 'ʙ', 'c' => 'ᴄ', 'd' => 'ᴅ', 'e' => 'ᴇ', 'f' => 'ꜰ', 'g' => 'ɢ',
        'h' => 'ʜ', 'i' => 'ɪ', 'j' => 'ᴊ', 'k' => 'ᴋ', 'l' => 'ʟ', 'm' => 'ᴍ', 'n' => 'ɴ',
        'o' => 'ᴏ', 'p' => 'ᴘ', 'q' => 'ǫ', 'r' => 'ʀ', 's' => 'ѕ', 't' => 'ᴛ', 'u' => 'ᴜ',
        'v' => 'ᴠ', 'w' => 'ᴡ', 'x' => 'x', 'y' => 'ʏ', 'z' => 'ᴢ',
    ];

    private const UPSIDE_DOWN = [
        'a' => 'ɐ', 'b' => 'q', 'c' => 'ɔ', 'd' => 'p', 'e' => 'ǝ', 'f' => 'ɟ', 'g' => 'ƃ',
        'h' => 'ɥ', 'i' => 'ᴉ', 'j' => 'ɾ', 'k' => 'ʞ', 'l' => 'l', 'm' => 'ɯ', 'n' => 'u',
        'o' => 'o', 'p' => 'd', 'q' => 'b', 'r' => 'ɹ', 's' => 's', 't' => 'ʇ', 'u' => 'n',
        'v' => 'ʌ', 'w' => 'ʍ', 'x' => 'x', 'y' => 'ʎ', 'z' => 'z',
        '1' => 'Ɩ', '2' => 'ᄅ', '3' => 'Ɛ', '4' => 'ㄣ', '5' => 'ϛ', '6' => '9', '7' => 'ㄥ',
        '8' => '8', '9' => '6', '0' => '0', '.' => '˙', ',' => "'", '?' => '¿', '!' => '¡',
        "'" => ',', '"' => ',,', '(' => ')', ')' => '(', '[' => ']', ']' => '[', '&' => '⅋',
    ];

    public static function key(): string
    {
        return 'content.fancy-text-generator';
    }

    public function cacheTtl(): int
    {
        return 86400;
    }

    public function inputSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'required' => ['text'],
            'additionalProperties' => false,
            'properties' => [
                'text' => [
                    'type' => 'string',
                    'title' => 'Your text',
                    'description' => 'A name, a bio line, a caption — anything you want styled.',
                    'minLength' => 1,
                    'maxLength' => 200,
                    'examples' => ['creator studio'],
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $text = $input->string('text');
        $items = [];

        foreach (self::STYLES as $style) {
            $items[] = [
                'title' => $style['label'],
                'body' => $this->map($text, $style),
            ];
        }

        $items[] = ['title' => 'Small caps', 'body' => $this->table($text, self::SMALL_CAPS)];
        $items[] = ['title' => 'Upside down', 'body' => $this->flip($text)];
        $items[] = ['title' => 'Strikethrough', 'body' => $this->combine($text, "\u{0336}")];
        $items[] = ['title' => 'Underline', 'body' => $this->combine($text, "\u{0332}")];
        $items[] = ['title' => 'Spaced out', 'body' => implode(' ', $this->chars($text))];

        return ToolResult::cards($items, summary: count($items).' styles — tap any one to copy it.')
            ->withWarnings([
                'Styled text is not a font: screen readers often read it letter by letter or skip it. '
                .'Keep your name and key information in plain text.',
            ]);
    }

    /** @param array{upper: int, lower: int, digit: int|null, holes?: array<string, string>} $style */
    private function map(string $text, array $style): string
    {
        $out = '';

        foreach ($this->chars($text) as $char) {
            $hole = $style['holes'][$char] ?? null;

            if ($hole !== null) {
                $out .= $hole;

                continue;
            }

            $out .= match (true) {
                $char >= 'A' && $char <= 'Z' => mb_chr($style['upper'] + (ord($char) - 65), 'UTF-8'),
                $char >= 'a' && $char <= 'z' => mb_chr($style['lower'] + (ord($char) - 97), 'UTF-8'),
                $char >= '0' && $char <= '9' && $style['digit'] !== null => mb_chr($style['digit'] + (ord($char) - 48), 'UTF-8'),
                default => $char,
            };
        }

        return $out;
    }

    /** @param array<array-key, string> $map */
    private function table(string $text, array $map): string
    {
        $out = '';

        foreach ($this->chars($text) as $char) {
            $out .= $map[mb_strtolower($char)] ?? $char;
        }

        return $out;
    }

    private function flip(string $text): string
    {
        return implode('', array_reverse($this->chars($this->table($text, self::UPSIDE_DOWN))));
    }

    private function combine(string $text, string $mark): string
    {
        return implode('', array_map(fn (string $char) => $char.$mark, $this->chars($text)));
    }

    /** @return list<string> */
    private function chars(string $text): array
    {
        return preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
