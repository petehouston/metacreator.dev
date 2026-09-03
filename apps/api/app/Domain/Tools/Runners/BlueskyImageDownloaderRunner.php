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
use App\Support\Social\SocialUrl;

/**
 * Every image on a Bluesky post, at full size, with the alt text the author wrote.
 *
 * Bluesky is the one platform in this group that does not have to be read through
 * a link card. AT Protocol keeps an unauthenticated read API open on purpose — the
 * network is designed to be readable by other clients — so a post's images can be
 * asked for directly instead of inferred from `og:image`, which on Bluesky names
 * one image even when the post carries four.
 *
 * Three things follow from using the real API, and all three are the reason this is
 * a Bluesky page rather than a row in the general downloader:
 *
 * - **Every image**, not the first. Up to four per post, each listed separately.
 * - **The alt text**, which is a caption the author wrote and the single most
 *   useful thing to carry across when you are quoting a post somewhere else.
 * - **The blob**, the file exactly as uploaded. `cdn.bsky.app` serves re-encoded
 *   renditions; the bytes themselves live on the author's own server, and the
 *   protocol publishes where that is.
 *
 * Nothing here is authenticated and nothing here is scraped: these are the public
 * read endpoints, called the way any Bluesky client calls them (docs/08).
 */
final class BlueskyImageDownloaderRunner implements Cacheable, ToolRunner, UsesProvider
{
    /** Bluesky's own unauthenticated read host. */
    private const APPVIEW = 'https://public.api.bsky.app/xrpc/';

    /** Where a `did:plc:` identity's document is published. */
    private const PLC_DIRECTORY = 'https://plc.directory/';

    public static function key(): string
    {
        return 'bluesky.image-downloader';
    }

    public function providers(): array
    {
        return ['bluesky'];
    }

    public function cacheTtl(): int
    {
        return 21600;
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
                    'description' => 'A bsky.app post link — the address in the bar when you open a post.',
                    'minLength' => 8,
                    'maxLength' => 500,
                    'examples' => ['https://bsky.app/profile/capecodfairytales.bsky.social/post/3mukkmshahc2n'],
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        [$actor, $rkey] = $this->parse(trim($input->string('url')));

        $did = str_starts_with($actor, 'did:') ? $actor : $this->resolveHandle($actor);
        $post = $this->fetchPost($did, $rkey);
        $images = $this->images($post);

        if ($images === []) {
            throw ToolExecutionException::notFound(
                'any images on that post. A text-only post has none, and a post that quotes a link shows '
                .'that link\'s preview picture rather than an image of its own',
            );
        }

        $pds = $this->pdsFor($did);
        $rows = [];

        foreach ($images as $index => $image) {
            $label = count($images) === 1 ? 'Image' : 'Image '.($index + 1);

            $rows[] = [
                'image' => $label,
                'version' => 'Full size',
                'dimensions' => $this->dimensions($image),
                'alt' => $image['alt'] === '' ? '— none written —' : $image['alt'],
                'url' => $image['fullsize'],
            ];

            $blob = $this->blobUrl($pds, $did, $image['fullsize']);

            if ($blob !== null) {
                $rows[] = [
                    'image' => $label,
                    'version' => 'Original upload',
                    'dimensions' => 'As uploaded',
                    'alt' => $image['alt'] === '' ? '— none written —' : $image['alt'],
                    'url' => $blob,
                ];
            }
        }

        return ToolResult::table(
            columns: [
                ['key' => 'image', 'label' => 'Image'],
                ['key' => 'version', 'label' => 'Version'],
                ['key' => 'dimensions', 'label' => 'Dimensions'],
                ['key' => 'alt', 'label' => 'Alt text', 'wrap' => true, 'copyable' => true],
                ['key' => 'url', 'label' => 'Download', 'align' => 'right', 'type' => 'download'],
            ],
            rows: $rows,
            summary: sprintf(
                '%d image%s on this post. The original upload row is the file itself, straight from the '
                .'author\'s server; the full-size row is Bluesky\'s re-encoded copy.',
                count($images),
                count($images) === 1 ? '' : 's',
            ),
        )->withMeta([
            'did' => $did,
            'rkey' => $rkey,
            'pds' => $pds,
            'image_count' => count($images),
            'preview_url' => $images[0]['fullsize'],
            'alt_text' => array_column($images, 'alt'),
        ])->withWarnings([
            'These images belong to whoever posted them. Downloading one is not a licence to republish '
            .'it — and on Bluesky the alt text is the author\'s writing too, so carry it across rather '
            .'than dropping it.',
            'Only public posts. A post from an account that has asked to be excluded from logged-out '
            .'views is not served by the public read API, and this tool never signs in to get around that.',
        ]);
    }

    /**
     * The actor and record key from a bsky.app post URL.
     *
     * `/profile/<handle-or-did>/post/<rkey>` is the only shape that identifies a
     * post. A profile link has no rkey, and telling somebody that plainly is more
     * use than a generic "not found" after two wasted requests.
     *
     * @return array{0: string, 1: string}
     */
    private function parse(string $input): array
    {
        $identity = SocialUrl::identify($input);

        if ($identity['platform'] !== 'bluesky') {
            throw ToolExecutionException::invalidInput(
                'That is not a Bluesky link.',
                ['url' => 'Expected a link such as https://bsky.app/profile/name.bsky.social/post/abc123'],
            );
        }

        if (preg_match('#^/profile/([^/]+)/post/([\w.:-]+)/?$#', $identity['path'], $match) !== 1) {
            throw ToolExecutionException::invalidInput(
                'That is a Bluesky link, but not to a single post. Open the post itself and copy the '
                .'address from there.',
                ['url' => 'Expected /profile/…/post/… in the link.'],
            );
        }

        return [rawurldecode($match[1]), $match[2]];
    }

