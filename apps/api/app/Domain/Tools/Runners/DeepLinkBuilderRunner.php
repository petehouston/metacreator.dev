<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Domain\Tools\Enums\ResultView;
use App\Domain\Tools\Exceptions\ToolExecutionException;
use App\Support\Social\SocialUrl;

/**
 * The link that opens a profile inside the app rather than in the phone's browser
 * — and an honest account of when each kind actually works.
 *
 * There are two ways to reach an installed app, and they behave nothing alike.
 *
 * A **universal link** is the ordinary `https://` URL. iOS and Android both let an
 * app claim its own domain, so tapping `https://instagram.com/nasa` opens Instagram
 * when it is installed and the website when it is not. It cannot fail. It is the
 * right link for a bio, a newsletter, a QR code and every button on a web page.
 *
 * A **scheme URI** — `instagram://user?username=nasa` — addresses the app directly.
 * It skips the browser hand-off, which is why native apps and some link-in-bio
 * tools use it, and it does nothing at all when the app is missing: no error, no
 * fallback, a tap that appears broken. It is the right link inside your own app,
 * and the wrong one on a public page.
 *
 * So this tool hands back both, labelled, with the failure mode of each stated
 * rather than implied. Schemes are only listed for platforms whose scheme is
 * long-established; where one is not, the row says so instead of guessing a URI
 * that would silently do nothing.
 */
final class DeepLinkBuilderRunner implements Cacheable, ToolRunner
{
    /**
     * Scheme templates per platform and object kind.
     *
     * `{value}` is the handle or id taken from the pasted link. A platform or kind
     * missing from this map has no row invented for it — see the class comment.
     *
     * @var array<string, array<string, array{scheme: string, note: string}>>
     */
    private const SCHEMES = [
        'instagram' => [
            'profile' => ['scheme' => 'instagram://user?username={value}',
                'note' => 'Opens the profile in the Instagram app.'],
        ],
        'x' => [
            'profile' => ['scheme' => 'twitter://user?screen_name={value}',
                'note' => 'The scheme is still `twitter://` after the rename.'],
            'post' => ['scheme' => 'twitter://status?id={value}',
                'note' => 'Takes the numeric post id, which is the last segment of the URL.'],
        ],
        'youtube' => [
            'video' => ['scheme' => 'vnd.youtube://{value}',
                'note' => 'Android’s documented YouTube scheme; iOS accepts it too.'],
            'channel' => ['scheme' => 'vnd.youtube://www.youtube.com/channel/{value}',
                'note' => 'Channel ids only — a handle has to be resolved first.'],
        ],
        'facebook' => [
            // One route covers every Facebook object, because the scheme takes the
            // web URL rather than an id — which is the only Facebook deep link that
            // does not need a numeric page id nobody has to hand.
            'profile' => ['scheme' => 'fb://facewebmodal/f?href={value}',
                'note' => 'Facebook’s in-app browser route. Takes the full https URL, url-encoded.'],
            'post' => ['scheme' => 'fb://facewebmodal/f?href={value}',
                'note' => 'Facebook’s in-app browser route. Takes the full https URL, url-encoded.'],
            'video' => ['scheme' => 'fb://facewebmodal/f?href={value}',
                'note' => 'Facebook’s in-app browser route. Takes the full https URL, url-encoded.'],
        ],
        'pinterest' => [
            'profile' => ['scheme' => 'pinterest://user/{value}',
                'note' => 'Opens the profile in the Pinterest app.'],
            'pin' => ['scheme' => 'pinterest://pin/{value}',
                'note' => 'Takes the numeric Pin id.'],
        ],
        'linkedin' => [
            'profile' => ['scheme' => 'linkedin://in/{value}',
                'note' => 'Takes the public profile id — the last segment of a /in/ URL.'],
        ],
        'twitch' => [
            'channel' => ['scheme' => 'twitch://stream/{value}',
                'note' => 'Opens the channel’s stream in the Twitch app.'],
        ],
        'telegram' => [
            'channel' => ['scheme' => 'tg://resolve?domain={value}',
                'note' => 'Telegram’s documented resolve scheme.'],
            'profile' => ['scheme' => 'tg://resolve?domain={value}',
                'note' => 'Telegram’s documented resolve scheme.'],
        ],
    ];

