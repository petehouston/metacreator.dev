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
use Illuminate\Support\Facades\Http;

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

/**
 * Every offline runner, run once through the validator with a realistic input.
 *
 * Not an assertion about *what* each tool says — that belongs with the tool — but
 * proof that its schema accepts the input its catalog entry advertises and that the
 * run produces a renderable result. Runners that fetch a URL are excluded: they
 * belong in a suite that can fake the HTTP client.
 */
it('runs every offline runner against a representative input', function (string $class, array $input) {
    $result = runRunner(app($class), $input);

    expect($result)->toBeInstanceOf(ToolResult::class)
        ->and($result->view)->toBeInstanceOf(ResultView::class)
        ->and($result->data)->toBeArray();
})->with([
    [Runners\AspectRatioCalculatorRunner::class, ['width' => 1920, 'height' => 1080, 'new_width' => 1280]],
    [Runners\CarouselSplitterRunner::class, ['panels' => 3, 'ratio' => '4:5']],
    [Runners\ColorPaletteExtractorRunner::class, ['colors' => 5]],
    [Runners\CtaGeneratorRunner::class, ['topic' => 'sourdough baking', 'goal' => 'comment']],
    [Runners\EmojiPickerRunner::class, ['query' => 'growth']],
    [Runners\FacebookAdTextCounterRunner::class, ['primary_text' => 'Free tools for creators.', 'headline' => 'Try it']],
    [Runners\FacebookPostPreviewRunner::class, ['text' => str_repeat('A sentence about content. ', 30), 'attachment' => 'photo']],
    [Runners\FancyTextGeneratorRunner::class, ['text' => 'creator studio']],
    [Runners\FollowerMilestoneCountdownRunner::class, ['current' => 8400, 'weekly_growth' => 180]],
    [Runners\HandleStrengthRunner::class, ['handle' => '@the_bread_lab_99']],
    [Runners\InstagramBioPreviewRunner::class, ['name' => 'Sam', 'bio' => "Real bread, no mystique.\nTips weekly 🍞"]],
    [Runners\LinkedInPostPreviewRunner::class, ['text' => str_repeat('A line about the work. ', 20)]],
    [Runners\PinterestPinPreviewRunner::class, ['title' => 'Sourdough starter schedule for beginners',
        'description' => 'A seven-day feeding schedule for a new starter.', 'aspect' => '2:3']],
    [Runners\PinterestPinSeoCheckerRunner::class, ['keyword' => 'sourdough starter schedule',
        'title' => 'Sourdough starter schedule for beginners',
        'description' => 'A simple seven-day sourdough starter schedule for beginners, with what to look for '
            .'each day and how to rescue a starter that has stalled.',
        'board' => 'Sourdough basics', 'link' => 'https://example.com/starter']],
    [Runners\ThreadsBioPreviewRunner::class, ['name' => 'Sam', 'handle' => 'thebreadlab',
        'bio' => "Real bread, no mystique.\nWeekly tips 🍞"]],
    [Runners\ThreadsPostPreviewRunner::class, ['text' => str_repeat('A line about the work. ', 12)]],
    [Runners\PinImageSizerRunner::class, ['focus' => 'top']],
    [Runners\PostingTimezoneConverterRunner::class, ['datetime' => '2026-09-01 18:30', 'timezone' => 'Europe/London']],
    [Runners\ReadabilityCheckerRunner::class, ['text' => 'This is a sentence. This is another one, slightly longer than the first.', 'audience' => 'social']],
    [Runners\QrCodeGeneratorRunner::class, ['content' => 'https://example.com/launch']],
    [Runners\ReelsCoverCropperRunner::class, ['grid_focus' => 'center']],
    [Runners\SafeZoneGuideRunner::class, ['surface' => 'tiktok']],
    [Runners\SocialImageResizerRunner::class, ['platform' => 'pinterest', 'format' => 'jpeg']],
    [Runners\StoryTemplateSizerRunner::class, ['surface' => 'tiktok']],
    [Runners\ScriptTimerRunner::class, ['script' => '[hook] Start with the result. Nobody stays for a preamble.', 'target_seconds' => 30]],
    [Runners\TextCaseConverterRunner::class, ['text' => 'how i grew to 100k subscribers']],
    [Runners\TweetScreenshotRunner::class, ['name' => 'Ada Lovelace', 'handle' => 'ada',
        'text' => 'It can do whatever we know how to order it to perform.', 'theme' => 'dim']],
    [Runners\TikTokMoneyCalculatorRunner::class, ['monthly_views' => 800000, 'followers' => 45000, 'niche' => 'beauty']],
    [Runners\WordCounterRunner::class, ['text' => 'Most creators quit at month four.']],
    [Runners\YouTubeChannelDescriptionGeneratorRunner::class, ['channel_name' => 'The Slow Loaf',
        'topic' => 'sourdough baking for small kitchens', 'audience' => 'home bakers', 'tone' => 'expert',
        'keywords' => 'sourdough starter, bread scoring']],
    [Runners\YouTubeContentCalendarRunner::class, ['start_date' => '2026-09-07', 'weeks' => 4,
        'long_form_per_week' => 1, 'shorts_per_week' => 3]],
    [Runners\YouTubeEmbedCodeGeneratorRunner::class, ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'start' => '1:23', 'width' => 560]],
    [Runners\YouTubePartnerProgramCheckerRunner::class, ['subscribers' => 740, 'watch_hours' => 2100,
        'shorts_views' => 450000, 'uploads_90d' => 6]],
    [Runners\YouTubeMoneyCalculatorRunner::class, ['monthly_views' => 250000, 'niche' => 'tech', 'audience' => 'us_uk']],
    [Runners\YouTubeTimestampLinkBuilderRunner::class, [
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'timestamps' => "0:00 Intro\n1:12 The problem\n4:35 The fix",
    ]],
]);

