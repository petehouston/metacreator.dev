<?php

declare(strict_types=1);

use App\Domain\TopRanking\Enums\RankingPlatform;
use App\Domain\TopRanking\Wikipedia\WikitableParser;

/**
 * The parser, against markup shaped like the articles it actually reads.
 *
 * These fixtures are trimmed from the real MediaWiki output — the reference `<sup>`,
 * the `plainlinks` wrapper, the flag icons, the `<th scope="row">` rank cell. Every
 * one of them broke a naive implementation of this parser at some point, which is
 * why they are here rather than a tidied-up table.
 */
function wikitable(string $body, string $classes = 'wikitable sortable'): string
{
    return '<div class="mw-parser-output"><table class="'.$classes.'">'.$body.'</table></div>';
}

function parseRows(string $html, RankingPlatform $platform = RankingPlatform::Instagram, int $limit = 50): array
{
    return (new WikitableParser)->parse($html, 0, $platform, $limit);
}

it('maps columns by their header, not by position', function () {
    // The follower column is third here and second in the next case; a parser that
    // indexed by position would read a country as a follower count.
    $html = wikitable(<<<'HTML'
        <tr><th>Username</th><th>Owner</th><th>Followers<br/>(millions)</th><th>Country</th></tr>
        <tr>
            <td><span class="plainlinks"><a rel="nofollow" class="external text" href="https://www.instagram.com/cristiano">@cristiano</a></span></td>
            <td><a href="/wiki/Cristiano_Ronaldo">Cristiano Ronaldo</a></td>
            <td>678</td>
            <td><span class="flagicon nowrap"><img alt="" src="//x/flag.png"/></span><a href="/wiki/Portugal">Portugal</a></td>
        </tr>
    HTML);

    $rows = parseRows($html);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->handle)->toBe('cristiano')
        ->and($rows[0]->owner)->toBe('Cristiano Ronaldo')
        ->and($rows[0]->metric)->toBe(678.0)
        ->and($rows[0]->country)->toBe('Portugal')
        ->and($rows[0]->profileUrl)->toBe('https://www.instagram.com/cristiano');
});

it('reads a rank cell written as a row header without shifting every column', function () {
    // TikTok's table opens each row with `<th scope="row">`. Collecting only `<td>`
    // would put the owner where the handle should be, on this table and no other.
    $html = wikitable(<<<'HTML'
        <tr><th>Rank</th><th>Username</th><th>Owner</th><th>Followers<br/>(millions)</th><th>Likes<br/>(billions)</th></tr>
        <tr>
            <th scope="row">1</th>
            <td><span class="plainlinks"><a rel="nofollow" class="external text" href="https://www.tiktok.com/@khaby.lame">@khaby.lame</a></span></td>
            <td><a href="/wiki/Khaby_Lame">Khaby Lame</a></td>
            <td>162.7</td>
            <td>2.2</td>
        </tr>
    HTML);

    $rows = parseRows($html, RankingPlatform::TikTok);

    expect($rows[0]->handle)->toBe('khaby.lame')
        ->and($rows[0]->owner)->toBe('Khaby Lame')
        ->and($rows[0]->metric)->toBe(162.7)
        ->and($rows[0]->secondaryMetric)->toBe(2.2);
});

it('strips a citation from a number instead of concatenating it', function () {
    // `162.7<sup>[4]</sup>` read tag-by-tag becomes 162.74 — a plausible-looking
    // number that is wrong, which is the worst kind.
    $html = wikitable(<<<'HTML'
        <tr><th>Username</th><th>Followers<br/>(millions)</th></tr>
        <tr><td>@someone</td><td>162.7<sup id="cite_ref-4" class="reference"><a href="#cite_note-4">[4]</a></sup></td></tr>
    HTML);

    expect(parseRows($html)[0]->metric)->toBe(162.7);
});

it('separates two countries in one cell', function () {
    $html = wikitable(<<<'HTML'
        <tr><th>Username</th><th>Followers<br/>(millions)</th><th>Country</th></tr>
        <tr>
            <td>@bellapoarch</td>
            <td>91.8</td>
            <td><span class="flagicon nowrap"><img alt="" src="//x/ph.png"/></span><a href="/wiki/Philippines">Philippines</a><span class="flagicon nowrap"><img alt="" src="//x/us.png"/></span><a href="/wiki/United_States">United States</a></td>
        </tr>
    HTML);

    expect(parseRows($html)[0]->country)->toBe('Philippines, United States');
});

