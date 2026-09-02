<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Contracts\UsesProvider;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Domain\Tools\Exceptions\ToolExecutionException;
use App\Support\Social\MetaImages;
use App\Support\Social\PageMeta;
use App\Support\Social\SocialUrl;

/**
 * The photo behind a public Instagram post, at the size Instagram publishes it.
 *
 * Right-clicking a photo in the feed saves the copy the feed is rendering, which is
 * sized for the column it is sitting in. The copy Instagram publishes to link cards
 * is the larger one, and it is the one this hands back.
 *
 * Two things about Instagram specifically are worth a page of their own, and both
 * are things the general downloader has no business knowing:
 *
 * - **The link expires.** Instagram signs every image URL, and the expiry is in the
 *   URL. So this reads it and says how long is left, because "save the file, do not
 *   bookmark the link" is advice that only means something with a number attached.
 * - **The size cannot be rewritten.** The signature covers the path, so the
 *   `s1080x1080` trick that circulates for Instagram URLs answers 403 on every
 *   current link. Saying so is more useful than a row that 403s.
 *
 * Read from the tags Instagram publishes for other sites, with no session and no
 * private endpoint (docs/08) — which is also where it stops: Instagram answers a
 * signed-out request with a sign-in page more often than it used to, and that is
 * reported as what it is rather than as an empty result.
 */
final class InstagramImageDownloaderRunner implements Cacheable, ToolRunner, UsesProvider
{
    public static function key(): string
    {
        return 'instagram.image-downloader';
    }

    public function providers(): array
    {
        return ['instagram'];
    }

    public function cacheTtl(): int
    {
        // Short, and shorter than the tool would otherwise want: these URLs are
        // signed and expire, and handing somebody a cached link that 403s is worse
        // than paying for the refetch.
        return 900;
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
                    'title' => 'Post URL',
                    'description' => 'A public Instagram post or reel — the /p/… or /reel/… link from the '
                        .'share sheet.',
                    'minLength' => 8,
                    'maxLength' => 500,
                    'examples' => ['https://www.instagram.com/p/Cxyz1234567/'],
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $raw = trim($input->string('url'));
        $identity = SocialUrl::identify($raw);

        if ($identity['platform'] !== 'instagram') {
            throw ToolExecutionException::invalidInput(
                'That is not an Instagram link.',
                ['url' => 'Expected a link such as https://www.instagram.com/p/Cxyz1234567/'],
            );
        }

        if ($identity['kind'] === 'profile') {
            throw ToolExecutionException::invalidInput(
                'That is a profile, not a post. Open the photo you want and use the link from its share '
                .'sheet.',
                ['url' => 'Expected a /p/… or /reel/… link.'],
            );
        }

        if ($identity['kind'] === 'story') {
            throw ToolExecutionException::invalidInput(
                'Stories are not public in the way a post is — they are served to signed-in viewers and '
                .'they disappear. There is nothing here to read without an account, so this tool does not '
                .'pretend otherwise.',
                ['url' => 'Expected a /p/… or /reel/… link.'],
            );
        }

        // Tracking parameters on a shared Instagram link are per-share identifiers,
        // so the fetch goes out without them.
        $page = PageMeta::fetch(SocialUrl::stripTracking($identity['url'])['url']);
        $images = $page->isLoginWall() ? [] : MetaImages::postImages($page);

        if ($images === []) {
            throw ToolExecutionException::notFound($page->isLoginWall()
                ? 'that post — Instagram answered with a sign-in page rather than the post. That happens '
                .'on private accounts, and increasingly on public ones too. Check the post opens in a '
                .'private browser window; if it does not, nothing signed-out can reach it'
                : 'an image on that post. A reel publishes its cover frame at best, and a post that has '
                .'been deleted publishes nothing at all');
        }

        return ToolResult::table(
            columns: MetaImages::columns(),
            rows: MetaImages::rows($images),
            summary: sprintf(
                '%d image%s published by this post%s.',
                count($images),
                count($images) === 1 ? '' : 's',
                $page->title() !== null ? ' — “'.$this->clip($page->title(), 70).'”' : '',
            ),
        )->withMeta([
            'kind' => $identity['kind'],
            'title' => $page->title(),
            'image_count' => count($images),
            'preview_url' => $images[0],
        ])->withWarnings(array_merge(MetaImages::warnings($images), $this->notes($identity, count($images))));
    }

    /**
     * The two things people ask about afterwards, answered before they ask.
     *
     * @param  array{platform: ?string, label: ?string, kind: ?string, host: ?string, path: string, url: string}  $identity
     * @return list<string>
     */
    private function notes(array $identity, int $count): array
    {
        $notes = [];

        if ($count === 1) {
            $notes[] = 'Instagram publishes only the first slide of a carousel to a link card when it '
                .'answers a signed-out request, so a ten-slide post can come back as one image. That is '
                .'Instagram\'s limit, not a failed read.';
        }

        if ($identity['kind'] === 'reel') {
            $notes[] = 'That link is a reel, so what comes back is its cover frame. This tool downloads '
                .'images; it does not download video.';
        }

        $notes[] = 'The size segment in an Instagram image URL cannot be rewritten. The signature covers '
            .'the whole path, so an edited link answers 403 — the version above is the largest one '
            .'Instagram publishes.';

        return $notes;
    }

    private function clip(string $value, int $length): string
    {
        return mb_strlen($value) > $length ? mb_substr($value, 0, $length).'…' : $value;
    }
}