it('refuses a timestamp list with no recognisable times', function () {
    runRunner(app(Runners\YouTubeTimestampLinkBuilderRunner::class), [
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'timestamps' => "Intro\nThe problem",
    ]);
})->throws(ToolExecutionException::class);

it('flags chapters that break YouTube’s own rules', function () {
    $result = runRunner(app(Runners\YouTubeTimestampLinkBuilderRunner::class), [
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'timestamps' => "1:00 Late start\n1:05 Too close",
    ]);

    // No 0:00, fewer than three entries, and under 10 seconds apart: all three.
    expect($result->warnings)->toHaveCount(3);
});

/**
 * Preview tools are the ones people judge the site on, and their whole value is
 * visual: a frame the frontend cannot draw is a blank result page.
 */
it('gives every preview tool at least one drawable frame', function (string $class, array $input) {
    $result = runRunner(app($class), $input);

    expect($result->view)->toBe(ResultView::SocialPreview)
        ->and($result->data['frames'])->not->toBeEmpty();

    foreach ($result->data['frames'] as $frame) {
        expect($frame)->toHaveKeys(['platform', 'surface', 'kind'])
            ->and($frame['surface'])->not->toBeEmpty();
    }
})->with([
    [Runners\FacebookPostPreviewRunner::class, ['text' => 'A short post.', 'attachment' => 'photo']],
    [Runners\InstagramBioPreviewRunner::class, ['bio' => 'Real bread, no mystique.']],
    [Runners\LinkedInPostPreviewRunner::class, ['text' => 'A short post.']],
    [Runners\PinterestPinPreviewRunner::class, ['title' => 'A pin title']],
    [Runners\SafeZoneGuideRunner::class, ['surface' => 'all']],
    [Runners\ThreadsBioPreviewRunner::class, ['bio' => 'Real bread, no mystique.']],
    [Runners\ThreadsPostPreviewRunner::class, ['text' => 'A short post.']],
]);

it('splits the post at the fold and keeps the hidden half', function () {
    $text = str_repeat('A sentence about content. ', 30);

    $result = runRunner(app(Runners\FacebookPostPreviewRunner::class), [
        'text' => trim($text),
        'attachment' => 'link',
    ]);

    foreach ($result->data['frames'] as $frame) {
        // Nothing may be lost in the split: visible + hidden must reconstruct the post.
        expect($frame['body']['visible'].$frame['body']['hidden'])->toBe(trim($text))
            ->and($frame['body']['hidden'])->not->toBeEmpty();
    }
});

it('leaves a short post whole', function () {
    $result = runRunner(app(Runners\LinkedInPostPreviewRunner::class), ['text' => 'Short enough to survive.']);

    foreach ($result->data['frames'] as $frame) {
        expect($frame['body']['hidden'])->toBe('');
    }
});

it('turns an over-long Threads post into a publishable chain', function () {
    $result = runRunner(app(Runners\ThreadsPostPreviewRunner::class), [
        'text' => str_repeat('This is a complete sentence about the work. ', 40),
    ]);

    $chain = array_filter(
        $result->data['frames'],
        fn (array $frame) => str_starts_with($frame['surface'], 'Chain post'),
    );

    expect($chain)->not->toBeEmpty()
        ->and($result->warnings)->not->toBeEmpty();

    foreach ($chain as $frame) {
        expect(mb_strlen($frame['body']['full']))->toBeLessThanOrEqual(500);
    }
});

