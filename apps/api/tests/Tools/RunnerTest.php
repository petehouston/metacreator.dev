<?php

declare(strict_types=1);

use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolResult;
use App\Domain\Tools\Enums\AccessReason;
use App\Domain\Tools\Enums\ResultView;
use App\Domain\Tools\Exceptions\ToolExecutionException;
use App\Domain\Tools\Models\Tool;
use App\Domain\Tools\Runners;
use App\Domain\Tools\Services\InputValidator;
use App\Providers\ToolServiceProvider;

/**
 * Runner behaviour, tested through the same validator the action uses.
 *
 * These are the tests that catch a runner whose schema and implementation have
 * drifted apart — the failure mode the engine's design is meant to make impossible.
 */
function runContext(bool $paid = false): RunContext
{
    return new RunContext(
        tool: new Tool(['version' => 1, 'key' => 'test']),
        accessReason: $paid ? AccessReason::Subscription : AccessReason::Free,
        runUlid: 'TEST',
    );
}

function runRunner(object $runner, array $input, bool $paid = false): ToolResult
{
    $validated = app(InputValidator::class)->validate($runner, $input);

    return $runner->run($validated, runContext($paid));
}

it('registers every runner exactly once', function () {
    $keys = array_map(fn (string $class) => $class::key(), ToolServiceProvider::runners());

    expect($keys)->toEqual(array_unique($keys))
        ->and($keys)->not->toBeEmpty();
});

it('gives every runner a valid, form-generatable schema', function (string $class) {
    $schema = app($class)->inputSchema();

    expect($schema)->toHaveKey('type')
        ->and($schema['type'])->toBe('object')
        ->and($schema)->toHaveKey('properties')
        ->and($schema['properties'])->not->toBeEmpty();

    // The form generator uses `title` as the field label and `description` as the
    // hint. A property without a title renders as a humanised key, which is nearly
    // always worse than a human-written one.
    foreach ($schema['properties'] as $name => $property) {
        expect(array_key_exists('type', $property))
            ->toBeTrue("Property [{$name}] in [{$class}] must declare a type");

        expect(array_key_exists('title', $property))
            ->toBeTrue("Property [{$name}] in [{$class}] must declare a title");
    }
})->with(fn () => ToolServiceProvider::runners());

it('calculates engagement rate and benchmarks it', function () {
    $result = runRunner(app(Runners\EngagementRateCalculatorRunner::class), [
        'platform' => 'instagram',
        'followers' => 10000,
        'likes' => 500,
        'comments' => 50,
    ]);

    expect($result->view)->toBe(ResultView::KeyValue);

    $rate = collect($result->data['pairs'])->firstWhere('label', 'Engagement rate (by followers)');

    // 550 interactions / 10,000 followers = 5.5%
    expect($rate['value'])->toBe('5.5%')
        ->and($result->summary)->toContain('5.5%');
});

it('counts X posts the way X does', function () {
    $result = runRunner(app(Runners\CharacterCounterRunner::class), [
        // A link counts as a flat 23 regardless of its real length.
        'text' => 'Hello https://example.com/a/very/long/path/that/keeps/going',
    ]);

    // "Hello " is 6 characters, plus 23 for the link.
    expect($result->meta['characters_weighted'])->toBe(29);
});

it('never splits a thread mid-sentence when it can avoid it', function () {
    $text = str_repeat('This is a complete sentence. ', 40);

    $result = runRunner(app(Runners\ThreadSplitterRunner::class), [
        'text' => trim($text),
        'limit' => 280,
        'numbering' => 'none',
    ]);

    foreach ($result->data['blocks'] as $block) {
        expect(trim($block['text']))->toEndWith('.');
    }
});

it('accounts for numbering when splitting, so no post overflows', function () {
    $result = runRunner(app(Runners\ThreadSplitterRunner::class), [
        'text' => str_repeat('Sentence number one here. ', 60),
        'limit' => 280,
        'numbering' => 'slash',
    ]);

    foreach ($result->data['blocks'] as $block) {
        expect($block['meta']['over_limit'])->toBeFalse();
    }
});

it('produces the same giveaway winners for the same seed', function () {
    $input = [
        'entries' => implode("\n", array_map(fn (int $i) => "@entrant{$i}", range(1, 50))),
        'winners' => 3,
        'seed' => 'published-in-advance',
    ];

    $first = runRunner(app(Runners\GiveawayWinnerPickerRunner::class), $input);
    $second = runRunner(app(Runners\GiveawayWinnerPickerRunner::class), $input);

    expect($first->data['items'])->toEqual($second->data['items'])
        ->and($first->meta['verifiable'])->toBeTrue();
});

