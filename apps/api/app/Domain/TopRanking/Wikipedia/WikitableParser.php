<?php

declare(strict_types=1);

namespace App\Domain\TopRanking\Wikipedia;

use App\Domain\TopRanking\Enums\RankingPlatform;
use RuntimeException;

/**
 * Turns one `wikitable` into rows.
 *
 * The parser is **header-driven**, not position-driven, and that is what lets nine
 * differently-shaped articles share one implementation. Every list here uses its own
 * column order and its own words — Instagram leads with "Username", Twitch with
 * "Rank" then "Channel", YouTube puts the profile link in a column of its own called
 * "Link" — so a parser that reached for `$cells[2]` would need nine of itself and
 * would break the first time an editor inserted a column. Reading the header row and
 * mapping *labels* to fields survives a reordering and survives an insertion, and
 * when it does not survive something it fails loudly on the header rather than
 * quietly importing follower counts into the country column.
 *
 * Everything below operates on the HTML MediaWiki produced, never on wikitext — see
 * {@see WikipediaClient} for why that distinction carries the feature.
 */
final class WikitableParser
{
    /**
     * Header keywords, most specific first, mapped to the field they fill.
     *
     * Order matters: "primary language" must be tested before "language" would
     * match something else, and "channel name" before "name". The first entry whose
     * needle appears in the normalised header wins.
     *
     * @var array<string, list<string>>
     */
    private const HEADERS = [
        'metric' => ['subscribers', 'followers', 'views', 'total followers'],
        'secondary' => ['likes'],
        'link' => ['link'],
        'name' => ['username', 'channel name', 'page name', 'channel', 'account name', 'name'],
        'owner' => ['owner', 'network / owner', 'network', 'uploader'],
        'country' => ['country', 'nationality', 'region', 'territory'],
        'category' => ['content category', 'streamed categories', 'category', 'genre'],
        'language' => ['primary language', 'language'],
        'description' => ['description', 'notes'],
    ];

    /**
     * @return list<ParsedRow>
     *
     * @throws RuntimeException when the table is absent or its header cannot be
     *                          read. Both mean the article changed shape, which is
     *                          an admin's decision to make, not something to paper
     *                          over with an empty result.
     */
    public function parse(string $html, int $tableIndex, RankingPlatform $platform, int $limit): array
    {
        $tables = $this->tables($html);

        if (! isset($tables[$tableIndex])) {
            throw new RuntimeException(sprintf(
                'That article has %d ranking table(s); the page is configured to read table %d.',
                count($tables),
                $tableIndex,
            ));
        }

        $rows = $this->rows($tables[$tableIndex]);

        if ($rows === []) {
            throw new RuntimeException('The table on that article has no rows.');
        }

        $columns = $this->mapHeader(array_shift($rows));

        if (! isset($columns['name'])) {
            throw new RuntimeException(
                'No column on that table looks like an account name. The article has probably been restructured.'
            );
        }

        $parsed = [];

        foreach ($rows as $cells) {
            $row = $this->toRow($cells, $columns, $platform);

            if ($row !== null) {
                $parsed[] = $row;
            }

            if (count($parsed) >= $limit) {
                break;
            }
        }

        return $parsed;
    }