it('scores a Pin higher when the keyword is where Pinterest reads it', function () {
    $optimised = runRunner(app(Runners\PinterestPinSeoCheckerRunner::class), [
        'keyword' => 'sourdough starter schedule',
        'title' => 'Sourdough starter schedule for beginners',
        'description' => 'A simple seven-day sourdough starter schedule for beginners, with what to look for '
            .'each day and how to rescue a starter that has stalled overnight.',
        'board' => 'Sourdough basics',
        'link' => 'https://example.com/starter',
    ]);

    $vague = runRunner(app(Runners\PinterestPinSeoCheckerRunner::class), [
        'keyword' => 'sourdough starter schedule',
        'title' => 'My new recipe',
        'description' => '#baking #bread #yum #foodie #sourdough #homemade',
    ]);

    expect($optimised->data['overall'])->toBeGreaterThan($vague->data['overall'])
        ->and($vague->data['fixes'])->not->toBeEmpty();
});

it('shades the same margins it reports for a safe zone', function () {
    $result = runRunner(app(Runners\SafeZoneGuideRunner::class), ['surface' => 'tiktok']);

    $frame = $result->data['frames'][0];

    expect($frame['canvas'])->toEqual([
        'width' => 1080, 'height' => 1920,
        'top' => 130, 'bottom' => 500, 'left' => 60, 'right' => 260,
    ]);
});

/**
 * Image tools return their output inline as data URIs, because the upload-and-store
 * path they were originally blocked on does not exist yet. That makes two things
 * worth asserting: the bytes really are a decodable image of the size claimed, and
 * a run with no source URL still produces something rather than failing.
 */
it('produces real, correctly sized images from every crop tool', function (string $class, array $input, array $expected) {
    $result = runRunner(app($class), $input);

    expect($result->view)->toBe(ResultView::MediaGallery)
        ->and($result->artifacts)->toHaveCount(count($expected));

    foreach ($result->artifacts as $index => $artifact) {
        expect($artifact->url)->toStartWith('data:image/');

        $bytes = base64_decode(substr($artifact->url, strpos($artifact->url, ',') + 1), true);
        $size = getimagesizefromstring((string) $bytes);

        expect($size)->not->toBeFalse()
            ->and([$size[0], $size[1]])->toEqual($expected[$index]);
    }
})->with([
    'reels cover' => [Runners\ReelsCoverCropperRunner::class, [],
        [[1080, 1920], [1080, 1350], [1080, 1080]]],
    'pin sizes' => [Runners\PinImageSizerRunner::class, [],
        [[1000, 1500], [1000, 1000], [1080, 1920]]],
    'story sizer' => [Runners\StoryTemplateSizerRunner::class, ['surface' => 'tiktok'],
        [[1080, 1920], [1080, 1920]]],
    'carousel' => [Runners\CarouselSplitterRunner::class, ['panels' => 4, 'ratio' => '1:1'],
        [[1080, 1080], [1080, 1080], [1080, 1080], [1080, 1080]]],
]);

it('cuts carousel panels that reconstruct the source without a gap', function () {
    $result = runRunner(app(Runners\CarouselSplitterRunner::class), ['panels' => 5, 'ratio' => '4:5']);

    // Five panels of equal width, in order: the seam only lines up if every cut
    // lands on an integer boundary of the source.
    expect($result->artifacts)->toHaveCount(5)
        ->and($result->meta['panel_width'])->toBe(1080);

    foreach ($result->artifacts as $index => $artifact) {
        expect($artifact->filename)->toBe(sprintf('carousel-%02d.jpg', $index + 1))
            ->and($artifact->label)->toBe('Slide '.($index + 1).' of 5');
    }
});

it('compresses less at a lower quality, and says by how much', function () {
    $result = runRunner(app(Runners\ImageCompressorRunner::class), ['format' => 'jpeg', 'max_width' => 800]);

    $sizes = array_map(fn ($artifact) => $artifact->size, $result->artifacts);

    // The ladder runs from highest quality to lowest, so each step must be smaller.
    expect($sizes)->toEqual(collect($sizes)->sortDesc()->values()->all())
        ->and($result->meta['steps'])->toHaveCount(4);
});

it('warns when a conversion is about to throw transparency away', function () {
    $toJpeg = runRunner(app(Runners\ImageFormatConverterRunner::class), ['format' => 'jpeg']);
    $toWebp = runRunner(app(Runners\ImageFormatConverterRunner::class), ['format' => 'webp']);

    expect(implode(' ', $toJpeg->warnings))->toContain('no transparency')
        ->and(implode(' ', $toWebp->warnings))->not->toContain('no transparency');
});

