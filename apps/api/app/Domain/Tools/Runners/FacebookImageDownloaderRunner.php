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
use App\Support\Http\SafeHttpClient;
use App\Support\Social\CdnImage;
use App\Support\Social\MetaImages;
use App\Support\Social\PageMeta;
use App\Support\Social\SocialUrl;

/**
 * The picture behind a public Facebook post, or a Page's profile picture at the
 * largest size Facebook stores.
 *
 * Two different links, two different routes, and the split is what makes this worth
 * a page of its own:
 *
 * - A **post, photo or video** link is read from the tags Facebook publishes for
 *   link cards, the same way every other downloader here works. Facebook signs
 *   those URLs and expires them, so the remaining life of each link is read out of
 *   the link and shown next to it.
 * - A **Page** link goes somewhere better. Facebook's Graph API serves any Page's
 *   profile picture without a token, at a size you ask for, and answers with the
 *   dimensions it actually has — so the ladder below is measured rather than
 *   guessed, and the largest row is the largest copy Facebook holds rather than the
 *   200-pixel one the API hands out by default.
 *
 * Neither route signs in and neither reads a private endpoint (docs/08). Where
 * Facebook declines — which for post links it does often, answering a signed-out
 * request with a sign-in page — that is reported as the platform declining, not as
 * an empty result.
 */
final class FacebookImageDownloaderRunner implements Cacheable, ToolRunner, UsesProvider
{
    /** The unauthenticated picture endpoint, and the sizes worth asking it for. */
    private const GRAPH = 'https://graph.facebook.com/';

    /**
     * Named sizes Facebook understands, plus the explicit request for the biggest.
     *
     * The named ones are small, fixed renditions. The last row asks for a size
     * larger than any Page picture is stored at, which is how you get Facebook to
     * answer with the largest copy it has rather than a rendition of it.
     *
     * @var array<string, string>
     */
    private const PICTURE_SIZES = [
        'small' => 'Comment and search-result avatar',
        'normal' => 'Feed avatar',
        'large' => 'The biggest named size — what the API gives you by default',
        'max' => 'Nothing — this is the copy Facebook stores, asked for by size',
    ];

    public static function key(): string
    {
        return 'facebook.image-downloader';
    }

    public function providers(): array
    {
        return ['facebook'];
    }

    public function cacheTtl(): int
    {
        // Every URL either route returns is signed and expires within hours.
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
                    'title' => 'Post or Page URL',
                    'description' => 'A public post, photo or video link — or a Page link, for its profile '
                        .'picture at full size.',
                    'minLength' => 8,
                    'maxLength' => 500,
                    'examples' => ['https://www.facebook.com/NASA'],
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $raw = trim($input->string('url'));
        $identity = SocialUrl::identify($raw);

        if ($identity['platform'] !== 'facebook') {
            throw ToolExecutionException::invalidInput(
                'That is not a Facebook link.',
                ['url' => 'Expected a link such as https://www.facebook.com/NASA'],
            );
        }

        $page = $this->pageName($identity);

        return $page !== null ? $this->profilePicture($page) : $this->postImages($identity);
    }

