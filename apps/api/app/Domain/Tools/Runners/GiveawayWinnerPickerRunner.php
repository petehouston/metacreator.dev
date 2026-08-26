<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Domain\Tools\Exceptions\ToolExecutionException;

/**
 * Picks giveaway winners in a way entrants can verify.
 *
 * A random picker whose output cannot be checked is worthless the moment someone
 * accuses you of rigging it. This derives the draw deterministically from a seed you
 * publish beforehand, so anyone can re-run it and get the same winners — which is
 * the entire point of the tool.
 *
 * Deliberately NOT cacheable: an unseeded draw must produce a fresh result each run.
 */
final class GiveawayWinnerPickerRunner implements ToolRunner
{
    public static function key(): string
    {
        return 'utility.giveaway-winner-picker';
    }

    public function inputSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'required' => ['entries'],
            'additionalProperties' => false,
            'properties' => [
                'entries' => [
                    'type' => 'string',
                    'title' => 'Entries',
                    'description' => 'One entrant per line. Usernames, emails, or comment text — whatever you collected.',
                    'minLength' => 1,
                    'maxLength' => 200000,
                ],
                'winners' => [
                    'type' => 'integer',
                    'title' => 'Number of winners',
                    'minimum' => 1,
                    'maximum' => 100,
                    'default' => 1,
                ],
                'runners_up' => [
                    'type' => 'integer',
                    'title' => 'Runners-up to draw',
                    'description' => 'Useful when a winner does not claim the prize.',
                    'minimum' => 0,
                    'maximum' => 100,
                    'default' => 2,
                ],
                'deduplicate' => [
                    'type' => 'boolean',
                    'title' => 'Remove duplicate entries',
                    'description' => 'Off means each repeat entry is an extra chance to win.',
                    'default' => true,
                ],
                'seed' => [
                    'type' => 'string',
                    'title' => 'Verification seed (optional)',
                    'description' => 'Publish a seed before drawing — anyone can then re-run this and confirm the result. Leave blank for a one-off random draw.',
                    'maxLength' => 100,
                    'default' => '',
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $entries = $this->parse($input->string('entries'), $input->bool('deduplicate', true));

        if ($entries === []) {
            throw ToolExecutionException::invalidInput(
                'No entries found. Add one entrant per line.',
                ['entries' => 'This field must contain at least one entry.'],
            );
        }

        $winnerCount = min($input->int('winners', 1), count($entries));
        $runnerUpCount = min($input->int('runners_up', 2), count($entries) - $winnerCount);

        $seed = trim($input->string('seed'));
        $isVerifiable = $seed !== '';

        // A published seed makes the draw reproducible; without one we use the CSPRNG.
        $order = $isVerifiable
            ? $this->seededShuffle($entries, $seed)
            : $this->secureShuffle($entries);

        $winners = array_slice($order, 0, $winnerCount);
        $runnersUp = array_slice($order, $winnerCount, $runnerUpCount);

        $items = [];

        foreach ($winners as $index => $winner) {
            $items[] = [
                'title' => 'Winner '.($index + 1),
                'body' => $winner,
                'meta' => ['emphasis' => true],
            ];
        }

        foreach ($runnersUp as $index => $entrant) {
            $items[] = [
                'title' => 'Runner-up '.($index + 1),
                'body' => $entrant,
                'meta' => ['muted' => true],
            ];
        }

        $warnings = $isVerifiable
            ? []
            : ['This draw used a random seed, so it cannot be re-verified. For a public giveaway, '
                .'publish a seed first and enter it here — entrants can then reproduce the result themselves.'];

        return ToolResult::cards(
            items: $items,
            summary: sprintf(
                'Drew %d winner%s from %s entries%s.',
                $winnerCount,
                $winnerCount === 1 ? '' : 's',
                number_format(count($entries)),
                $isVerifiable ? " using seed \"{$seed}\"" : '',
            ),
        )->withWarnings($warnings)->withMeta([
            'entry_count' => count($entries),
            'verifiable' => $isVerifiable,
            'seed' => $isVerifiable ? $seed : null,
            'entries_checksum' => substr(hash('sha256', implode("\n", $entries)), 0, 16),
        ]);
    }

    /** @return list<string> */
    private function parse(string $raw, bool $deduplicate): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];

        $entries = [];

        foreach ($lines as $line) {
            $entry = trim($line);

            if ($entry !== '') {
                $entries[] = $entry;
            }
        }

        return $deduplicate ? array_values(array_unique($entries)) : $entries;
    }

    /**
     * Fisher–Yates driven by a hash chain of the seed.
     *
     * Deterministic and independent of PHP's RNG implementation, so the same seed and
     * entry list give the same winners on any machine, in any version — which is what
     * makes third-party verification possible.
     *
     * @param  list<string>  $entries
     * @return list<string>
     */
    private function seededShuffle(array $entries, string $seed): array
    {
        // Binding the entry list into the seed material means a seed published in
        // advance cannot be reused to fish for a favourable list.
        $material = hash('sha256', $seed.'|'.implode("\n", $entries), true);
        $counter = 0;

        for ($i = count($entries) - 1; $i > 0; $i--) {
            $bytes = hash('sha256', $material.pack('N', $counter++), true);
            $value = unpack('N', substr($bytes, 0, 4))[1] ?? 0;
            $j = $value % ($i + 1);

            [$entries[$i], $entries[$j]] = [$entries[$j], $entries[$i]];
        }

        return array_values($entries);
    }

    /**
     * @param  list<string>  $entries
     * @return list<string>
     */
    private function secureShuffle(array $entries): array
    {
        for ($i = count($entries) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$entries[$i], $entries[$j]] = [$entries[$j], $entries[$i]];
        }

        return array_values($entries);
    }
}