    public static function key(): string
    {
        return 'utility.deep-link-builder';
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
            'required' => ['url'],
            'additionalProperties' => false,
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'x-control' => 'text',
                    'title' => 'Profile or post link',
                    'description' => 'Paste the ordinary web link to the profile, video, post or Pin '
                        .'you want to open in the app.',
                    'minLength' => 4,
                    'maxLength' => 500,
                    'examples' => ['https://www.instagram.com/nasa/'],
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $raw = trim($input->string('url'));
        $identity = SocialUrl::identify($raw);

        if ($identity['host'] === null) {
            throw ToolExecutionException::invalidInput(
                'That is not a URL we can read. Paste the whole link, including the domain.',
                ['url' => 'Expected a link such as https://www.instagram.com/nasa/'],
            );
        }

        // The tracking on a shared link would be carried into every row below, and a
        // deep link with somebody's share id in it is a deep link that identifies them.
        $clean = SocialUrl::stripTracking($identity['url'], keep: ['v', 't', 'list'])['url'];

        $platform = $identity['platform'];
        $kind = $identity['kind'];
        $value = $this->value($platform, $kind, $clean);

        $rows = [[
            'type' => 'Universal link',
            'link' => $clean,
            'opens' => 'The app when it is installed, the website when it is not.',
            'reliability' => 'Always works',
        ]];

        $template = $platform !== null && $kind !== null
            ? (self::SCHEMES[$platform][$kind] ?? null)
            : null;

        if ($template !== null && $value !== null) {
            $rows[] = [
                'type' => 'Scheme URI',
                'link' => str_replace('{value}', $value, $template['scheme']),
                'opens' => $template['note'],
                'reliability' => 'Only with the app installed',
            ];
        }

        $rows[] = [
            'type' => 'HTML button',
            'link' => '<a href="'.htmlspecialchars($clean, ENT_QUOTES | ENT_HTML5).'" '
                .'rel="noopener">Open in the app</a>',
            'opens' => 'The universal link, which is the one to put on a web page.',
            'reliability' => 'Always works',
        ];

        return (new ToolResult(
            view: ResultView::Table,
            data: [
                'columns' => [
                    ['key' => 'type', 'label' => 'Link'],
                    ['key' => 'link', 'label' => 'Copy', 'copyable' => true, 'wrap' => true],
                    ['key' => 'opens', 'label' => 'What it does'],
                    ['key' => 'reliability', 'label' => 'When it works'],
                ],
                'rows' => $rows,
            ],
            summary: $this->summary($identity, $template !== null && $value !== null),
        ))->withMeta(array_filter([
            'platform' => $platform,
            'kind' => $kind,
            'universal_link' => $clean,
            'scheme_uri' => $rows[1]['type'] === 'Scheme URI' ? $rows[1]['link'] : null,
        ], fn ($value) => $value !== null))
            ->withWarnings($this->warnings($platform, $template !== null && $value !== null));
    }

    /**
     * The handle or id a scheme template needs, pulled out of the cleaned URL.
     *
     * Returns null when the URL does not carry the identifier the app's scheme
     * takes — a YouTube `@handle` is not a channel id, and building
     * `vnd.youtube://@someone` would produce a URI that opens nothing.
     */
    private function value(?string $platform, ?string $kind, string $url): ?string
    {
        $path = trim((string) (parse_url($url, PHP_URL_PATH) ?: ''), '/');
        parse_str((string) (parse_url($url, PHP_URL_QUERY) ?: ''), $query);

        return match (true) {
            $platform === 'facebook' => rawurlencode($url),
            $platform === 'instagram' && $kind === 'profile' => explode('/', $path)[0] ?: null,
            $platform === 'x' && $kind === 'profile' => explode('/', $path)[0] ?: null,
            $platform === 'x' && $kind === 'post' => $this->lastNumeric($path),
            $platform === 'youtube' && $kind === 'video' => is_string($query['v'] ?? null)
                ? $query['v']
                : $this->lastSegment($path),
            $platform === 'youtube' && $kind === 'channel' => preg_match('#channel/(UC[\w-]{22})#', $path, $m) === 1
                ? $m[1]
                : null,
            $platform === 'pinterest' && $kind === 'pin' => $this->lastNumeric($path),
            $platform === 'pinterest' && $kind === 'profile' => explode('/', $path)[0] ?: null,
            $platform === 'linkedin' && $kind === 'profile' => str_starts_with($path, 'in/')
                ? explode('/', $path)[1] ?? null
                : null,
            $platform === 'twitch' && $kind === 'channel' => explode('/', $path)[0] ?: null,
            $platform === 'telegram' => explode('/', $path)[0] ?: null,
            default => null,
        };
    }

    private function lastSegment(string $path): ?string
    {
        $segments = array_values(array_filter(explode('/', $path)));

        return $segments === [] ? null : end($segments);
    }

    private function lastNumeric(string $path): ?string
    {
        $last = $this->lastSegment($path);

        return $last !== null && preg_match('/^\d+$/', $last) === 1 ? $last : null;
    }

    /** @param  array{platform: ?string, label: ?string, kind: ?string, host: ?string, path: string, url: string}  $identity */
    private function summary(array $identity, bool $hasScheme): string
    {
        $what = $identity['label'] === null
            ? 'that link'
            : $identity['label'].' '.($identity['kind'] ?? 'link');

        return $hasScheme
            ? "Two ways to open {$what} in the app. Use the universal link everywhere a stranger "
                .'might tap it; keep the scheme URI for inside your own app.'
            : "The universal link is the one to use for {$what} — it opens the app when it is "
                .'installed and the website when it is not, which is what a public link has to do.';
    }

    /** @return list<string> */
    private function warnings(?string $platform, bool $hasScheme): array
    {
        $warnings = [
            'A scheme URI does nothing when the app is not installed — no error, no fallback, just a '
            .'tap that appears to fail. That is why the universal link is the default here and not '
            .'the other way round.',
        ];

        if (! $hasScheme && $platform !== null) {
            $warnings[] = SocialUrl::label($platform).' has no long-established scheme for this kind '
                .'of link, so none is listed. An invented one would be a URI that silently opens '
                .'nothing, which is worse than not offering it.';
        }

        $warnings[] = 'Schemes are set by the app, not by a standard, and an app can retire one in a '
            .'release. Test any scheme URI on a real device before it goes on something you print.';

        return $warnings;
    }
}