it('takes the profile link from a dedicated Link column when there is one', function () {
    // The YouTube article names the channel in one column and links it in another,
    // and the link is the only place the actual handle appears.
    $html = wikitable(<<<'HTML'
        <tr><th>Name</th><th>Link</th><th>Subscribers<br/>(millions)</th></tr>
        <tr>
            <td><a href="/wiki/MrBeast">MrBeast</a></td>
            <td><span class="plainlinks"><a rel="nofollow" class="external text" href="https://www.youtube.com/user/MrBeast6000">Link</a></span></td>
            <td>515</td>
        </tr>
    HTML);

    $rows = parseRows($html, RankingPlatform::YouTube);

    expect($rows[0]->name)->toBe('MrBeast')
        ->and($rows[0]->handle)->toBe('MrBeast6000')
        ->and($rows[0]->profileUrl)->toBe('https://www.youtube.com/user/MrBeast6000');
});

it('invents no profile URL for a row identified only by a display name', function () {
    // Half the Facebook Pages list has no handle. Stitching "Cristiano Ronaldo"
    // into a URL produces a link with a space in it that 404s for every reader.
    $html = wikitable(<<<'HTML'
        <tr><th>Rank</th><th>Page name</th><th>Followers<br/>(millions)</th></tr>
        <tr><th scope="row">2</th><td><a href="/wiki/Cristiano_Ronaldo">Cristiano Ronaldo</a></td><td>174</td></tr>
    HTML);

    $rows = parseRows($html, RankingPlatform::Facebook);

    expect($rows[0]->name)->toBe('Cristiano Ronaldo')
        ->and($rows[0]->handle)->toBeNull()
        ->and($rows[0]->profileUrl)->toBeNull();
});

it('keeps an exact count rather than mistaking it for an out-of-range value', function () {
    // Twitch publishes 1,112,947 where every other page publishes "515". A ceiling
    // tuned for magnitudes silently dropped this whole column.
    $html = wikitable(<<<'HTML'
        <tr><th>Rank</th><th>Channel</th><th>Total subscribers</th></tr>
        <tr><th scope="row">1</th><td><a rel="nofollow" class="external text" href="https://www.twitch.tv/KaiCenat">KaiCenat</a></td><td>1,112,947</td></tr>
    HTML);

    expect(parseRows($html, RankingPlatform::Twitch)[0]->metric)->toBe(1112947.0);
});

it('honours the row limit', function () {
    $rows = collect(range(1, 10))
        ->map(fn (int $n) => "<tr><td>@user{$n}</td><td>{$n}</td></tr>")
        ->implode('');

    $html = wikitable('<tr><th>Username</th><th>Followers<br/>(millions)</th></tr>'.$rows);

    expect(parseRows($html, limit: 4))->toHaveCount(4);
});

it('refuses a table whose header it cannot recognise, rather than importing nonsense', function () {
    // The failure that actually happens: the article is restructured. Silence here
    // would mean a public page quietly filled with columns read from the wrong place.
    $html = wikitable('<tr><th>Date achieved</th><th>Days held</th></tr><tr><td>2024</td><td>12</td></tr>');

    expect(fn () => parseRows($html))->toThrow(RuntimeException::class);
});

it('reports how many tables an article has when the configured index is missing', function () {
    $html = wikitable('<tr><th>Username</th><th>Followers</th></tr><tr><td>@a</td><td>1</td></tr>');

    expect(fn () => (new WikitableParser)->parse($html, 3, RankingPlatform::Instagram, 50))
        ->toThrow(RuntimeException::class, 'has 1 ranking table');
});

it('ignores tables that are not wikitables', function () {
    // Articles carry navboxes and infoboxes. Counting them would make the admin's
    // table index mean something different from what an editor sees on the page.
    $html = '<table class="infobox"><tr><th>Username</th><th>Followers</th></tr><tr><td>@nav</td><td>9</td></tr></table>'
        .wikitable('<tr><th>Username</th><th>Followers<br/>(millions)</th></tr><tr><td>@real</td><td>5</td></tr>');

    expect(parseRows($html)[0]->handle)->toBe('real');
});