it('extracts a palette whose colours are ordered by how much of the image they cover', function () {
    $result = runRunner(app(Runners\ColorPaletteExtractorRunner::class), ['colors' => 6]);

    $shares = array_map(
        fn (array $row) => (float) rtrim($row['share'], '%'),
        $result->data['rows'],
    );

    expect($result->data['rows'])->toHaveCount(6)
        ->and($shares)->toEqual(collect($shares)->sortDesc()->values()->all());

    foreach ($result->data['rows'] as $row) {
        expect($row['hex'])->toMatch('/^#[0-9A-F]{6}$/');
    }
});

it('encodes a scannable QR code and flags one nobody can scan', function () {
    $good = runRunner(app(Runners\QrCodeGeneratorRunner::class), ['content' => 'https://example.com']);

    $invisible = runRunner(app(Runners\QrCodeGeneratorRunner::class), [
        'content' => 'https://example.com',
        'foreground' => '#8A8A8A',
        'background' => '#909090',
    ]);

    expect($good->artifacts[0]->mimeType)->toBe('image/svg+xml')
        ->and($good->meta['contrast_ratio'])->toBeGreaterThan(4.5)
        ->and(implode(' ', $invisible->warnings))->toContain('too close in brightness');
});

it('refuses to encode a QR code pointing at a private address', function () {
    runRunner(app(Runners\QrCodeGeneratorRunner::class), ['content' => 'http://169.254.169.254/latest/meta-data/']);
})->throws(ToolExecutionException::class);

it('wraps a post card without ever overrunning the canvas', function () {
    $result = runRunner(app(Runners\TweetScreenshotRunner::class), [
        'name' => 'Ada Lovelace',
        'handle' => '@ada',
        // A URL far longer than one line: it has to be cut, not allowed to run off.
        'text' => 'Read it here https://example.com/'.str_repeat('a', 200),
        'theme' => 'dark',
    ]);

    $svg = base64_decode(substr($result->artifacts[0]->url, strpos($result->artifacts[0]->url, ',') + 1), true);

    expect($svg)->toContain('<svg')
        ->and($svg)->toContain('#000000')
        // The handle is normalised to exactly one @, however it was typed.
        ->and($svg)->toContain('@ada')
        ->and($svg)->not->toContain('@@ada');
});

it('says plainly that a post card is a mock-up, not a screenshot', function () {
    $result = runRunner(app(Runners\TweetScreenshotRunner::class), [
        'name' => 'Ada', 'handle' => 'ada', 'text' => 'Hello.',
    ]);

    expect(implode(' ', $result->warnings))->toContain('mock-up');
});

it('rejects a handle no network could accept', function () {
    runRunner(app(Runners\UsernameAvailabilityRunner::class), ['handle' => 'not a handle!']);
})->throws(ToolExecutionException::class);

it('never adds the two monetization routes together', function () {
    // Half of each threshold qualifies on neither route, which is the arithmetic
    // mistake the tool exists to correct.
    $result = runRunner(app(Runners\YouTubePartnerProgramCheckerRunner::class), [
        'subscribers' => 1200,
        'watch_hours' => 2000,
        'shorts_views' => 5_000_000,
        'uploads_90d' => 4,
    ]);

    expect($result->meta['ads_eligible'])->toBeFalse();

    $watch = collect($result->data['sections'])->firstWhere('key', 'watch');

    // 5M of 10M Shorts views is the better of the two routes: 50, not 50 + 50.
    expect($watch['score'])->toBe(50);
});

it('disqualifies a monetization application on the account rules alone', function () {
    $result = runRunner(app(Runners\YouTubePartnerProgramCheckerRunner::class), [
        'subscribers' => 50_000,
        'watch_hours' => 90_000,
        'shorts_views' => 0,
        'uploads_90d' => 30,
        'no_active_strikes' => false,
    ]);

    expect($result->meta['ads_eligible'])->toBeFalse()
        ->and($result->summary)->toContain('hard no');
});

it('reports both monetization tiers once a channel clears them', function () {
    $result = runRunner(app(Runners\YouTubePartnerProgramCheckerRunner::class), [
        'subscribers' => 1_000,
        'watch_hours' => 4_000,
        'shorts_views' => 0,
        'uploads_90d' => 3,
    ]);

    expect($result->meta['fan_funding_eligible'])->toBeTrue()
        ->and($result->meta['ads_eligible'])->toBeTrue();
});

/**
 * A stripped-down channel page carrying only what the runner reads: the identity
 * block, the header line, and whichever monetization markers a case needs.
 */
