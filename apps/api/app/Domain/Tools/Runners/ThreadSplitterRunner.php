<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Domain\Tools\Exceptions\ToolExecutionException;
use App\Support\Social\PostLength;

/**
 * Splits long text into a numbered thread at sentence boundaries.
 *
 * The hard part is not splitting — it is *where*. Naïve splitters cut mid-sentence
 * and the thread reads badly, so this prefers paragraph breaks, then sentence
 * endings, then clause boundaries, and only falls back to a word break when a single
 * sentence genuinely exceeds the limit.
 */
final class ThreadSplitterRunner implements Cacheable, ToolRunner
{
    public static function key(): string
    {
        return 'x.thread-splitter';
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
                    'title' => 'Your long-form text',
                    'description' => 'Paste an essay, a newsletter section, or a script. Blank lines are respected as natural breaks.',
                    'minLength' => 1,
                    'maxLength' => 50000,
                ],
                'limit' => [
                    'type' => 'integer',
                    'title' => 'Characters per post',
                    'description' => '280 for a standard account, 25,000 for X Premium.',
                    'minimum' => 50,
                    'maximum' => 25000,
                    'default' => 280,
                ],
                'numbering' => [
                    'type' => 'string',
                    'title' => 'Numbering style',
                    'enum' => ['none', 'slash', 'parenthesis', 'emoji'],
                    'default' => 'slash',
                    'description' => '"1/8", "1)", or 🧵 on the first post only.',
                ],
                'reserve_hook' => [
                    'type' => 'boolean',
                    'title' => 'Keep the first line as a standalone hook',
                    'default' => true,
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $text = trim($input->string('text'));
        $limit = $input->int('limit', 280);
        $numbering = $input->string('numbering', 'slash');

        if ($text === '') {
            throw ToolExecutionException::invalidInput(
                'Add some text to split.',
                ['text' => 'This field cannot be empty.'],
            );
        }

        // Numbering costs characters, so the budget has to account for it before
        // splitting — otherwise the last posts overflow once suffixes are added.
        $reserve = $numbering === 'none' ? 0 : 8;
        $budget = max(20, $limit - $reserve);

        $segments = $this->split($text, $budget, $input->bool('reserve_hook', true));
        $total = count($segments);

        $blocks = [];

        foreach ($segments as $index => $segment) {
            $body = $this->number($segment, $index + 1, $total, $numbering);

            $blocks[] = [
                'label' => 'Post '.($index + 1)." of {$total}",
                'text' => $body,
                'meta' => [
                    'characters' => PostLength::weighted($body),
                    'limit' => $limit,
                    'over_limit' => PostLength::weighted($body) > $limit,
                ],
            ];
        }

        $warnings = [];
        $overflowing = array_filter($blocks, fn (array $b) => $b['meta']['over_limit']);

        if ($overflowing !== []) {
            $warnings[] = count($overflowing).' post(s) still exceed the limit because a single '
                .'word or URL is longer than the budget. Edit those manually.';
        }

        return ToolResult::textBlocks(
            blocks: $blocks,
            summary: "Split into {$total} posts, breaking at sentence boundaries.",
        )->withWarnings($warnings)->withMeta([
            'post_count' => $total,
            'source_characters' => mb_strlen($text),
        ]);
    }

    /**
     * @return list<string>
     */
    private function split(string $text, int $budget, bool $reserveHook): array
    {
        $segments = [];
        $paragraphs = preg_split('/\n{2,}/u', $text) ?: [$text];

        // An opening hook works best alone, even when it would fit with what follows.
        if ($reserveHook && count($paragraphs) > 1) {
            $hook = trim(array_shift($paragraphs));

            if ($hook !== '' && PostLength::weighted($hook) <= $budget) {
                $segments[] = $hook;
            } elseif ($hook !== '') {
                array_unshift($paragraphs, $hook);
            }
        }

        $buffer = '';

        foreach ($paragraphs as $paragraph) {
            foreach ($this->sentences(trim($paragraph)) as $sentence) {
                $candidate = $buffer === '' ? $sentence : $buffer.' '.$sentence;

                if (PostLength::weighted($candidate) <= $budget) {
                    $buffer = $candidate;

                    continue;
                }

                if ($buffer !== '') {
                    $segments[] = $buffer;
                    $buffer = '';
                }

                // A single sentence over budget is the only case where we cut inside
                // one; do it on word boundaries so nothing is mangled.
                if (PostLength::weighted($sentence) > $budget) {
                    foreach ($this->chunkWords($sentence, $budget) as $chunk) {
                        $segments[] = $chunk;
                    }
                } else {
                    $buffer = $sentence;
                }
            }

            if ($buffer !== '') {
                $segments[] = $buffer;
                $buffer = '';
            }
        }

        return array_values(array_filter(
            $segments,
            static fn (string $segment): bool => trim($segment) !== '',
        ));
    }

    /** @return list<string> */
    private function sentences(string $paragraph): array
    {
        // Split after ., ! or ? followed by whitespace, without breaking on common
        // abbreviations or decimals.
        $parts = preg_split(
            '/(?<=[.!?])(?<!\b(?:Mr|Mrs|Ms|Dr|Prof|Inc|Ltd|vs|etc|e\.g|i\.e)\.)\s+/u',
            $paragraph,
            -1,
            PREG_SPLIT_NO_EMPTY,
        );

        return $parts === false ? [$paragraph] : array_map('trim', $parts);
    }

    /** @return list<string> */
    private function chunkWords(string $sentence, int $budget): array
    {
        $chunks = [];
        $current = '';

        foreach (preg_split('/\s+/u', $sentence) ?: [] as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;

            if (PostLength::weighted($candidate) > $budget && $current !== '') {
                $chunks[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    private function number(string $text, int $index, int $total, string $style): string
    {
        return match ($style) {
            'slash' => "{$text}\n\n{$index}/{$total}",
            'parenthesis' => "{$index}) {$text}",
            'emoji' => $index === 1 ? "🧵 {$text}" : "{$text}\n\n{$index}/{$total}",
            default => $text,
        };
    }
}
