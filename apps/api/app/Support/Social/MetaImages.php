<?php

declare(strict_types=1);

namespace App\Support\Social;

/**
 * The rows three downloaders build from the same handful of facts.
 *
 * Instagram, Threads and Facebook are one company's infrastructure wearing three
 * brands. All three publish their post images to link cards through `fbcdn.net` or
 * `cdninstagram.com`, all three sign those URLs with an expiry, and all three
 * refuse a size rewrite because the signature covers the path. The table those
 * three facts produce is identical, so it is built once here.
 *
 * What is *not* here is any of the copy. Each platform's page says something
 * different about what to paste, what comes back and why it sometimes does not,
 * and that difference is the reason each has its own page rather than a shared one
 * — so the runners keep their own summaries, warnings and instructions.
 */
final class MetaImages
{
    /**
     * Path fragments that mark an image as Meta's own interface, not a post's.
     *
     * A sign-in page still publishes `og:image` — pointing at the platform's logo,
     * served from its static bundle. Handing that back as "the post image" would be
     * a wrong answer wearing the shape of a right one, so those are dropped before
     * anything else looks at the list, and a page left with nothing is treated as
     * the wall it is.
     *
     * @var list<string>
     */
    private const CHROME = ['/rsrc.php/', '/static.cdninstagram.com/images/', '/images/instagram/'];

    /**
     * The images a post actually published, with the platform's own furniture removed.
     *
     * @return list<string>
     */
    public static function postImages(PageMeta $page): array
    {
        return array_values(array_filter(
            $page->images(),
            static function (string $url): bool {
                foreach (self::CHROME as $fragment) {
                    if (str_contains($url, $fragment)) {
                        return false;
                    }
                }

                return true;
            },
        ));
    }

    /**
     * One table row per published image, with the signed link's remaining life.
     *
     * @param  list<string>  $images
     * @return list<array<string, string>>
     */
    public static function rows(array $images): array
    {
        $rows = [];

        foreach ($images as $index => $image) {
            $lifetime = CdnImage::lifetime($image);
            $size = CdnImage::dimensions($image);

            $rows[] = [
                'image' => count($images) === 1 ? 'Post image' : 'Image '.($index + 1),
                // The crop leads, because it is the one thing here that changes what
                // the visitor sees in the file rather than how long they have to fetch it.
                'source' => match (true) {
                    CdnImage::rendition($image)['cropped'] => 'Cropped for the link card',
                    CdnImage::isSigned($image) => 'Signed CDN link',
                    default => 'As published',
                },
                'size' => $size ?? 'Not stated',
                'expires' => $lifetime === null ? 'Does not expire' : 'Expires in '.$lifetime,
                'url' => $image,
            ];
        }

        return $rows;
    }

    /**
     * The column set those rows fill.
     *
     * The expiry column exists because it changes what the visitor should do next:
     * a link with two hours left is a file to save now, not a link to bookmark.
     *
     * @return list<array{key: string, label: string, align?: string, type?: string}>
     */
    public static function columns(): array
    {
        return [
            ['key' => 'image', 'label' => 'Image'],
            ['key' => 'source', 'label' => 'Version'],
            ['key' => 'size', 'label' => 'Pixels'],
            ['key' => 'expires', 'label' => 'Link life'],
            ['key' => 'url', 'label' => 'Download', 'align' => 'right', 'type' => 'download'],
        ];
    }

    /**
     * The warning every Meta-signed link earns, plus the ownership one.
     *
     * Phrased around what the visitor can do about it — save the file — rather than
     * around the mechanism, which is only interesting to the person who built this.
     *
     * @param  list<string>  $images
     * @return list<string>
     */
    public static function warnings(array $images): array
    {
        $warnings = [
            'These images belong to whoever posted them. Downloading one is not a licence to republish '
            .'it — use them for research, reference, moodboards or commentary.',
        ];

        foreach ($images as $image) {
            if (CdnImage::rendition($image)['cropped']) {
                $warnings[] = 'Meta crops the picture it publishes to a link card, so an image marked '
                    .'“Cropped for the link card” above is missing whatever fell outside that crop — '
                    .'usually the sides of a landscape photo or the top and bottom of a portrait one. '
                    .'The crop is baked into the signature, so there is no uncropped version to ask for '
                    .'here; the full frame is only served to a signed-in viewer in the app.';

                break;
            }
        }

        foreach ($images as $image) {
            if (CdnImage::isSigned($image)) {
                $warnings[] = 'Every link above is signed and expires. Save the file now; a link saved '
                    .'instead of a file answers 403 once the signature is stale, and there is no way to '
                    .'refresh one except fetching the post again.';

                break;
            }
        }

        return $warnings;
    }
}