    /**
     * A Page's own picture, measured at every size the endpoint will serve.
     *
     * The four requests go out together: they are independent, they are small, and
     * running them in series would spend most of the tool's budget waiting. A size
     * that fails is left out rather than reported — the endpoint answering for
     * three of four is still a useful answer.
     */
    private function profilePicture(string $page): ToolResult
    {
        $responses = SafeHttpClient::attemptPool(array_map(
            fn (string $size) => self::GRAPH.rawurlencode($page).'/picture?redirect=false&'
                .($size === 'max' ? 'width=2048&height=2048' : 'type='.$size),
            array_combine(array_keys(self::PICTURE_SIZES), array_keys(self::PICTURE_SIZES)),
        ));

        $rows = [];
        $largest = null;

        foreach (self::PICTURE_SIZES as $size => $use) {
            $data = $responses[$size]?->json('data');

            if (! is_array($data) || ! is_string($data['url'] ?? null)) {
                continue;
            }

            // A Page with no picture is served a silhouette. Handing that back as
            // "the profile picture" would be a wrong answer dressed as a right one.
            if (($data['is_silhouette'] ?? false) === true) {
                throw ToolExecutionException::notFound(
                    'a profile picture on that Page. Facebook is serving the default silhouette, which '
                    .'means the Page has not set one',
                );
            }

            $lifetime = CdnImage::lifetime($data['url']);
            $largest = $data['url'];

            $rows[] = [
                'image' => $size === 'max' ? 'Original upload' : 'Resized',
                'source' => is_int($data['width'] ?? null) && is_int($data['height'] ?? null)
                    ? $data['width'].' × '.$data['height']
                    : 'Unknown',
                'expires' => $lifetime === null ? 'Does not expire' : 'Expires in '.$lifetime,
                'url' => $data['url'],
            ];
        }

        if ($rows === []) {
            throw ToolExecutionException::notFound(
                'a picture for that Page. Facebook serves this one without a login for Pages, but not for '
                .'personal profiles — a personal profile\'s picture is only visible to people it is shared '
                .'with, and this tool does not sign in',
            );
        }

        return ToolResult::table(
            columns: [
                ['key' => 'image', 'label' => 'Version'],
                ['key' => 'source', 'label' => 'Dimensions'],
                ['key' => 'expires', 'label' => 'Link life'],
                ['key' => 'url', 'label' => 'Download', 'align' => 'right', 'type' => 'download'],
            ],
            rows: $rows,
            summary: 'Profile picture for “'.$page.'” at every size Facebook will serve. The last row is '
                .'the copy Facebook stores; the rest are renditions of it.',
        )->withMeta([
            'page' => $page,
            'mode' => 'profile-picture',
            'preview_url' => $largest,
        ])->withWarnings([
            'A Page\'s profile picture is its logo, and a logo is usually a trademark. Downloading one is '
            .'fine for a mock-up, a slide or a comparison; putting it on something that implies the brand '
            .'endorsed you is not.',
            'These links are signed and expire. Save the file rather than the link.',
        ]);
    }

    /**
     * Whatever a post, photo or video link publishes to its link card.
     *
     * @param  array{platform: ?string, label: ?string, kind: ?string, host: ?string, path: string, url: string}  $identity
     */
    private function postImages(array $identity): ToolResult
    {
        $page = PageMeta::fetch(SocialUrl::stripTracking($identity['url'])['url']);
        $images = $page->isLoginWall() ? [] : MetaImages::postImages($page);

        if ($images === []) {
            throw ToolExecutionException::notFound($page->isLoginWall()
                ? 'that post — Facebook answered with a sign-in page rather than the post. Facebook does '
                .'this even for posts that are public when you are signed in; check the post opens in a '
                .'private browser window, and if it does not, nothing signed-out can reach it'
                : 'an image on that post. A text-only post has none, and a video post publishes a poster '
                .'frame at best');
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
            'mode' => 'post',
            'title' => $page->title(),
            'image_count' => count($images),
            'preview_url' => $images[0],
        ])->withWarnings(array_merge(MetaImages::warnings($images), [
            'A post with an album publishes one picture to its link card. When a post you know carries '
            .'twelve photos comes back with one, that is the card, not a failed read — open the photo '
            .'itself and paste that link instead.',
        ]));
    }

    /**
     * The Page name in a link, when the link is to a Page rather than to a post.
     *
     * The distinction is structural: `/NASA` is a Page, `/NASA/posts/123` is a post
     * on it, and only the first is a question the picture endpoint can answer. The
     * reserved first segments are the ones Facebook uses for its own routes, which
     * look like Page names and are not.
     *
     * @param  array{platform: ?string, label: ?string, kind: ?string, host: ?string, path: string, url: string}  $identity
     */
    private function pageName(array $identity): ?string
    {
        if ($identity['kind'] !== 'profile'
            || preg_match('#^/([\w.-]+)/?$#', $identity['path'], $match) !== 1) {
            return null;
        }

        $reserved = ['watch', 'reel', 'share', 'groups', 'events', 'marketplace', 'photo', 'story.php',
            'permalink.php', 'profile.php', 'people', 'pages', 'login', 'help'];

        return in_array(mb_strtolower($match[1]), $reserved, true) ? null : $match[1];
    }

    private function clip(string $value, int $length): string
    {
        return mb_strlen($value) > $length ? mb_substr($value, 0, $length).'…' : $value;
    }
}