function channelPage(string $subscribers, bool $memberships = false, bool $store = false): string
{
    $html = '<link rel="canonical" href="https://www.youtube.com/channel/UCBJycsmduvYEL83R_U4JriQ">'
        .'<meta property="og:title" content="The Slow Loaf">'
        .'<meta property="og:description" content="Real bread, no mystique.">'
        .'<meta property="og:image" content="https://yt3.googleusercontent.com/abc=s900-c-k-c0x00ffffff-no-rj">'
        .'"banner":"https://yt3.googleusercontent.com/xyz=w2560-fcrop64=1,00000000ffffffff-nd-v1"'
        .'{"content":"@theslowloaf"},{"content":"'.$subscribers.' subscribers"},{"content":"212 videos"}';

    if ($memberships) {
        $html .= '{"iconName":"SPONSORSHIP_STAR","title":"Join"}';
    }

    if ($store) {
        $html .= '{"url":"/@theslowloaf/store","webPageType":"WEB_PAGE_TYPE_CHANNEL"}';
    }

    return $html;
}

it('confirms monetization from a feature only monetized channels have', function () {
    Http::fake(['www.youtube.com/*' => Http::response(channelPage('120K', memberships: true))]);

    $result = runRunner(app(Runners\YouTubeChannelMonetizationCheckerRunner::class), ['channel' => '@theslowloaf']);

    expect($result->view)->toBe(ResultView::SocialPreview)
        ->and($result->meta['monetization'])->toBe('enabled')
        ->and($result->meta['memberships_enabled'])->toBeTrue()
        ->and($result->summary)->toContain('has monetization enabled')
        ->and($result->warnings)->toBeEmpty();

    $frame = $result->data['frames'][0];

    // The card is the point of the tool: real artwork, and a way out to the channel.
    expect($frame['kind'])->toBe('channel')
        ->and($frame['artwork']['avatar'])->toContain('yt3.googleusercontent.com/abc')
        ->and($frame['artwork']['banner'])->toContain('yt3.googleusercontent.com/xyz')
        ->and($frame['cta'])->toBe(['label' => 'View channel', 'url' => 'https://www.youtube.com/@theslowloaf'])
        ->and($frame['status']['tone'])->toBe('ok');
});

it('rules monetization out below the 500-subscriber floor', function () {
    Http::fake(['www.youtube.com/*' => Http::response(channelPage('412'))]);

    $result = runRunner(app(Runners\YouTubeChannelMonetizationCheckerRunner::class), ['channel' => 'theslowloaf']);

    expect($result->meta['monetization'])->toBe('disabled')
        ->and($result->meta['subscribers_approx'])->toBe(412)
        ->and($result->summary)->toContain('is not monetized');
});

it('refuses to guess when a channel publishes no monetization feature', function () {
    // Past the floor, but running neither memberships nor a shop: the state a
    // monetized ads-only channel and an unmonetized one share exactly.
    Http::fake(['www.youtube.com/*' => Http::response(channelPage('12K'))]);

    $result = runRunner(app(Runners\YouTubeChannelMonetizationCheckerRunner::class), ['channel' => '@theslowloaf']);

    expect($result->meta['monetization'])->toBe('unconfirmed')
        ->and($result->meta['subscribers_approx'])->toBe(12_000)
        ->and($result->data['frames'][0]['status']['tone'])->toBe('warn')
        ->and(implode(' ', $result->warnings))->toContain('not the same as');
});

it('does not read a merch link in a description as a Shopping shelf', function () {
    $html = channelPage('12K').'{"text":"Shop here: https://shop.example.com/store"}';

    Http::fake(['www.youtube.com/*' => Http::response($html)]);

    $result = runRunner(app(Runners\YouTubeChannelMonetizationCheckerRunner::class), ['channel' => '@theslowloaf']);

    expect($result->meta['shopping_enabled'])->toBeFalse()
        ->and($result->meta['monetization'])->toBe('unconfirmed');
});

it('spaces a content calendar instead of clustering it', function () {
    $result = runRunner(app(Runners\YouTubeContentCalendarRunner::class), [
        'start_date' => '2026-09-09',
        'weeks' => 1,
        'long_form_per_week' => 1,
        'shorts_per_week' => 3,
        'pillars' => "Tutorial\nReview",
    ]);

    expect($result->data['rows'])->toHaveCount(4);

    // Four slots across seven days must land on four different days.
    $days = array_unique(array_column($result->data['rows'], 'date'));

    expect($days)->toHaveCount(4);
});

