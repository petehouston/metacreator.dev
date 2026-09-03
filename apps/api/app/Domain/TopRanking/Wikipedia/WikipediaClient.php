<?php

declare(strict_types=1);

namespace App\Domain\TopRanking\Wikipedia;

use App\Support\Http\SafeHttpClient;
use RuntimeException;

/**
 * Reads an English Wikipedia article as parsed HTML.
 *
 * The `action=parse&prop=text` form is used rather than the raw wikitext, and that
 * is the single most important decision in this whole feature. Wikitext for these
 * articles is a thicket of templates — `{{Plain link}}`, `{{Flag}}`, `{{plainlist}}`,
 * `{{dagger}}` — nested inside table cells, with inline HTML comments sitting in the
 * middle of the numbers ("162.<!-- update the date too -->7"). Parsing that by hand
 * means reimplementing a template engine, badly, and re-breaking every time an
 * editor reaches for a template we have not seen.
 *
 * The parsed HTML is what those templates *produce*: `{{Plain link}}` has already
 * become an `<a href>` pointing at the real profile, `{{Flag}}` has already become
 * the country's name, and the comments are gone. The markup that remains is a plain
 * table.
 *
 * No API key, no quota, no account — the MediaWiki action API is open. The
 * User-Agent is the one courtesy the Wikimedia terms actually ask for, and
 * {@see SafeHttpClient} sends it on every request.
 */
final class WikipediaClient
{
    private const ENDPOINT = 'https://en.wikipedia.org/w/api.php';

    /**
     * Article HTML is large — the YouTube list is well past the 2 MB default read
     * cap, and the rows we want are in the middle of it, so a truncated body loses
     * the back half of the ranking silently.
     */
    private const MAX_BYTES = 8_000_000;

    /**
     * The rendered HTML of an article's body.
     *
     * @throws RuntimeException when the article does not exist, or Wikipedia is
     *                          unreachable. Both are the caller's problem to report:
     *                          a renamed article is an admin fixing `source_page`,
     *                          and an outage is a retry.
     */
    public function html(string $page): string
    {
        $response = SafeHttpClient::attempt(self::ENDPOINT.'?'.http_build_query([
            'action' => 'parse',
            'page' => $page,
            'prop' => 'text',
            'format' => 'json',
            'formatversion' => '2',
            // Nothing here reads the parser's performance report or the list of
            // templates used, and both are kilobytes on every request.
            'disablelimitreport' => '1',
            'disableeditsection' => '1',
        ]), timeout: 20.0);

        if ($response === null || $response->failed()) {
            throw new RuntimeException('Wikipedia could not be reached.');
        }

        /** @var array<string, mixed> $body */
        $body = json_decode(SafeHttpClient::body($response, self::MAX_BYTES), true) ?: [];

        // The action API answers 200 with an `error` object for a missing page, so
        // the status code alone does not tell us whether this worked.
        if (isset($body['error'])) {
            $code = is_array($body['error']) ? (string) ($body['error']['code'] ?? 'unknown') : 'unknown';

            throw new RuntimeException($code === 'missingtitle'
                ? "There is no Wikipedia article called “{$page}”."
                : "Wikipedia refused the request ({$code}).");
        }

        $html = $body['parse']['text'] ?? null;

        if (! is_string($html) || $html === '') {
            throw new RuntimeException("Wikipedia returned nothing for “{$page}”.");
        }

        return $html;
    }
}
