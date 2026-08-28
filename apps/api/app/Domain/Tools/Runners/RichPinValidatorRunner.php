<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Support\Http\SafeHttpClient;

/**
 * Whether a page carries the markup a Rich Pin needs — before you apply for one.
 *
 * Pinterest's own validator only tells you pass or fail. This reads the page the
 * same way and reports which specific tags are present, missing or empty, so a
 * rejection turns into a list of things to add rather than a mystery.
 *
 * Only the two types most creators need are checked: **article** (blog posts, the
 * common case) and **product** (shops). Recipe Pins need a full schema.org Recipe
 * graph, which is a different job.
 */
final class RichPinValidatorRunner implements Cacheable, ToolRunner
{
    /**
     * Required and optional tags per Pin type, with the weight each carries.
     *
     * @var array<string, array{label: string, required: list<array{tag: string, why: string}>, optional: list<array{tag: string, why: string}>}>
     */
    private const TYPES = [
        'article' => [
            'label' => 'Article Rich Pin',
            'required' => [
                ['tag' => 'og:type', 'why' => 'Must be "article", or Pinterest treats the page as a plain link.'],
                ['tag' => 'og:title', 'why' => 'Becomes the bold headline on the Pin.'],
                ['tag' => 'og:description', 'why' => 'The excerpt under the headline, and indexed for search.'],
                ['tag' => 'og:url', 'why' => 'The canonical destination Pinterest attributes saves to.'],
            ],
            'optional' => [
                ['tag' => 'article:published_time', 'why' => 'Lets Pinterest show and sort by recency.'],
                ['tag' => 'article:author', 'why' => 'Adds the byline to the Pin.'],
                ['tag' => 'og:site_name', 'why' => 'Shows your site name instead of the bare domain.'],
                ['tag' => 'og:image', 'why' => 'Not required for the Pin, but it is what every other network shows.'],
            ],
        ],
        'product' => [
            'label' => 'Product Rich Pin',
            'required' => [
                ['tag' => 'og:type', 'why' => 'Must be "product".'],
                ['tag' => 'og:title', 'why' => 'The product name on the Pin.'],
                ['tag' => 'og:description', 'why' => 'The product blurb.'],
                ['tag' => 'product:price:amount', 'why' => 'No price, no product Pin — this is the tag that fails most often.'],
                ['tag' => 'product:price:currency', 'why' => 'A price without a currency is rejected.'],
                ['tag' => 'og:image', 'why' => 'Product Pins are not shown without an image.'],
            ],
            'optional' => [
                ['tag' => 'product:availability', 'why' => 'Drives the "in stock" badge.'],
                ['tag' => 'og:url', 'why' => 'The canonical product URL.'],
                ['tag' => 'og:site_name', 'why' => 'Your shop name on the Pin.'],
            ],
        ],
    ];

    public static function key(): string
    {
        return 'pinterest.rich-pin-validator';
    }

