<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Domain\Tools\Exceptions\ToolExecutionException;
use App\Support\Http\SafeHttpClient;
use App\Support\Http\UrlGuard;
use App\Support\Social\SocialUrl;

/**
 * Where a short link actually goes, one hop at a time.
 *
 * Two different people need this. Somebody has been sent a `bit.ly` and wants to
 * know what is behind it before clicking — a chain that ends on a domain you have
 * never heard of is the oldest phishing shape there is. And somebody is debugging
 * their own link: a campaign URL that goes through a shortener, a redirector and a
 * CMS canonical, losing its UTM parameters somewhere in the middle, and the only
 * way to find where is to watch each hop.
 *
 * So the answer is the *chain*, not just the destination. Each row is one hop with
 * its status code, and the parameters that survived it — which is what makes a
 * dropped `utm_campaign` visible rather than merely suspected.
 *
 * Nothing here is followed blindly: every hop is re-checked by
 * {@see UrlGuard} before it is fetched, so a shortener pointing
 * at `169.254.169.254` stops the walk instead of proxying it.
 */
final class LinkExpanderRunner implements Cacheable, ToolRunner
{
    /** Long enough for any legitimate chain; short enough that a loop costs one request each. */
    private const MAX_HOPS = 12;

    /** Domains whose whole purpose is to hide the destination. */
    private const SHORTENERS = [
        'bit.ly', 'tinyurl.com', 't.co', 'goo.gl', 'ow.ly', 'buff.ly', 'is.gd', 'cutt.ly',
        'rebrand.ly', 'shorturl.at', 'rb.gy', 'tiny.cc', 'lnkd.in', 'pin.it', 'fb.me',
        'youtu.be', 'redd.it', 'dai.ly', 'flic.kr', 'instagr.am', 'vm.tiktok.com', 'vt.tiktok.com',
        'trib.al', 'spoti.fi', 'amzn.to', 'shorte.st', 'adf.ly', 'bl.ink', 'linktr.ee',
    ];

    public static function key(): string
    {
        return 'utility.link-expander';
    }

    public function cacheTtl(): int
    {
        // Short: a shortener's destination can be edited at any time, and a cached
        // "this is safe" that outlives the truth is worse than no answer.
        return 600;
    }

