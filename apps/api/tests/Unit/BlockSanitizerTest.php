<?php

declare(strict_types=1);

use App\Domain\Blog\Blocks\BlockSanitizer;
use App\Domain\Blog\Blocks\BlockTextExtractor;

/**
 * The sanitiser is the boundary between "an editor pasted something" and "a browser
 * executed something". Every case here is an attack that has worked on a real CMS.
 */
beforeEach(function (): void {
    $this->sanitize = fn (array $blocks): array => app(BlockSanitizer::class)
        ->sanitize(['blocks' => $blocks])['blocks'];
});

it('strips scripts and event handlers from rich text', function (string $payload) {
    $blocks = ($this->sanitize)([['type' => 'paragraph', 'data' => ['html' => $payload]]]);

    expect($blocks[0]['data']['html'])
        ->not->toContain('script')
        ->not->toContain('onerror')
        ->not->toContain('onclick')
        ->not->toContain('javascript:');
})->with([
    'script tag' => ['<script>alert(1)</script>'],
    'img onerror' => ['<img src=x onerror=alert(1)>'],
    'javascript href' => ['<a href="javascript:alert(1)">click</a>'],
    'svg onload' => ['<svg onload=alert(1)></svg>'],
    'nested case trick' => ['<scr<script>ipt>alert(1)</script>'],
]);

it('keeps the formatting marks an editor actually uses', function () {
    $blocks = ($this->sanitize)([[
        'type' => 'paragraph',
        'data' => ['html' => '<strong>b</strong> <em>i</em> <mark>hi</mark> <code>c</code> <a href="https://example.com">link</a>'],
    ]]);

    expect($blocks[0]['data']['html'])
        ->toContain('<strong>')
        ->toContain('<em>')
        // `mark` is HTML5 and needs a custom HTMLPurifier element; without it the
        // highlight mark is silently dropped from every post.
        ->toContain('<mark>')
        ->toContain('<code>')
        ->toContain('href="https://example.com"');
});

it('allows an iframe only from a provider we named', function (string $src, bool $kept) {
    $blocks = ($this->sanitize)([[
        'type' => 'html',
        'data' => ['html' => '<iframe src="'.$src.'"></iframe>'],
    ]]);

    expect(str_contains($blocks[0]['data']['html'], 'iframe'))->toBe($kept);
})->with([
    'youtube' => ['https://www.youtube.com/embed/abc', true],
    'vimeo' => ['https://player.vimeo.com/video/123', true],
    'arbitrary host' => ['https://evil.example/x', false],
    'data uri' => ['data:text/html;base64,PHNjcmlwdD4=', false],
]);

it('blocks a javascript url on a button', function () {
    $blocks = ($this->sanitize)([[
        'type' => 'button',
        'data' => ['label' => 'Go', 'href' => 'javascript:alert(1)'],
    ]]);

    expect($blocks[0]['data']['href'])->toBe('');
});

it('clamps a heading to the H2–H4 range', function (int $given, int $expected) {
    $blocks = ($this->sanitize)([['type' => 'heading', 'data' => ['level' => $given, 'text' => 'T']]]);

    // H1 is the post title. A second H1 in the body is a real SEO defect.
    expect($blocks[0]['data']['level'])->toBe($expected);
})->with([
    'h1 promoted' => [1, 2],
    'h3 kept' => [3, 3],
    'h9 clamped' => [9, 4],
]);

it('preserves an unknown block type rather than dropping it', function () {
    $blocks = ($this->sanitize)([['type' => 'fromTheFuture', 'data' => ['keep' => 'me']]]);

    // A rollback must never destroy content written by a newer deploy.
    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]['type'])->toBe('fromTheFuture')
        ->and($blocks[0]['data']['keep'])->toBe('me');
});

it('gives every block a stable id', function () {
    $blocks = ($this->sanitize)([
        ['type' => 'paragraph', 'data' => ['html' => 'a'], 'id' => 'b_existing'],
        ['type' => 'paragraph', 'data' => ['html' => 'b']],
    ]);

    expect($blocks[0]['id'])->toBe('b_existing')
        ->and($blocks[1]['id'])->toStartWith('b_');
});

it('leaves code untouched', function () {
    $code = "if (a < b && c > d) {\n  echo '<script>';\n}";

    $blocks = ($this->sanitize)([['type' => 'code', 'data' => ['language' => 'php', 'code' => $code]]]);

    // Code is rendered as a text node, so escaping is the renderer's job. Running
    // it through an HTML sanitiser here would silently corrupt it.
    expect($blocks[0]['data']['code'])->toBe($code);
});

it('counts words and reading time from prose only', function () {
    $extractor = app(BlockTextExtractor::class);

    $document = ['blocks' => [
        ['type' => 'paragraph', 'data' => ['html' => '<p>one two three four five</p>']],
        ['type' => 'heading', 'data' => ['level' => 2, 'text' => 'six seven']],
        // Neither of these should inflate the count.
        ['type' => 'code', 'data' => ['code' => 'lots of code tokens here indeed']],
        ['type' => 'embed', 'data' => ['url' => 'https://youtube.com/watch?v=x']],
    ]];

    $text = $extractor->text($document);

    expect($extractor->wordCount($text))->toBe(7)
        ->and($text)->toBe('one two three four five six seven')
        ->and($extractor->readingMinutes(7))->toBe(1)
        ->and($extractor->readingMinutes(1100))->toBe(5);
});
