<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Domain\Tools\Exceptions\ToolExecutionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * One handle, checked against every network at once.
 *
 * Two things happen per network, and they are worth separating. First the handle is
 * checked against that platform's **rules** — length, allowed characters, dots and
 * dashes — which is pure computation and always accurate. Then the public profile
 * page is requested: a 404 means nobody holds it, a 200 means somebody does.
 *
 * That second check is honest about its limits. Several platforms answer automated
 * requests with a login wall or a soft 200 for every path, so their row says
 * "check manually" and links straight to the page rather than guessing. A tool that
 * confidently reports a taken handle as free is worse than one that admits it
 * cannot see.
 */
final class UsernameAvailabilityRunner implements Cacheable, ToolRunner
{
    /**
     * @var array<string, array{label: string, url: string, min: int, max: int, pattern: string,
     *     rule: string, probe: bool}>
     */
    private const PLATFORMS = [
        'instagram' => ['label' => 'Instagram', 'url' => 'https://www.instagram.com/%s/',
            'min' => 1, 'max' => 30, 'pattern' => '/^[a-z0-9._]+$/',
            'rule' => 'Letters, numbers, periods and underscores. Up to 30.', 'probe' => false],
        'tiktok' => ['label' => 'TikTok', 'url' => 'https://www.tiktok.com/@%s',
            'min' => 2, 'max' => 24, 'pattern' => '/^[a-z0-9._]+$/',
            'rule' => 'Letters, numbers, periods and underscores. Up to 24.', 'probe' => false],
        'x' => ['label' => 'X (Twitter)', 'url' => 'https://x.com/%s',
            'min' => 4, 'max' => 15, 'pattern' => '/^[a-z0-9_]+$/',
            'rule' => 'Letters, numbers and underscores only. 4–15 characters.', 'probe' => false],
        'youtube' => ['label' => 'YouTube', 'url' => 'https://www.youtube.com/@%s',
            'min' => 3, 'max' => 30, 'pattern' => '/^[a-z0-9._-]+$/',
            'rule' => 'Letters, numbers, periods, underscores and hyphens. 3–30.', 'probe' => true],
        'pinterest' => ['label' => 'Pinterest', 'url' => 'https://www.pinterest.com/%s/',
            'min' => 3, 'max' => 30, 'pattern' => '/^[a-z0-9_]+$/',
            'rule' => 'Letters, numbers and underscores. 3–30.', 'probe' => true],
        'threads' => ['label' => 'Threads', 'url' => 'https://www.threads.net/@%s',
            'min' => 1, 'max' => 30, 'pattern' => '/^[a-z0-9._]+$/',
            'rule' => 'Shares the Instagram handle exactly.', 'probe' => false],
        'github' => ['label' => 'GitHub', 'url' => 'https://github.com/%s',
            'min' => 1, 'max' => 39, 'pattern' => '/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/',
            'rule' => 'Letters, numbers and single hyphens. Up to 39.', 'probe' => true],
        'twitch' => ['label' => 'Twitch', 'url' => 'https://www.twitch.tv/%s',
            'min' => 4, 'max' => 25, 'pattern' => '/^[a-z0-9_]+$/',
            'rule' => 'Letters, numbers and underscores. 4–25.', 'probe' => true],
    ];

    public static function key(): string
    {
        return 'utility.username-availability';
    }

    public function cacheTtl(): int
    {
        // Handles get claimed. A day-old answer would send someone to register a
        // name that is already gone, so this expires quickly on purpose.
        return 600;
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
                    'description' => 'Without the @. Case is ignored — every network here is case-insensitive.',
                    'minLength' => 1,
                    'maxLength' => 40,
                    'examples' => ['metacreator'],
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $handle = strtolower(ltrim(trim($input->string('handle')), '@'));

        if ($handle === '' || preg_match('/^[a-z0-9._-]+$/', $handle) !== 1) {
            throw ToolExecutionException::invalidInput(
                'Handles are letters, numbers, periods, underscores and hyphens. Drop anything else and try again.',
                ['handle' => 'Unsupported characters.'],
            );
        }

        $rows = [];
        $free = 0;
        $taken = 0;

        foreach (self::PLATFORMS as $platform) {
            $url = sprintf($platform['url'], $handle);
            $length = strlen($handle);

            $invalid = match (true) {
                $length < $platform['min'] => "Too short — needs {$platform['min']}+",
                $length > $platform['max'] => "Too long — max {$platform['max']}",
                preg_match($platform['pattern'], $handle) !== 1 => 'Illegal characters',
                default => null,
            };

            if ($invalid !== null) {
                $rows[] = [
                    'platform' => $platform['label'],
                    'valid' => $invalid,
                    'status' => 'Cannot be registered',
                    'rule' => $platform['rule'],
                    'url' => $url,
                ];

                continue;
            }

            $status = $platform['probe'] ? $this->probe($url) : 'Check manually';

            $free += $status === 'Looks available' ? 1 : 0;
            $taken += $status === 'Taken' ? 1 : 0;

            $rows[] = [
                'platform' => $platform['label'],
                'valid' => 'Valid',
                'status' => $status,
                'rule' => $platform['rule'],
                'url' => $url,
            ];
        }

        return ToolResult::table(
            columns: [
                ['key' => 'platform', 'label' => 'Platform'],
                ['key' => 'valid', 'label' => 'Format'],
                ['key' => 'status', 'label' => 'Availability'],
                ['key' => 'rule', 'label' => 'Handle rules'],
                ['key' => 'url', 'label' => 'Profile', 'align' => 'right'],
            ],
            rows: $rows,
            summary: "“{$handle}” looks free on {$free} network(s) and is taken on {$taken}. "
                .'The rest block automated checks — open them to see.',
        )->withWarnings([
            'Instagram, TikTok, X and Threads answer automated requests with a login wall, so their rows '
            .'are format checks only. Open the profile link to see the real answer.',
            'A handle nobody holds is not necessarily yours to take: trademarked and impersonating names '
            .'get reclaimed after the fact, however cleanly they register today.',
        ])->withMeta(['handle' => $handle, 'available' => $free, 'taken' => $taken]);
    }

    /**
     * A 404 means free; a 200 means somebody is there.
     *
     * Redirects are not followed: several platforms send an unknown handle to a
     * search or sign-up page, which would come back 200 and read as "taken".
     */
    private function probe(string $url): string
    {
        try {
            $response = Http::timeout(4.0)
                ->connectTimeout(3.0)
                ->withoutRedirecting()
                ->withHeaders(['User-Agent' => 'MetaCreatorBot/1.0 (+https://metacreator.dev/bot)'])
                ->head($url);
        } catch (Throwable) {
            return 'Check manually';
        }

        return match (true) {
            $response->status() === 404 => 'Looks available',
            $response->status() === 200 => 'Taken',
            default => 'Check manually',
        };
    }
}
