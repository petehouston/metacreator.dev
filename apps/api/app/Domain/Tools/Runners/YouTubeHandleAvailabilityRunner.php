<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Contracts\UsesProvider;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Domain\Tools\Exceptions\ToolExecutionException;
use App\Support\Http\SafeHttpClient;

/**
 * Whether a YouTube handle is free, answered rather than guessed.
 *
 * YouTube is one of the few platforms that will tell you: `/@handle` returns 200
 * when somebody holds it and 404 when nobody does, with no login wall in the way.
 * That makes a definite answer possible here, unlike the general username checker,
 * where four of the eight networks can only be linked to.
 *
 * When a handle is taken the tool checks a short list of variants too, because the
 * next question is always "what should I use instead".
 */
final class YouTubeHandleAvailabilityRunner implements Cacheable, ToolRunner, UsesProvider
{
    /** Suffixes worth trying, ordered by how well they read on a channel page. */
    private const VARIANTS = ['hq', 'tv', 'official', 'studio', 'daily'];

    private const MAX_VARIANTS = 4;

    public static function key(): string
    {
        return 'youtube.handle-availability';
    }

    public function providers(): array
    {
        return ['youtube'];
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
            'required' => ['handle'],
            'additionalProperties' => false,
            'properties' => [
                'handle' => [
                    'type' => 'string',
                    'title' => 'Handle to check',
                    'description' => 'With or without the @. YouTube allows 3–30 characters: letters, '
                        .'numbers, underscores, hyphens and periods.',
                    'minLength' => 1,
                    'maxLength' => 40,
                    'examples' => ['theslowloaf'],
                ],
                'suggest_variants' => [
                    'type' => 'boolean',
                    'title' => 'Suggest alternatives if it is taken',
                    'default' => true,
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $handle = ltrim(trim($input->string('handle')), '@');

        $problem = $this->ruleViolation($handle);

        if ($problem !== null) {
            return ToolResult::table(
                columns: $this->columns(),
                rows: [[
                    'handle' => '@'.$handle,
                    'status' => 'Invalid',
                    'detail' => $problem,
                    'url' => '',
                ]],
                summary: "“@{$handle}” is not a handle YouTube will accept, so availability does not arise.",
            )->withMeta(['handle' => $handle, 'valid' => false]);
        }

        $taken = $this->isTaken($handle);
        $rows = [$this->row($handle, $taken)];

        if ($taken === true && $input->bool('suggest_variants', true)) {
            foreach ($this->variants($handle) as $variant) {
                $rows[] = $this->row($variant, $this->isTaken($variant));
            }
        }

        $free = array_values(array_filter(
            array_slice($rows, 1),
            fn (array $row) => $row['status'] === 'Available',
        ));

        return ToolResult::table(
            columns: $this->columns(),
            rows: $rows,
            summary: match (true) {
                $taken === null => "We could not reach YouTube to check “@{$handle}”. Try again shortly — "
                    .'reporting it as available without knowing would be worse than reporting nothing.',
                $taken === false => "“@{$handle}” is available. Claim it from YouTube Studio → Customisation → Basic info.",
                $free !== [] => "“@{$handle}” is taken. {$free[0]['handle']} is free.",
                default => "“@{$handle}” is taken, and so is every variant we tried.",
            },
        )->withMeta([
            'handle' => $handle,
            'valid' => true,
            'available' => $taken === false,
        ])->withWarnings([
            'A free handle is not automatically yours to keep. Trademarked and impersonating names get '
            .'reclaimed after the fact, however cleanly they register today.',
        ]);
    }

    /** @return list<array{key: string, label: string, align?: string}> */
    private function columns(): array
    {
        return [
            ['key' => 'handle', 'label' => 'Handle'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'detail', 'label' => 'Detail'],
            ['key' => 'url', 'label' => 'Open', 'align' => 'right'],
        ];
    }

    /** @return array<string, string> */
    private function row(string $handle, ?bool $taken): array
    {
        return [
            'handle' => '@'.$handle,
            'status' => match ($taken) {
                true => 'Taken',
                false => 'Available',
                null => 'Unknown',
            },
            'detail' => match ($taken) {
                true => 'A channel already uses this handle.',
                false => 'YouTube has no channel at this handle.',
                null => 'YouTube did not answer. This is not a “no”.',
            },
            'url' => $taken === true ? "https://www.youtube.com/@{$handle}" : '',
        ];
    }

    /**
     * True when held, false when free, null when we could not tell.
     *
     * The third state matters: a timeout must never be reported as "available",
     * because somebody will act on it and lose the name to whoever really has it.
     */
    private function isTaken(string $handle): ?bool
    {
        $response = SafeHttpClient::attempt("https://www.youtube.com/@{$handle}", timeout: 5.0);

        if ($response === null) {
            return null;
        }

        return match (true) {
            $response->status() === 404 => false,
            $response->successful() => true,
            default => null,
        };
    }

    /** @return list<string> */
    private function variants(string $handle): array
    {
        $variants = array_map(fn (string $suffix) => $handle.$suffix, self::VARIANTS);

        // A handle can only be 30 characters, so a suffix that overflows is not a
        // suggestion — it is a second failure.
        $variants = array_values(array_filter($variants, fn (string $v) => mb_strlen($v) <= 30));

        return array_slice($variants, 0, self::MAX_VARIANTS);
    }

    /** YouTube's own handle rules, checked before we spend a request. */
    private function ruleViolation(string $handle): ?string
    {
        if ($handle === '') {
            throw ToolExecutionException::invalidInput(
                'Enter a handle to check.',
                ['handle' => 'A handle is required.'],
            );
        }

        $length = mb_strlen($handle);

        return match (true) {
            $length < 3 => 'Too short — handles are at least 3 characters.',
            $length > 30 => 'Too long — handles are at most 30 characters.',
            preg_match('/^[A-Za-z0-9._-]+$/', $handle) !== 1 => 'Contains a character YouTube does not allow. Only letters, numbers, underscores, '
                    .'hyphens and periods are permitted.',
            preg_match('/^[0-9.]+$/', $handle) === 1 => 'A handle cannot be only digits and periods — YouTube rejects anything that reads as '
                    .'a phone number or a URL.',
            default => null,
        };
    }
}
