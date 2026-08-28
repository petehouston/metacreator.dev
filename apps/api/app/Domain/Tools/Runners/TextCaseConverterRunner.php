<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use Illuminate\Support\Str;

/**
 * Every casing at once, each with its own copy button.
 *
 * Converting one way at a time is the wrong interaction: people rarely know which
 * casing they want until they see it next to the alternatives.
 */
final class TextCaseConverterRunner implements Cacheable, ToolRunner
{
    /** Words that stay lowercase inside a title, unless they start or end it. */
    private const MINOR_WORDS = [
        'a', 'an', 'and', 'as', 'at', 'but', 'by', 'for', 'from', 'in', 'nor', 'of',
        'on', 'or', 'per', 'the', 'to', 'via', 'vs', 'with',
    ];

    public static function key(): string
    {
        return 'content.text-case-converter';
    }

    public function cacheTtl(): int
    {
        return 3600;
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
                    'maxLength' => 20000,
                    'examples' => ['how i grew to 100k subscribers in 9 months'],
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $text = trim($input->string('text'));
        $words = $text === '' ? [] : (preg_split('/[\s_-]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $lower = array_map(fn (string $w) => mb_strtolower($w), $words);

        return ToolResult::textBlocks([
            ['label' => 'UPPERCASE', 'text' => mb_strtoupper($text)],
            ['label' => 'lowercase', 'text' => mb_strtolower($text)],
            ['label' => 'Title Case', 'text' => $this->titleCase($words)],
            ['label' => 'Sentence case', 'text' => $this->sentenceCase($text)],
            ['label' => 'camelCase', 'text' => $this->camel($lower)],
            ['label' => 'PascalCase', 'text' => implode('', array_map(fn (string $w) => Str::ucfirst($w), $lower))],
            ['label' => 'snake_case', 'text' => implode('_', $lower)],
            ['label' => 'kebab-case', 'text' => implode('-', $lower)],
            ['label' => 'CONSTANT_CASE', 'text' => mb_strtoupper(implode('_', $lower))],
            ['label' => 'aLtErNaTiNg cAsE', 'text' => $this->alternating($text)],
            ['label' => 'Reversed', 'text' => $this->reverse($text)],
        ], summary: $text === ''
            ? 'Enter some text to convert.'
            : count($words).' words converted into 11 casings — copy the one you need.');
    }

    /** @param list<string> $words */
    private function titleCase(array $words): string
    {
        $last = count($words) - 1;

        return implode(' ', array_map(function (string $word, int $index) use ($last): string {
            $lower = mb_strtolower($word);

            return $index !== 0 && $index !== $last && in_array($lower, self::MINOR_WORDS, true)
                ? $lower
                : Str::ucfirst($lower);
        }, $words, array_keys($words)));
    }

    private function sentenceCase(string $text): string
    {
        $lower = mb_strtolower($text);

        return preg_replace_callback(
            '/(^|[.!?]\s+)(\p{L})/u',
            fn (array $m) => $m[1].mb_strtoupper($m[2]),
            $lower,
        ) ?? $lower;
    }

    /** @param list<string> $words */
    private function camel(array $words): string
    {
        if ($words === []) {
            return '';
        }

        return $words[0].implode('', array_map(fn (string $w) => Str::ucfirst($w), array_slice($words, 1)));
    }

    private function alternating(string $text): string
    {
        $out = '';
        $upper = false;

        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            if (preg_match('/\p{L}/u', $char) === 1) {
                $out .= $upper ? mb_strtoupper($char) : mb_strtolower($char);
                $upper = ! $upper;

                continue;
            }

            $out .= $char;
        }

        return $out;
    }

    private function reverse(string $text): string
    {
        return implode('', array_reverse(preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []));
    }
}
