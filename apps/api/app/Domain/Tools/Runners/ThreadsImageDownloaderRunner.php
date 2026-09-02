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
 * The image on a public Threads post, from the post's own link card.
 *
 * Threads is the most readable of Meta's three: a public post answers a signed-out
 * request with real tags rather than a wall, which is why a Threads downloader can
 * promise something an Instagram one cannot.
 *
 * The platform-specific part is the address. Threads moved from `threads.net` to
 * `threads.com`, both are in circulation, and links copied from the app still carry
 * a `?igshid=` — a per-share identifier that says who forwarded the post to you.
 * Normalising the host and dropping that parameter before the fetch is a small
 * thing that a general downloader taking any URL from any platform has no reason to
 * do, and it is the difference between a link that resolves and one that does not.
 *
 * Read from published card tags, no session, no private endpoint (docs/08).
 */
final class ThreadsImageDownloaderRunner implements Cacheable, ToolRunner, UsesProvider
{
    public static function key(): string
    {
        return 'threads.image-downloader';
    }

    public function providers(): array
    {
        return ['threads'];
    }

    public function cacheTtl(): int
    {
        // Threads serves the same signed CDN links Instagram does, so the same
        // short window applies: a cached link that has expired is worse than a
        // refetch.
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
                    'description' => 'A public Threads post. Both threads.com and the older threads.net '
                        .'links work.',
                    'minLength' => 8,
                    'maxLength' => 500,
                    'examples' => ['https://www.threads.com/@zuck/post/C2QBoRaRmvo'],
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $raw = trim($input->string('url'));
        $identity = SocialUrl::identify($raw);

        if ($identity['platform'] !== 'threads') {
            throw ToolExecutionException::invalidInput(
                'That is not a Threads link.',
                ['url' => 'Expected a link such as https://www.threads.com/@name/post/Cabc123'],
            );
        }

        if ($identity['kind'] === 'profile') {
            throw ToolExecutionException::invalidInput(
                'That is a profile, not a post. Open the post you want and copy the link from there.',
                ['url' => 'Expected a /@name/post/… link.'],
            );
        }

        $page = PageMeta::fetch(SocialUrl::stripTracking($identity['url'])['url']);
        $images = $page->isLoginWall() ? [] : MetaImages::postImages($page);

        if ($images === []) {
            throw ToolExecutionException::notFound($page->isLoginWall()
                ? 'that post — Threads answered with a sign-in page. A post from a private profile is not '
                .'public, and nothing signed-out can reach it'
                : 'an image on that post. Threads posts are text by default, so a post with no picture '
                .'genuinely has nothing to hand back');
        }

        return ToolResult::table(
            columns: MetaImages::columns(),
            rows: MetaImages::rows($images),
            summary: sprintf(
                '%d image%s on this post%s.',
                count($images),
                count($images) === 1 ? '' : 's',
                $page->title() !== null ? ' — “'.$this->clip($page->title(), 70).'”' : '',
            ),
        )->withMeta([
            'title' => $page->title(),
            'image_count' => count($images),
            'preview_url' => $images[0],
            'canonical' => $page->canonical(),
        ])->withWarnings(array_merge(MetaImages::warnings($images), [
            'A post with several images publishes the first one to its link card. When a post carries a '
            .'set and only one comes back, that is what happened.',
            'Threads runs on the same image infrastructure as Instagram, so the same rule applies: the '
            .'size segment in the URL is covered by the signature and cannot be rewritten to ask for a '
            .'bigger copy.',
        ]));
    }

    private function clip(string $value, int $length): string
    {
        return mb_strlen($value) > $length ? mb_substr($value, 0, $length).'…' : $value;
    }
}
