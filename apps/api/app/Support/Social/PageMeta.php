<?php

declare(strict_types=1);

namespace App\Support\Social;

use App\Support\Http\SafeHttpClient;

/**
 * The meta tags a public page publishes, read once and reused.
 *
 * Open Graph is the closest thing the social web has to a public API: every
 * platform that renders a link card reads the same four or five tags, and every
 * platform that publishes a post writes them so the card renders elsewhere. That
 * makes `og:image` on a post URL the supported, terms-respecting way to reach the
 * post's picture — no login, no private endpoint, no scraping of the rendered feed.
 *
 * {@see YouTubePage} does the same job for YouTube specifically, and predates this;
 * it stays where it is because it reads a lot more than meta tags. This is the
 * general case, for the tools that accept any URL from any platform.
 */
final readonly class PageMeta
{
    private function __construct(public string $url, public string $html) {}

    public static function fetch(string $url): self
    {
        $url = SocialUrl::normalise($url);

        return new self($url, SafeHttpClient::body(SafeHttpClient::get($url)));
    }

    /** For callers that already hold the HTML — the tests, and tools that fetch once and ask twice. */
    public static function of(string $url, string $html): self
    {
        return new self($url, $html);
    }

    public function og(string $property): ?string
    {
        return $this->attribute('property', "og:{$property}");
    }

    public function twitter(string $name): ?string
    {
        return $this->attribute('name', "twitter:{$name}");
    }

    public function named(string $name): ?string
    {
        return $this->attribute('name', $name);
    }

    /**
     * The best available title: Open Graph, then X's tag, then the `<title>`.
     *
     * The order is the order the platforms themselves resolve it in, so a preview
     * built on this shows what a feed would show rather than what we would prefer.
     */
    public function title(): ?string
    {
        $og = $this->og('title') ?? $this->twitter('title');

        if ($og !== null) {
            return $og;
        }

        return preg_match('/<title[^>]*>(.*?)<\/title>/is', $this->html, $match) === 1
            ? self::decode(trim($match[1]))
            : null;
    }

    public function description(): ?string
    {
        return $this->og('description') ?? $this->twitter('description') ?? $this->named('description');
    }

    public function image(): ?string
    {
        $image = $this->og('image')
            ?? $this->og('image:secure_url')
            ?? $this->twitter('image')
            ?? $this->twitter('image:src');

        return $image === null ? null : $this->absolute($image);
    }

    public function siteName(): ?string
    {
        return $this->og('site_name');
    }

    public function canonical(): ?string
    {
        return preg_match('/<link[^>]+rel=["\']canonical["\'][^>]*href=["\']([^"\']+)["\']/i', $this->html, $match) === 1
            ? $this->absolute($match[1])
            : null;
    }

    /**
     * Every `og:image` on the page, in document order.
     *
     * A carousel publishes one tag per slide, so taking only the first would hand
     * back one photo out of ten — which is exactly the case an image downloader
     * exists for.
     *
     * @return list<string>
     */
    public function images(): array
    {
        $found = [];

        foreach (['og:image', 'og:image:secure_url', 'twitter:image'] as $tag) {
            $attribute = str_starts_with($tag, 'og:') ? 'property' : 'name';
            $pattern = '/<meta[^>]+'.$attribute.'=["\']'.preg_quote($tag, '/')
                .'["\'][^>]*content=["\']([^"\']+)["\']/i';

            if (preg_match_all($pattern, $this->html, $matches) > 0) {
                foreach ($matches[1] as $value) {
                    $found[] = $this->absolute(self::decode($value));
                }
            }
        }

        return array_values(array_unique(array_filter($found, fn (string $url) => $url !== '')));
    }

    /**
     * Whether the page we were handed is a login or consent wall rather than content.
     *
     * Several platforms answer an unauthenticated request with a 200 and a sign-in
     * page. Reporting "no image found" for that is the wrong diagnosis: the tool
     * worked, the platform declined, and only one of those two the visitor can do
     * anything about.
     */
    public function isLoginWall(): bool
    {
        $title = mb_strtolower($this->title() ?? '');

        foreach (['log in', 'login', 'sign up', 'sign in', 'restricted'] as $phrase) {
            if (str_contains($title, $phrase)) {
                return true;
            }
        }

        // A real post page always carries og:title; a wall usually carries only the
        // platform's own generic tags, or none at all.
        return $this->og('title') === null && $this->og('image') === null;
    }

    /** Resolve a relative `content` value against the page it was found on. */
    private function absolute(string $value): string
    {
        $value = trim($value);

        if ($value === '' || str_contains($value, '://')) {
            return $value;
        }

        $parts = parse_url($this->url);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

        if (str_starts_with($value, '//')) {
            return ($parts['scheme'] ?? 'https').':'.$value;
        }

        return str_starts_with($value, '/')
            ? $origin.$value
            : $origin.rtrim(dirname($parts['path'] ?? '/'), '/').'/'.$value;
    }

    private function attribute(string $attribute, string $value): ?string
    {
        // Attribute order varies between frameworks, so both orders are matched.
        $patterns = [
            '/<meta[^>]+'.$attribute.'=["\']'.preg_quote($value, '/').'["\'][^>]*content=["\']([^"\']*)["\']/i',
            '/<meta[^>]+content=["\']([^"\']*)["\'][^>]*'.$attribute.'=["\']'.preg_quote($value, '/').'["\']/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $this->html, $match) === 1 && trim($match[1]) !== '') {
                return self::decode(trim($match[1]));
            }
        }

        return null;
    }

    private static function decode(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5);
    }
}