it('starts a content calendar on the Monday of the chosen week', function () {
    $result = runRunner(app(Runners\YouTubeContentCalendarRunner::class), [
        // A Thursday: the calendar still begins on the Monday before it.
        'start_date' => '2026-09-10',
        'weeks' => 2,
        'long_form_per_week' => 7,
        'shorts_per_week' => 0,
        'publish_time' => '09:30',
    ]);

    expect($result->data['rows'][0]['date'])->toBe('Mon 7 Sep 2026')
        ->and($result->data['rows'][0]['time'])->toBe('09:30')
        ->and($result->data['rows'])->toHaveCount(14);
});

it('rotates content pillars so none is starved', function () {
    $result = runRunner(app(Runners\YouTubeContentCalendarRunner::class), [
        'start_date' => '2026-09-07',
        'weeks' => 3,
        'long_form_per_week' => 1,
        'shorts_per_week' => 2,
        'pillars' => "Tutorial\nReview\nBehind the scenes",
    ]);

    $counts = array_count_values(array_column($result->data['rows'], 'pillar'));

    expect($counts)->toEqual(['Tutorial' => 3, 'Review' => 3, 'Behind the scenes' => 3]);
});

it('refuses a content calendar with nothing in it', function () {
    runRunner(app(Runners\YouTubeContentCalendarRunner::class), [
        'start_date' => '2026-09-07',
        'long_form_per_week' => 0,
        'shorts_per_week' => 0,
    ]);
})->throws(ToolExecutionException::class);

it('front-loads a channel description and shows what the fold cuts', function () {
    $result = runRunner(app(Runners\YouTubeChannelDescriptionGeneratorRunner::class), [
        'channel_name' => 'The Slow Loaf',
        'topic' => 'sourdough baking for small kitchens',
        'audience' => 'home bakers with no proving drawer',
        'schedule' => 'every Tuesday',
        'tone' => 'friendly',
        'keywords' => 'sourdough starter, bread scoring',
    ]);

    $blocks = collect($result->data['blocks']);

    // The searchable phrase has to survive the 150-character preview.
    expect($blocks->first()['text'])->toContain('sourdough baking for small kitchens')
        ->and($blocks->last()['text'])->toContain('sourdough baking for small kitchens')
        ->and(mb_strlen($blocks->last()['text']))->toBeLessThanOrEqual(151);

    // Keywords are worked into a sentence rather than listed.
    expect($blocks->get(1)['text'])->toContain('sourdough starter and bread scoring');
});

it('warns when a channel description would be rejected for length', function () {
    $result = runRunner(app(Runners\YouTubeChannelDescriptionGeneratorRunner::class), [
        'channel_name' => 'Test',
        // Every field at, but not over, the length its schema allows.
        'topic' => str_repeat('a long topic phrase ', 7),
        'audience' => str_repeat('a long audience phrase ', 6),
        'schedule' => str_repeat('weekly ', 14),
        'keywords' => implode(', ', array_fill(0, 8, str_repeat('keyword ', 4))),
        'links' => implode("\n", array_map(
            fn (int $i) => "A reasonably long link label {$i} | https://example.com/a/long/path/{$i}",
            range(1, 6),
        )),
    ]);

    expect(implode(' ', $result->warnings))->toContain('over YouTube’s 1,000-character limit');
});

it('drops a half-written link rather than rendering it', function () {
    $result = runRunner(app(Runners\YouTubeChannelDescriptionGeneratorRunner::class), [
        'channel_name' => 'The Slow Loaf',
        'topic' => 'sourdough',
        'links' => "Newsletter | https://example.com\nNo URL here\n| https://example.com/orphan",
    ]);

    $full = collect($result->data['blocks'])->get(1)['text'];

    expect($full)->toContain('Newsletter: https://example.com')
        ->and($full)->not->toContain('No URL here')
        ->and($full)->not->toContain('orphan');
});

it('tells the user plainly when comment search has no API key', function () {
    runRunner(app(Runners\YouTubeCommentFinderRunner::class), [
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'query' => 'timestamp',
    ]);
})->throws(ToolExecutionException::class, 'needs a YouTube Data API key');

it('forces mute on an autoplaying embed, because every browser does', function () {
    $result = runRunner(app(Runners\YouTubeEmbedCodeGeneratorRunner::class), [
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'autoplay' => true,
    ]);

    expect($result->meta['embed_url'])->toContain('autoplay=1')
        ->toContain('mute=1')
        ->and(implode(' ', $result->warnings))->toContain('Autoplay forces mute');
});

it('names the video as its own playlist so a loop actually loops', function () {
    $result = runRunner(app(Runners\YouTubeEmbedCodeGeneratorRunner::class), [
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'loop' => true,
    ]);

    // `loop=1` alone silently does nothing; the playlist parameter is what makes it work.
    expect($result->meta['embed_url'])->toContain('loop=1')->toContain('playlist=dQw4w9WgXcQ');
});