it('produces different giveaway winners for different seeds', function () {
    $entries = implode("\n", array_map(fn (int $i) => "@entrant{$i}", range(1, 200)));

    $a = runRunner(app(Runners\GiveawayWinnerPickerRunner::class),
        ['entries' => $entries, 'winners' => 5, 'seed' => 'seed-a']);
    $b = runRunner(app(Runners\GiveawayWinnerPickerRunner::class),
        ['entries' => $entries, 'winners' => 5, 'seed' => 'seed-b']);

    expect($a->data['items'])->not->toEqual($b->data['items']);
});

it('warns when a giveaway draw cannot be verified', function () {
    $result = runRunner(app(Runners\GiveawayWinnerPickerRunner::class), [
        'entries' => "@a\n@b\n@c",
        'winners' => 1,
    ]);

    expect($result->warnings)->not->toBeEmpty()
        ->and($result->meta['verifiable'])->toBeFalse();
});

it('parses every shape of YouTube URL', function (string $url) {
    $result = runRunner(app(Runners\YouTubeThumbnailDownloaderRunner::class), ['url' => $url]);

    expect($result->meta['video_id'])->toBe('dQw4w9WgXcQ');
})->with([
    'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    'https://youtu.be/dQw4w9WgXcQ?t=42',
    'https://www.youtube.com/shorts/dQw4w9WgXcQ',
    'https://m.youtube.com/watch?list=PL123&v=dQw4w9WgXcQ',
    'https://www.youtube.com/embed/dQw4w9WgXcQ',
    'dQw4w9WgXcQ',
]);

it('rejects a URL that is not a YouTube video', function () {
    runRunner(app(Runners\YouTubeThumbnailDownloaderRunner::class), ['url' => 'https://vimeo.com/12345']);
})->throws(ToolExecutionException::class);

it('refuses to build a UTM link pointing at internal infrastructure', function (string $url) {
    runRunner(app(Runners\UtmBuilderRunner::class), [
        'url' => $url,
        'source' => 'test',
        'medium' => 'social',
    ]);
})->with([
    'loopback' => 'http://127.0.0.1:6379/',
    'localhost' => 'http://localhost/admin',
    'cloud metadata' => 'http://169.254.169.254/latest/meta-data/',
    'private range' => 'http://192.168.1.1/',
])->throws(ToolExecutionException::class);

it('normalises UTM parameters and says when it changed them', function () {
    $result = runRunner(app(Runners\UtmBuilderRunner::class), [
        'url' => 'https://example.com/pricing?existing=keep',
        'source' => 'Instagram',
        'medium' => 'Bio Link',
        'campaign' => 'Spring Launch',
    ]);

    $tagged = collect($result->data['pairs'])->firstWhere('label', 'Tagged URL')['value'];

    expect($tagged)
        ->toContain('utm_source=instagram')
        ->toContain('utm_medium=bio-link')
        ->toContain('utm_campaign=spring-launch')
        // A destination's own query string must survive being tagged.
        ->toContain('existing=keep');

    expect($result->warnings)->not->toBeEmpty();
});

it('gives paying users a larger hashtag set than anonymous ones', function () {
    $input = ['topic' => 'sourdough baking', 'platform' => 'instagram'];

    $free = runRunner(app(Runners\HashtagGeneratorRunner::class), $input);
    $paid = runRunner(app(Runners\HashtagGeneratorRunner::class), $input, paid: true);

    $count = fn ($result) => collect($result->data['items'])->sum(fn ($item) => $item['meta']['count']);

    expect($count($paid))->toBeGreaterThan($count($free));
});

it('penalises a headline that uses known clickbait', function () {
    $honest = runRunner(app(Runners\HeadlineAnalyzerRunner::class), [
        'headline' => 'How I Grew to 100k Subscribers in 9 Months (Without Going Viral)',
        'context' => 'youtube',
    ]);

    $bait = runRunner(app(Runners\HeadlineAnalyzerRunner::class), [
        'headline' => "You Won't Believe What Happened Next!!!",
        'context' => 'youtube',
    ]);

    expect($honest->data['overall'])->toBeGreaterThan($bait->data['overall'])
        ->and($bait->data['fixes'])->not->toBeEmpty();
});

it('always returns fixes ordered by severity', function () {
    $result = runRunner(app(Runners\HeadlineAnalyzerRunner::class), [
        'headline' => 'THIS IS A VERY LONG SHOUTY HEADLINE THAT GOES ON AND ON WELL PAST ANY REASONABLE TRUNCATION POINT!!!',
        'context' => 'youtube',
    ]);

    $rank = ['high' => 3, 'medium' => 2, 'low' => 1];
    $severities = array_map(fn (array $fix) => $rank[$fix['severity']], $result->data['fixes']);

    expect($severities)->toEqual(collect($severities)->sortDesc()->values()->all());
});
