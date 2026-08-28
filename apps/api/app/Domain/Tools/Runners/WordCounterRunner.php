<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Support\Social\PostLength;

/**
 * Words, characters, sentences and how long the thing takes to read or say.
 *
 * Deliberately separate from the character counter: that tool answers "does this
 * fit?", this one answers "how long is this?" — a different question with a
 * different audience (scripts, articles, essays).
 */
final class WordCounterRunner implements Cacheable, ToolRunner
{
    public static function key(): string
    {
        return 'content.word-counter';
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
                    'description' => 'Paste anything — a caption, a script, a whole article.',
                    'maxLength' => 200000,
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $text = trim($input->string('text'));

        $words = $text === '' ? [] : (preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $wordCount = count($words);
        $characters = PostLength::graphemeCount($text);
        $withoutSpaces = PostLength::graphemeCount(preg_replace('/\s+/u', '', $text) ?? '');
        $sentences = $text === '' ? 0 : max(1, preg_match_all('/[.!?]+(\s|$)/u', $text));
        $paragraphs = $text === '' ? 0 : count(preg_split('/\n\s*\n/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []);

        $unique = count(array_unique(array_map(
            fn (string $word) => mb_strtolower(trim($word, ".,!?;:\"'()[]—–-")),
            $words,
        )));

        return ToolResult::keyValue([
            ['label' => 'Words', 'value' => number_format($wordCount)],
            ['label' => 'Characters', 'value' => number_format($characters), 'hint' => number_format($withoutSpaces).' without spaces'],
            ['label' => 'Sentences', 'value' => number_format($sentences)],
            ['label' => 'Paragraphs', 'value' => number_format($paragraphs)],
            ['label' => 'Unique words', 'value' => number_format($unique),
                'hint' => $wordCount > 0 ? round(($unique / $wordCount) * 100).'% of the total' : 'Nothing counted yet'],
            ['label' => 'Average sentence', 'value' => $sentences > 0 ? round($wordCount / $sentences, 1).' words' : '—',
                'hint' => 'Under 20 reads comfortably.'],
            ['label' => 'Reading time', 'value' => $this->duration((int) ceil($wordCount / 238 * 60)),
                'hint' => 'At 238 words per minute, silent reading.'],
            ['label' => 'Speaking time', 'value' => $this->duration((int) ceil($wordCount / 140 * 60)),
                'hint' => 'At 140 words per minute, spoken aloud.'],
        ], summary: $wordCount === 0
            ? 'Nothing to count yet — paste some text above.'
            : number_format($wordCount).' words · '.number_format($characters).' characters.')
            ->withMeta(['words' => $wordCount, 'characters' => $characters]);
    }

    private function duration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds} sec";
        }

        $minutes = intdiv($seconds, 60);
        $rest = $seconds % 60;

        return $rest === 0 ? "{$minutes} min" : "{$minutes} min {$rest} sec";
    }
}