it('defaults an embed to the privacy-enhanced domain', function () {
    $default = runRunner(app(Runners\YouTubeEmbedCodeGeneratorRunner::class), [
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ]);

    $standard = runRunner(app(Runners\YouTubeEmbedCodeGeneratorRunner::class), [
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'privacy_mode' => false,
    ]);

    expect($default->meta['embed_url'])->toStartWith('https://www.youtube-nocookie.com/embed/')
        ->and($standard->meta['embed_url'])->toStartWith('https://www.youtube.com/embed/')
        ->and(implode(' ', $standard->warnings))->toContain('tracking cookies');
});

it('reads a timestamp start in either notation', function (string $input, int $seconds) {
    $result = runRunner(app(Runners\YouTubeEmbedCodeGeneratorRunner::class), [
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'start' => $input,
    ]);

    expect($result->meta['embed_url'])->toContain("start={$seconds}");
})->with([
    ['90', 90],
    ['1:30', 90],
    ['1:02:03', 3723],
]);

it('refuses a start time it cannot parse rather than silently dropping it', function () {
    runRunner(app(Runners\YouTubeEmbedCodeGeneratorRunner::class), [
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'start' => 'halfway',
    ]);
})->throws(ToolExecutionException::class);

it('rejects a handle that breaks YouTube’s own rules before spending a request', function (string $handle) {
    $result = runRunner(app(Runners\YouTubeHandleAvailabilityRunner::class), ['handle' => $handle]);

    expect($result->meta['valid'])->toBeFalse()
        ->and($result->data['rows'][0]['status'])->toBe('Invalid');
})->with([
    'too short' => 'ab',
    'too long' => 'abcdefghijklmnopqrstuvwxyz01234',
    'illegal character' => 'the slow loaf',
    'reads as a number' => '123.456',
]);

/**
 * The RSS feed generator, whose whole difficulty is that YouTube's feed endpoint
 * is unreliable: the same URL answers 200, 404 and 500 within seconds. The tool
 * used to read one response and report a correct URL as broken.
 */
describe('youtube rss feed generator', function () {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
        .'<feed xmlns:yt="http://www.youtube.com/xml/schemas/2015">'
        .'<entry><yt:videoId>abcdefghijk</yt:videoId><title>Hello &amp; Goodbye</title>'
        .'<published>2026-08-25T23:23:00+00:00</published></entry></feed>';

    $page = '<html><link rel="canonical" href="https://www.youtube.com/channel/UCBJycsmduvYEL83R_U4JriQ">'
        .'<meta property="og:title" content="Marques Brownlee"></html>';

    it('retries a flapping feed rather than calling a correct URL broken', function () use ($xml, $page) {
        Http::fake([
            'www.youtube.com/feeds/videos.xml?channel_id=*' => Http::sequence()
                ->push('nope', 404)
                ->push('nope', 500)
                ->push($xml, 200),
            'www.youtube.com/feeds/videos.xml?playlist_id=*' => Http::response('nope', 404),
            '*' => Http::response($page, 200),
        ]);

        $result = runRunner(new Runners\YouTubeRssFeedGeneratorRunner, ['source' => '@mkbhd']);

        expect($result->view)->toBe(ResultView::Table)
            ->and($result->meta['feed_reachable'])->toBeTrue()
            ->and($result->warnings)->toBeEmpty()
            ->and($result->summary)->toContain('1 entry');
    });

    it('shows the feed document in its own card when the feed reads', function () use ($xml, $page) {
        Http::fake([
            'www.youtube.com/feeds/videos.xml?channel_id=*' => Http::response($xml, 200),
            'www.youtube.com/feeds/videos.xml?playlist_id=*' => Http::response('nope', 404),
            '*' => Http::response($page, 200),
        ]);

        $result = runRunner(new Runners\YouTubeRssFeedGeneratorRunner, ['source' => '@mkbhd']);

        expect($result->meta['code']['text'])->toBe($xml)
            // The uploads feed answered 404 throughout, so it is not offered.
            ->and(array_column($result->data['rows'], 'label'))
            ->not->toContain('Uploads playlist feed')
            ->toContain('Channel RSS feed');
    });

    it('keeps the URL but drops the card and warns when the feed never reads', function () use ($page) {
        Http::fake([
            'www.youtube.com/feeds/videos.xml*' => Http::response('nope', 500),
            '*' => Http::response($page, 200),
        ]);

        $result = runRunner(new Runners\YouTubeRssFeedGeneratorRunner, ['source' => '@mkbhd']);

        $rows = collect($result->data['rows'])->keyBy('label');

        expect($result->meta)->not->toHaveKey('code')
            ->and($result->meta['feed_reachable'])->toBeFalse()
            ->and($result->warnings)->toHaveCount(1)
            ->and($rows['Channel RSS feed']['value'])
            ->toBe('https://www.youtube.com/feeds/videos.xml?channel_id=UCBJycsmduvYEL83R_U4JriQ')
            ->and($rows['Entries in the feed']['value'])->toBe('Could not read');
    });

    it('rejects a 200 carrying Google\'s HTML error page', function () use ($page) {
        Http::fake([
            'www.youtube.com/feeds/videos.xml*' => Http::response('<!DOCTYPE html><title>Error 404</title>', 200),
            '*' => Http::response($page, 200),
        ]);

        $result = runRunner(new Runners\YouTubeRssFeedGeneratorRunner, ['source' => '@mkbhd']);

        expect($result->meta['feed_reachable'])->toBeFalse();
    });

    it('trusts the playlist page when a playlist feed will not serve', function () {
        Http::fake([
            'www.youtube.com/feeds/videos.xml*' => Http::response('nope', 500),
            'www.youtube.com/playlist*' => Http::response('<html><title>Dark Psychology</title></html>', 200),
        ]);

        $result = runRunner(new Runners\YouTubeRssFeedGeneratorRunner, [
            'source' => 'https://www.youtube.com/playlist?list=PL4Z2VFKFXMshO_XHkkNKF8vMCllRgyoto',
        ]);

        expect($result->warnings)->toHaveCount(1)
            ->and($result->meta['code'] ?? null)->toBeNull();
    });

    it('reports not found when neither the playlist feed nor its page exists', function () {
        Http::fake(['*' => Http::response('nope', 404)]);

        expect(fn () => runRunner(new Runners\YouTubeRssFeedGeneratorRunner, [
            'source' => 'https://www.youtube.com/playlist?list=PL4Z2VFKFXMshO_XHkkNKF8vMCllRgyoto',
        ]))->toThrow(ToolExecutionException::class);
    });
});