    public function cacheTtl(): int
    {
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
                    'title' => 'Page URL',
                    'description' => 'The page a Pin will link to — a blog post or a product page.',
                    'minLength' => 4,
                    'maxLength' => 500,
                    'examples' => ['https://example.com/blog/sourdough-starter'],
                ],
                'type' => [
                    'type' => 'string',
                    'title' => 'Pin type',
                    'enum' => ['article', 'product'],
                    'default' => 'article',
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $url = trim($input->string('url'));
        $url = str_contains($url, '://') ? $url : "https://{$url}";

        $type = $input->string('type', 'article');
        $spec = self::TYPES[$type] ?? self::TYPES['article'];

        $html = SafeHttpClient::body(SafeHttpClient::get($url));

        /** @var array<string, string|null> $found */
        $found = [];

        foreach ([...$spec['required'], ...$spec['optional']] as $tag) {
            $found[$tag['tag']] = self::meta($html, $tag['tag']);
        }

        $requiredPresent = 0;
        $fixes = [];

        foreach ($spec['required'] as $tag) {
            if (($found[$tag['tag']] ?? null) !== null) {
                $requiredPresent++;

                continue;
            }

            $fixes[] = [
                'severity' => 'high',
                'title' => 'Add '.$tag['tag'],
                'detail' => $tag['why'],
            ];
        }

        $optionalPresent = count(array_filter(array_map(
            fn (array $tag) => $found[$tag['tag']] ?? null,
            $spec['optional'],
        ), fn (?string $value) => $value !== null));

        // og:type has to say the right thing, not merely exist.
        $declared = $found['og:type'] ?? null;

        if ($declared !== null && strtolower($declared) !== $type) {
            $fixes[] = [
                'severity' => 'high',
                'title' => "og:type says “{$declared}”, not “{$type}”",
                'detail' => 'Pinterest picks the Rich Pin format from this tag. Anything else and the page '
                    .'is treated as an ordinary link, however complete the rest of the markup is.',
            ];
            $requiredPresent = max(0, $requiredPresent - 1);
        }

        foreach ($spec['optional'] as $tag) {
            if (($found[$tag['tag']] ?? null) === null) {
                $fixes[] = ['severity' => 'low', 'title' => 'Consider '.$tag['tag'], 'detail' => $tag['why']];
            }
        }

        $requiredScore = (int) round($requiredPresent / count($spec['required']) * 100);
        $optionalScore = (int) round($optionalPresent / count($spec['optional']) * 100);
        // Required tags are the pass/fail; optional ones only polish the Pin.
        $overall = (int) round($requiredScore * 0.8 + $optionalScore * 0.2);

        // The score view draws each section's notes under its bar, so the tag list
        // lives there: a validator is only useful if you can see what it read.
        $notes = static function (array $tags) use ($found): array {
            return array_map(function (array $tag) use ($found): string {
                $value = $found[$tag['tag']] ?? null;

                return $value === null
                    ? $tag['tag'].' — not set'
                    : $tag['tag'].' — '.(mb_strlen($value) > 70 ? mb_substr($value, 0, 70).'…' : $value);
            }, $tags);
        };

        return ToolResult::score(
            overall: $overall,
            sections: [
                ['key' => 'required', 'label' => 'Required tags — '.$requiredPresent.' of '
                    .count($spec['required']), 'score' => $requiredScore, 'notes' => $notes($spec['required'])],
                ['key' => 'optional', 'label' => 'Optional tags — '.$optionalPresent.' of '
                    .count($spec['optional']), 'score' => $optionalScore, 'notes' => $notes($spec['optional'])],
            ],
            fixes: array_slice($fixes, 0, 6),
            summary: $requiredScore === 100
                ? "This page has everything the {$spec['label']} format needs. Apply for Rich Pins with this URL."
                : 'This page is short '.(count($spec['required']) - $requiredPresent)
                    ." of the {$spec['label']} requirements, so it will not validate yet.",
        )->withWarnings([
            'Rich Pins are enabled per domain, not per page: validate one page, and Pinterest applies '
            .'the format across the whole site. Validate a page that is typical of your markup.',
        ])->withMeta(['tags' => $found, 'type' => $type, 'url' => $url]);
    }

    /** Meta tags appear with either `property` or `name`, in either attribute order. */
    private static function meta(string $html, string $tag): ?string
    {
        $quoted = preg_quote($tag, '/');

        $patterns = [
            '/<meta[^>]+(?:property|name)=["\']'.$quoted.'["\'][^>]*content=["\']([^"\']*)["\']/i',
            '/<meta[^>]+content=["\']([^"\']*)["\'][^>]*(?:property|name)=["\']'.$quoted.'["\']/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $match) === 1 && trim($match[1]) !== '') {
                return html_entity_decode(trim($match[1]), ENT_QUOTES | ENT_HTML5);
            }
        }

        return null;
    }
}
