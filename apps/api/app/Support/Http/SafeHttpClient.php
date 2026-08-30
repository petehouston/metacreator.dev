<?php

declare(strict_types=1);

namespace App\Support\Http;

use App\Domain\Tools\Exceptions\ToolExecutionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\UriInterface;
use Throwable;

/**
 * The only way a tool may fetch a user-supplied URL.
 *
 * {@see UrlGuard} checks the URL before the request, and again on every redirect:
 * a public URL that 302s to `http://169.254.169.254` is the classic SSRF bypass,
 * and checking only the first hop does not stop it.
 */
final class SafeHttpClient
{
    /** Pages are capped so a hostile server cannot stream gigabytes at a worker. */
    public const MAX_BYTES = 2_000_000;

    public static function get(string $url, float $timeout = 6.0): Response
    {
        $response = self::request($url, $timeout);

        if ($response->failed()) {
            throw ToolExecutionException::notFound('anything at that URL (HTTP '.$response->status().')');
        }

        return $response;
    }

    /**
     * Like {@see self::get()}, but a non-2xx status comes back as a response rather
     * than an exception; only a transport failure returns null.
     *
     * For the callers whose question *is* the status code — does this handle exist,
     * is this page reachable — a 404 is the answer, not an error. Keeping the two
     * failure kinds apart is what stops a tool reporting "available" because the
     * network was down.
     */
    public static function attempt(string $url, float $timeout = 6.0): ?Response
    {
        try {
            return self::request($url, $timeout);
        } catch (ToolExecutionException) {
            return null;
        }
    }

    /**
     * Several safe URLs fetched concurrently, keyed the way they were passed in.
     *
     * Sequential requests are the wrong shape for an expansion that makes dozens of
     * small calls: forty-odd round trips at a couple of hundred milliseconds each
     * spends a worker's whole budget on waiting. Every URL is guarded exactly as it
     * is in {@see self::request()}; a failure comes back as null for that key
     * alone, because one dead request should not cost the other thirty-nine.
     *
     * @param  array<array-key, string>  $urls
     * @return array<array-key, Response|null>
     */
    public static function attemptPool(array $urls, float $timeout = 6.0): array
    {
        $safe = array_filter($urls, static fn (string $url) => UrlGuard::isPublicHttpUrl($url));

        if ($safe === []) {
            return array_map(static fn () => null, $urls);
        }

        try {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn (string $url, int|string $key) => $pool->as((string) $key)
                    ->timeout($timeout)
                    ->connectTimeout(min($timeout, 3.0))
                    ->withHeaders([
                        'User-Agent' => 'MetaCreatorBot/1.0 (+https://metacreator.dev/bot)',
                        'Accept' => 'application/json,text/plain;q=0.9,*/*;q=0.8',
                    ])
                    ->withOptions(['allow_redirects' => false])
                    ->get($url),
                array_values($safe),
                array_keys($safe),
            ));
        } catch (Throwable) {
            return array_map(static fn () => null, $urls);
        }

        return array_map(
            static function (int|string $key) use ($responses, $safe): ?Response {
                if (! array_key_exists($key, $safe)) {
                    return null;
                }

                $response = $responses[(string) $key] ?? null;

                return $response instanceof Response ? $response : null;
            },
            array_combine(array_keys($urls), array_keys($urls)),
        );
    }

    private static function request(string $url, float $timeout): Response
    {
        if (! UrlGuard::isPublicHttpUrl($url)) {
            throw ToolExecutionException::invalidInput(
                'That URL cannot be fetched. Use a public http(s) address.',
                ['url' => 'Not a publicly reachable URL.'],
            );
        }

        try {
            return Http::timeout($timeout)
                ->connectTimeout(min($timeout, 3.0))
                ->withHeaders([
                    'User-Agent' => 'MetaCreatorBot/1.0 (+https://metacreator.dev/bot)',
                    'Accept' => 'text/html,application/xhtml+xml,application/json;q=0.9,*/*;q=0.8',
                ])
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 3,
                        'strict' => true,
                        'referer' => false,
                        'protocols' => ['http', 'https'],
                        'on_redirect' => static function ($request, $response, UriInterface $uri): void {
                            if (! UrlGuard::hostIsPublic($uri->getHost())) {
                                throw new ToolExecutionException(
                                    'That link redirects somewhere we will not follow.',
                                    'tool.invalid_input',
                                );
                            }
                        },
                    ],
                ])
                ->get($url);
        } catch (ToolExecutionException $e) {
            throw $e;
        } catch (Throwable) {
            throw ToolExecutionException::upstreamFailed(
                (string) parse_url($url, PHP_URL_HOST),
                'We could not reach that URL. It may be down, slow, or blocking automated requests.',
            );
        }
    }

    /**
     * The response body, truncated to something a regex can safely be run over.
     *
     * A caller may raise the cap when it knows the page it is reading is genuinely
     * larger and the part it needs is near the end — a YouTube channel page is
     * ~2.5 MB and puts the banner in the last few kilobytes. Raise it deliberately
     * and per call, never globally: the default is what protects every other tool
     * from a page that is large by accident or by malice.
     */
    public static function body(Response $response, int $maxBytes = self::MAX_BYTES): string
    {
        return substr($response->body(), 0, max(1, $maxBytes));
    }
}