/**
 * Google's suggestion endpoint answers with `[query, [suggestions...]]`, and the
 * order of that second array is the ranking the search box drops down.
 */
function suggestResponse(): Closure
{
    return function ($request) {
        parse_str((string) parse_url((string) $request->url(), PHP_URL_QUERY), $query);
        $q = $query['q'] ?? '';

        return Http::response(json_encode([$q, ["{$q} recipe", "{$q} for beginners"]]));
    };
}

it('groups YouTube suggestions the way the search box behaves', function () {
    Http::fake(['suggestqueries.google.com/*' => suggestResponse()]);

    $result = runRunner(app(Runners\YouTubeSearchSuggestRunner::class), [
        'keyword' => 'sourdough starter',
        'expansion' => 'everything',
    ]);

    $labels = array_column($result->data['groups'], 'label');

    expect($result->view)->toBe(ResultView::Table)
        ->and($labels)->toBe([
            'Direct suggestions',
            'Questions & long-tail',
            'Commercial intent',
            'Alphabet expansion (A–Z)',
        ])
        // 1 seed + 9 questions + 8 commercial + 26 letters, two suggestions each.
        ->and($result->meta['queries_made'])->toBe(44)
        ->and($result->meta['suggestions'])->toBe(88);

    $direct = $result->data['groups'][0];

    // YouTube's own ranking, kept: re-sorting would throw away the only popularity
    // signal the endpoint gives.
    expect($direct['rows'][0]['suggestion'])->toBe('sourdough starter recipe')
        ->and($direct['rows'][1]['suggestion'])->toBe('sourdough starter for beginners')
        ->and($direct['rows'][0]['rank'])->toBe(1)
        ->and($direct['rows'][0]['search'])->toContain('search_query=sourdough%20starter%20recipe');
});

it('runs a single group when one is asked for, and never repeats a suggestion', function () {
    Http::fake(['suggestqueries.google.com/*' => suggestResponse()]);

    $result = runRunner(app(Runners\YouTubeSearchSuggestRunner::class), [
        'keyword' => 'sourdough starter',
        'expansion' => 'questions',
        'position' => 'after',
    ]);

    $suggestions = array_column($result->data['rows'], 'suggestion');

    expect($result->data['groups'])->toHaveCount(1)
        ->and($suggestions)->toEqual(array_unique($suggestions))
        // "after" appends the modifier, the way typing another word into the box does.
        ->and($suggestions[0])->toBe('sourdough starter how recipe');
});