    /**
     * Every `wikitable` in the document, outermost only.
     *
     * `<table` … `</table>` is matched by depth rather than by a lazy regex: these
     * articles nest tables inside cells (a `{{plainlist}}` of two countries renders
     * as one), and a non-greedy match would end the outer table at the inner
     * closing tag and lose every row after the first multi-country entry.
     *
     * @return list<string>
     */
    private function tables(string $html): array
    {
        $tables = [];
        $offset = 0;

        while (preg_match('/<table\b[^>]*>/i', $html, $open, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $start = (int) $open[0][1];
            $cursor = $start + strlen($open[0][0]);
            $depth = 1;

            while ($depth > 0 && preg_match('/<(\/?)table\b[^>]*>/i', $html, $tag, PREG_OFFSET_CAPTURE, $cursor) === 1) {
                $depth += $tag[1][0] === '/' ? -1 : 1;
                $cursor = (int) $tag[0][1] + strlen($tag[0][0]);
            }

            $table = substr($html, $start, $cursor - $start);
            $offset = $cursor;

            // `wikitable` is the class the article's own ranking tables carry. The
            // navboxes, infoboxes and the "Key" legend do not, which is what keeps
            // the index in `source_table` counting the tables an editor can see.
            if (stripos($open[0][0], 'wikitable') !== false) {
                $tables[] = $table;
            }
        }

        return $tables;
    }

    /**
     * The table's rows, each as a list of raw cell HTML.
     *
     * `th` and `td` are collected together and in document order, because several of
     * these tables put the rank in a `<th scope="row">` — so a pass that took only
     * `td` would shift every column by one on exactly those tables and on no others.
     *
     * @return list<list<string>>
     */
    private function rows(string $table): array
    {
        preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/is', $table, $matches, PREG_SET_ORDER);

        $rows = [];

        foreach ($matches as $match) {
            preg_match_all('/<(th|td)\b[^>]*>(.*?)<\/\1>/is', $match[1], $cells, PREG_SET_ORDER);

            if ($cells === []) {
                continue;
            }

            $rows[] = array_map(static fn (array $cell): string => $cell[2], $cells);
        }

        return $rows;
    }

    /**
     * Header labels to field names.
     *
     * @param  list<string>  $header
     * @return array<string, int> field => column index
     */
    private function mapHeader(array $header): array
    {
        $columns = [];

        foreach ($header as $index => $cell) {
            $label = $this->normalise($this->text($cell));

            if ($label === '') {
                continue;
            }

            foreach (self::HEADERS as $field => $needles) {
                // First column to claim a field keeps it. Several tables carry two
                // plausible matches — YouTube's "Name" and "Channel name" — and the
                // leftmost is the one a reader would call the name.
                if (isset($columns[$field])) {
                    continue;
                }

                foreach ($needles as $needle) {
                    if (str_contains($label, $needle)) {
                        $columns[$field] = $index;

                        continue 3;
                    }
                }
            }
        }

        return $columns;
    }

    /**
     * @param  list<string>  $cells
     * @param  array<string, int>  $columns
     */
    private function toRow(array $cells, array $columns, RankingPlatform $platform): ?ParsedRow
    {
        $cell = static fn (string $field): ?string => isset($columns[$field]) ? ($cells[$columns[$field]] ?? null) : null;

        $nameCell = $cell('name');

        if ($nameCell === null) {
            return null;
        }

        $name = $this->text($nameCell);

        if ($name === '') {
            return null;
        }

        // The profile address, preferring what the article published. A dedicated
        // "Link" column beats a link inside the name cell, and both beat a URL we
        // would have to guess from the handle — the guess is right most of the time
        // and wrong in exactly the cases a reader would notice (a YouTube channel
        // that predates handles, an account since renamed).
        $profileUrl = $this->externalLink($cell('link') ?? '')
            ?? $this->externalLink($nameCell)
            ?? $platform->profileUrl($name);

        $handle = $this->handle($name, $profileUrl, $platform);

        return new ParsedRow(
            name: $name,
            handle: $handle,
            owner: $this->nullableText($cell('owner')),
            profileUrl: $profileUrl,
            metric: $this->number($cell('metric')),
            secondaryMetric: $this->number($cell('secondary')),
            country: $this->nullableText($cell('country')),
            category: $this->nullableText($cell('category')),
            language: $this->nullableText($cell('language')),
            description: $this->nullableText($cell('description')),
        );
    }

    /**
     * The account handle.
     *
     * Taken from the profile URL rather than from the displayed name wherever one
     * exists, because the URL is machine-written and the name is not: articles
     * write the same account as `@khaby.lame`, `Khaby Lame` and `khaby.lame` in
     * different columns of the same table.
     */
    private function handle(string $name, ?string $profileUrl, RankingPlatform $platform): ?string
    {
        if ($profileUrl !== null) {
            $path = trim((string) parse_url($profileUrl, PHP_URL_PATH), '/');

            if ($path !== '') {
                $segments = explode('/', $path);

                // `youtube.com/user/MrBeast6000`, `bsky.app/profile/x.bsky.social`
                // and `youtube.com/@MrBeast` all put the handle last, so the final
                // segment is the right one to take.
                //
                // `/channel/UCbCmjCuTUZos6Inko4u57UQ` is the exception: that last
                // segment is an opaque internal id, and printing it beneath a channel
                // name as though it were a handle looks like a bug on the page. Only
                // the display name is withheld — the link itself still works.
                $handle = ltrim(end($segments), '@');
                $parent = count($segments) > 1 ? strtolower($segments[count($segments) - 2]) : '';

                if ($handle !== ''
                    && $parent !== 'channel'
                    && ! in_array(strtolower($handle), ['user', 'channel', 'c', 'profile'], true)) {
                    return substr($handle, 0, 120);
                }
            }
        }

        // No link: only trust the name if it was written as a handle. A page called
        // "Real Madrid CF" is a name, and storing it as a handle would build a
        // profile URL that 404s.
        return str_starts_with($name, '@') ? substr(ltrim($name, '@'), 0, 120) : null;
    }

    /** The first genuine outbound link in a cell, if any. */
    private function externalLink(string $cell): ?string
    {
        // `class="external"` is what MediaWiki puts on a link that leaves the site.
        // Matching on it rather than on `href` skips the `/wiki/…` article links
        // that sit in the same cells, which are about the person, not the account.
        if (preg_match('/<a\b[^>]*class="[^"]*\bexternal\b[^"]*"[^>]*href="([^"]+)"/i', $cell, $match) !== 1) {
            return null;
        }

        $url = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return str_starts_with($url, 'http') ? substr($url, 0, 500) : null;
    }

    /**
     * A cell's number.
     *
     * Only the first number in the cell is read, and only after references are
     * stripped: `162.7<sup>[4]</sup>` must not become 162.74, and "2.2 (as of
     * August)" must not become 22.
     */
    private function number(?string $cell): ?float
    {
        if ($cell === null) {
            return null;
        }

        $text = str_replace(',', '', $this->text($cell));

        if (preg_match('/-?\d+(?:\.\d+)?/', $text, $match) !== 1) {
            return null;
        }

        $value = (float) $match[0];

        // No unit is assumed here, because the pages do not share one: most publish
        // a magnitude ("515" against a header reading "(millions)"), while Twitch's
        // subscriber list publishes the exact count ("1,112,947"). The page's
        // `metric_unit` is what interprets this number; all this guard does is
        // reject a negative or an absurdity.
        return $value >= 0 && $value < 1e12 ? $value : null;
    }

    private function nullableText(?string $cell): ?string
    {
        if ($cell === null) {
            return null;
        }

        $text = $this->text($cell);

        return $text === '' ? null : substr($text, 0, 400);
    }

    /**
     * A cell reduced to the words a reader would see.
     *
     * The separators this inserts are written as a control character rather than as
     * a comma, and then converted at the end. That indirection is not fussiness: the
     * cells contain commas of their own — Twitch publishes "1,112,947" — and a pass
     * that tidies punctuation cannot tell a separator it just inserted from a
     * thousands separator that was always there. Using a character that cannot occur
     * in the source keeps the two apart, and the one time it did not, an exact
     * subscriber count was read as the digit 1.
     */
    private function text(string $html): string
    {
        // A character that cannot appear in article text, so the tidy-up below can
        // only ever affect separators this method inserted.
        $sep = "\x1F";

        // Order matters. References and the templatestyles blocks that MediaWiki
        // inlines are removed whole — `<sup>[4]</sup>` stripped tag-by-tag leaves a
        // bare "[4]" glued to the value, and a `<style>` block stripped the same way
        // dumps a stylesheet into the cell.
        $html = preg_replace('/<(script|style|sup)\b[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;
        $html = preg_replace('/<span\b[^>]*class="[^"]*\b(reference|mw-editsection)\b[^"]*"[^>]*>.*?<\/span>/is', ' ', $html) ?? $html;

        // Two countries in one cell arrive in one of two shapes, and both need a
        // separator inserted or they render as "PhilippinesUnited States". A
        // `{{plainlist}}` produces list items; two bare `{{flag}}` templates produce
        // adjacent `flagicon` spans with nothing at all between them.
        $html = preg_replace('/<\/(li|p|div|tr)>/i', $sep, $html) ?? $html;
        $html = preg_replace('/(?<!^)(?=<span[^>]*class="[^"]*\bflagicon\b)/i', $sep, $html) ?? $html;

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Non-breaking spaces are everywhere in these tables and are not whitespace
        // as far as `trim` is concerned. The dagger marks a brand account and is
        // presentation, not part of the name.
        $text = str_replace(["\u{00A0}", "\u{200B}", '†', '‡'], [' ', '', '', ''], $text);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        // A run of separators — an empty list item between two full ones — is one
        // separator, and one at either end is none.
        $text = trim(preg_replace('/(?:\s*'.$sep.'\s*)+/', $sep, $text) ?? $text, " \t\n\r\0\x0B".$sep);

        return trim(str_replace($sep, ', ', $text));
    }

    private function normalise(string $label): string
    {
        return trim(strtolower(preg_replace('/[^a-z0-9\/ ]+/i', ' ', $label) ?? $label));
    }
}