    public function inputSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'required' => ['url'],
            'additionalProperties' => false,
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'x-control' => 'text',
                    'title' => 'Short or redirecting URL',
                    'description' => 'Any link — a shortener, a tracked campaign URL, an affiliate link.',
                    'minLength' => 6,
                    'maxLength' => 900,
                    'examples' => ['https://bit.ly/3xample'],
                ],
                'strip_tracking' => [
                    'type' => 'boolean',
                    'title' => 'Also show the destination with tracking removed',
                    'default' => true,
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $start = SocialUrl::normalise(trim($input->string('url')));

        if (SocialUrl::host($start) === null) {
            throw ToolExecutionException::invalidInput(
                'That is not a URL we can read. Paste the whole link, including the domain.',
                ['url' => 'Expected a link such as https://bit.ly/3xample'],
            );
        }

        [$rows, $final, $looped, $exhausted] = $this->walk($start);

        $destination = SocialUrl::identify($final);
        $stripped = SocialUrl::stripTracking($final);

        if ($input->bool('strip_tracking', true) && $stripped['removed'] !== []) {
            $rows[] = [
                'step' => '—',
                'status' => 'clean',
                'url' => $stripped['url'],
                'note' => 'Same page, '.count($stripped['removed']).' tracking parameter(s) removed',
            ];
        }

        $hops = count(array_filter($rows, fn (array $row) => $row['status'] !== 'clean')) - 1;

        return ToolResult::table(
            columns: [
                ['key' => 'step', 'label' => '#', 'align' => 'right'],
                ['key' => 'status', 'label' => 'Status', 'align' => 'right'],
                ['key' => 'url', 'label' => 'URL', 'copyable' => true, 'wrap' => true],
                ['key' => 'note', 'label' => 'What happened'],
            ],
            rows: $rows,
            summary: $this->summary($start, $final, $hops, $destination),
        )->withMeta([
            'final_url' => $final,
            'clean_url' => $stripped['url'],
            'final_domain' => SocialUrl::host($final),
            'hops' => max(0, $hops),
            'platform' => $destination['platform'],
            'removed_parameters' => $stripped['removed'],
        ])->withWarnings($this->warnings($start, $final, $hops, $looped, $exhausted, $stripped['removed']));
    }

    /**
     * Walk the chain, guarding each hop.
     *
     * @return array{0: list<array<string, string>>, 1: string, 2: bool, 3: bool}
     */
    private function walk(string $start): array
    {
        $rows = [];
        $seen = [];
        $current = $start;
        $looped = false;

        for ($step = 0; $step <= self::MAX_HOPS; $step++) {
            if (in_array($current, $seen, true)) {
                $rows[] = ['step' => (string) ($step + 1), 'status' => '↻', 'url' => $current,
                    'note' => 'Redirect loop — this URL has already been visited'];
                $looped = true;

                break;
            }

            $seen[] = $current;

            $response = SafeHttpClient::hop($current);

            if ($response === null) {
                $rows[] = ['step' => (string) ($step + 1), 'status' => '—', 'url' => $current,
                    'note' => 'No response — the host is unreachable or blocking automated requests'];

                break;
            }

            $status = $response->status();
            $location = $response->header('Location');

            if ($location === '' || $status < 300 || $status >= 400) {
                $rows[] = ['step' => (string) ($step + 1), 'status' => (string) $status, 'url' => $current,
                    'note' => $status >= 400 ? 'Chain ends here with an error' : 'Final destination'];

                break;
            }

            $next = $this->resolve($current, $location);

            $rows[] = ['step' => (string) ($step + 1), 'status' => (string) $status, 'url' => $current,
                'note' => 'Redirects to '.(SocialUrl::host($next) ?? $next)];

            $current = $next;
        }

        $exhausted = count($seen) > self::MAX_HOPS;

        return [$rows, $current, $looped, $exhausted];
    }

    /**
     * @param  array{platform: ?string, label: ?string, kind: ?string, host: ?string, path: string, url: string}  $destination
     */
    private function summary(string $start, string $final, int $hops, array $destination): string
    {
        if ($final === $start) {
            return 'That link does not redirect — it is already the destination.';
        }

        $domain = SocialUrl::host($final) ?? $final;
        $where = $destination['label'] !== null
            ? $destination['label'].' ('.$domain.')'
            : $domain;

        return sprintf(
            '%d redirect%s, ending at %s.',
            max(1, $hops),
            $hops === 1 ? '' : 's',
            $where,
        );
    }

    /**
     * @param  list<string>  $removed
     * @return list<string>
     */
    private function warnings(
        string $start,
        string $final,
        int $hops,
        bool $looped,
        bool $exhausted,
        array $removed,
    ): array {
        $warnings = [];

        if ($looped) {
            $warnings[] = 'This chain loops back on itself, so it never resolves. A browser would give up '
                .'with ERR_TOO_MANY_REDIRECTS.';
        }

        if ($exhausted) {
            $warnings[] = 'The chain is longer than '.self::MAX_HOPS.' hops and was stopped there. A chain '
                .'that long is either misconfigured or deliberately obfuscated.';
        }

        if ($hops >= 3) {
            $warnings[] = 'Each hop costs the visitor a round trip before anything renders. Three or more '
                .'is worth fixing on a link you control.';
        }

        $startHost = SocialUrl::host($start);
        $finalHost = SocialUrl::host($final);

        if ($startHost !== null && $finalHost !== null && $startHost !== $finalHost
            && ! in_array($startHost, self::SHORTENERS, true) && $hops > 0) {
            $warnings[] = 'The link starts on '.$startHost.' and ends on '.$finalHost.'. A cross-domain '
                .'redirect from a domain that is not a known shortener is worth a second look before you '
                .'click or publish it.';
        }

        if ($removed !== []) {
            $warnings[] = 'The destination carries '.count($removed).' tracking parameter(s): '
                .implode(', ', $removed).'.';
        }

        if (str_starts_with($final, 'http://')) {
            $warnings[] = 'The chain ends on plain HTTP. Anything sent to that page travels unencrypted.';
        }

        $warnings[] = 'This follows the redirects a server declares. A page that redirects with JavaScript '
            .'or a meta refresh will end here rather than at its real destination.';

        return $warnings;
    }

    /** Resolve a `Location` header, which may be relative, against the URL that sent it. */
    private function resolve(string $base, string $location): string
    {
        $location = trim($location);

        if (str_contains($location, '://')) {
            return $location;
        }

        $parts = parse_url($base);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

        if (str_starts_with($location, '//')) {
            return ($parts['scheme'] ?? 'https').':'.$location;
        }

        return str_starts_with($location, '/')
            ? $origin.$location
            : $origin.rtrim(dirname($parts['path'] ?? '/'), '/').'/'.$location;
    }
}