    /** A handle to the identifier it owns; handles change hands, DIDs do not. */
    private function resolveHandle(string $handle): string
    {
        $response = SafeHttpClient::attempt(
            self::APPVIEW.'com.atproto.identity.resolveHandle?handle='.rawurlencode(ltrim($handle, '@')),
        );

        $did = $response?->json('did');

        if (! is_string($did) || $did === '') {
            throw ToolExecutionException::notFound(
                'that account. Bluesky does not know the handle in that link — check it, or paste a link '
                .'copied from the post rather than typed by hand',
            );
        }

        return $did;
    }

    /**
     * One post, through the read endpoint every Bluesky client uses.
     *
     * `depth` and `parentHeight` are zeroed because the replies and the thread
     * above are a different tool's business, and asking for them would make the
     * response many times larger for nothing.
     *
     * @return array<string, mixed>
     */
    private function fetchPost(string $did, string $rkey): array
    {
        $uri = 'at://'.$did.'/app.bsky.feed.post/'.$rkey;

        $response = SafeHttpClient::attempt(
            self::APPVIEW.'app.bsky.feed.getPostThread?depth=0&parentHeight=0&uri='.rawurlencode($uri),
        );

        if ($response === null) {
            throw ToolExecutionException::upstreamFailed('Bluesky');
        }

        $post = $response->json('thread.post');

        if (! is_array($post)) {
            throw ToolExecutionException::notFound(
                'that post. It may have been deleted, or the account may have been deactivated — either '
                .'way the network no longer serves it',
            );
        }

        return $post;
    }

    /**
     * The images a post view carries, whatever it wraps them in.
     *
     * A plain image post puts them at `embed.images`. A post that also quotes
     * another post wraps the same structure a level down in `media`, and the images
     * on the *quoted* post are deliberately not followed: they belong to a
     * different post with a different author and a different link.
     *
     * @param  array<string, mixed>  $post
     * @return list<array{fullsize: string, alt: string, width: ?int, height: ?int}>
     */
    private function images(array $post): array
    {
        $embed = $post['embed'] ?? [];
        $candidates = $embed['images'] ?? ($embed['media']['images'] ?? null);

        if (! is_array($candidates)) {
            return [];
        }

        $images = [];

        foreach ($candidates as $image) {
            if (! is_array($image) || ! isset($image['fullsize']) || ! is_string($image['fullsize'])) {
                continue;
            }

            $width = $image['aspectRatio']['width'] ?? null;
            $height = $image['aspectRatio']['height'] ?? null;

            $images[] = [
                'fullsize' => $image['fullsize'],
                'alt' => is_string($image['alt'] ?? null) ? trim($image['alt']) : '',
                'width' => is_int($width) ? $width : null,
                'height' => is_int($height) ? $height : null,
            ];
        }

        return $images;
    }

    /**
     * Where this identity's data actually lives.
     *
     * Bluesky is not one server. An account's records and blobs sit on its personal
     * data server, which the identity document names, and which is frequently one
     * of Bluesky's own hosts but is under no obligation to be. Getting this wrong
     * would hand back a link that 404s, so a lookup that fails returns null and the
     * original-upload row is simply not offered.
     */
    private function pdsFor(string $did): ?string
    {
        $document = match (true) {
            str_starts_with($did, 'did:plc:') => SafeHttpClient::attempt(self::PLC_DIRECTORY.$did)?->json(),
            str_starts_with($did, 'did:web:') => SafeHttpClient::attempt(
                'https://'.substr($did, 8).'/.well-known/did.json',
            )?->json(),
            default => null,
        };

        foreach (is_array($document) ? ($document['service'] ?? []) : [] as $service) {
            if (is_array($service)
                && ($service['type'] ?? null) === 'AtprotoPersonalDataServer'
                && is_string($service['serviceEndpoint'] ?? null)) {
                return rtrim($service['serviceEndpoint'], '/');
            }
        }

        return null;
    }

    /**
     * The uploaded file, addressed by its content hash.
     *
     * `cdn.bsky.app` names the blob in the path it serves the rendition from —
     * `/img/feed_fullsize/plain/<did>/<cid>@jpeg` — so the identifier needed to ask
     * the author's server for the original is already in hand and costs no request.
     */
    private function blobUrl(?string $pds, string $did, string $fullsize): ?string
    {
        // The `@jpeg` suffix naming the rendition format is optional — the API
        // returns the URL with it in some responses and without it in others — so
        // the hash is matched with or without one.
        if ($pds === null || preg_match('#/([a-z0-9]{40,})(?:@[a-z0-9]+)?$#i', $fullsize, $match) !== 1) {
            return null;
        }

        return $pds.'/xrpc/com.atproto.sync.getBlob?did='.rawurlencode($did).'&cid='.$match[1];
    }

    /** @param  array{fullsize: string, alt: string, width: ?int, height: ?int}  $image */
    private function dimensions(array $image): string
    {
        return $image['width'] !== null && $image['height'] !== null
            ? $image['width'].' × '.$image['height']
            : 'Unknown';
    }
}
