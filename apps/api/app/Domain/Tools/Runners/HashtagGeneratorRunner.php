<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Domain\Tools\Exceptions\ToolExecutionException;

/**
 * Builds a mixed-difficulty hashtag set.
 *
 * The common mistake is stacking only huge tags, where a small account is buried
 * within seconds. A working set is mostly niche and small tags — where a post can
 * actually rank and stay visible — with a couple of broad ones for reach. This
 * returns tags grouped by that strategy and explains the ratio, because the ratio is
 * the actual advice.
 */
final class HashtagGeneratorRunner implements Cacheable, ToolRunner
{
    /** Suffixes that turn a topic into a real, searched community tag. */
    private const NICHE_SUFFIXES = [
        'tips', 'community', 'daily', 'life', 'ideas', 'inspo', 'hacks',
        'forbeginners', 'guide', 'creator', 'journey', 'routine',
    ];

    private const BROAD_SUFFIXES = ['', 'lover', 'addict', 'world', 'gram'];

    /** @var array<string, list<string>> */
    private const PLATFORM_STAPLES = [
        'instagram' => ['instagood', 'reels', 'explorepage', 'igdaily'],
        'tiktok' => ['fyp', 'foryoupage', 'tiktokviral', 'learnontiktok'],
        'youtube' => ['shorts', 'youtubeshorts', 'youtuber', 'newvideo'],
        'x' => ['thread', 'buildinpublic'],
        'linkedin' => ['careergrowth', 'leadership', 'personalbranding'],
    ];

    /** Recommended totals differ sharply by platform. */
    private const RECOMMENDED_COUNT = [
        'instagram' => 12,
        'tiktok' => 5,
        'youtube' => 4,
        'x' => 2,
        'linkedin' => 3,
    ];

    public static function key(): string
    {
        return 'content.hashtag-generator';
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
            'required' => ['topic'],
            'additionalProperties' => false,
            'properties' => [
                'topic' => [
                    'type' => 'string',
                    'title' => 'Topic or keyword',
                    'description' => 'What is the post about? "sourdough baking", "home gym", "swift ui".',
                    'minLength' => 2,
                    'maxLength' => 80,
                    'examples' => ['sourdough baking'],
                ],
                'platform' => [
                    'type' => 'string',
                    'title' => 'Platform',
                    'enum' => ['instagram', 'tiktok', 'youtube', 'x', 'linkedin'],
                    'default' => 'instagram',
                ],
                'extra_keywords' => [
                    'type' => 'string',
                    'title' => 'Related words (optional)',
                    'description' => 'Comma-separated. More context produces better niche tags.',
                    'maxLength' => 200,
                    'default' => '',
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $topic = trim($input->string('topic'));
        $platform = $input->string('platform', 'instagram');

        $seeds = $this->seeds($topic, $input->string('extra_keywords'));

        if ($seeds === []) {
            throw ToolExecutionException::invalidInput(
                'Enter a topic with at least two letters.',
                ['topic' => 'This does not contain anything we can build tags from.'],
            );
        }

        // Paid users get a larger set to pick from; free users get a usable one.
        $perGroup = $context->scaled(free: 5, account: 8, paid: 14);

        $niche = $this->build($seeds, self::NICHE_SUFFIXES, $perGroup);
        $broad = $this->build($seeds, self::BROAD_SUFFIXES, (int) ceil($perGroup / 2));
        $staples = array_slice(self::PLATFORM_STAPLES[$platform] ?? [], 0, 4);

        $recommended = $this->recommendedSet($niche, $broad, $staples, self::RECOMMENDED_COUNT[$platform] ?? 10);

        $items = [
            [
                'title' => 'Recommended set — copy this',
                'body' => $this->format($recommended),
                'meta' => [
                    'count' => count($recommended),
                    'emphasis' => true,
                    'note' => 'Roughly 70% niche, 20% broad, 10% platform staples.',
                ],
            ],
            [
                'title' => 'Niche tags (where you can actually rank)',
                'body' => $this->format($niche),
                'meta' => ['count' => count($niche), 'note' => 'Smaller audiences, far longer visibility for a small account.'],
            ],
            [
                'title' => 'Broad tags (reach, short-lived)',
                'body' => $this->format($broad),
                'meta' => ['count' => count($broad), 'note' => 'High volume — use two or three at most.'],
            ],
        ];

        if ($staples !== []) {
            $items[] = [
                'title' => ucfirst($platform).' staples',
                'body' => $this->format($staples),
                'meta' => ['count' => count($staples), 'note' => 'Conventional on this platform; low differentiation.'],
            ];
        }

        return ToolResult::cards(
            items: $items,
            summary: 'Built '.(count($niche) + count($broad) + count($staples))." tags for \"{$topic}\" on ".ucfirst($platform).'.',
        )->withWarnings([
            'Hashtags help discovery; they do not rescue a weak hook. Check the tags still fit before posting — communities drift.',
        ]);
    }

    /** @return list<string> */
    private function seeds(string $topic, string $extra): array
    {
        $words = preg_split('/[\s,]+/u', mb_strtolower($topic.' '.$extra), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $seeds = [];

        foreach ($words as $word) {
            $clean = preg_replace('/[^\p{L}\p{N}]/u', '', $word) ?? '';

            if (mb_strlen($clean) >= 2) {
                $seeds[] = $clean;
            }
        }

        // The full phrase is usually the single best tag, so it leads.
        $phrase = preg_replace('/[^\p{L}\p{N}]/u', '', mb_strtolower($topic)) ?? '';

        if (mb_strlen($phrase) >= 3 && ! in_array($phrase, $seeds, true)) {
            array_unshift($seeds, $phrase);
        }

        return array_values(array_unique($seeds));
    }

    /**
     * @param  list<string>  $seeds
     * @param  list<string>  $suffixes
     * @return list<string>
     */
    private function build(array $seeds, array $suffixes, int $limit): array
    {
        $tags = [];

        foreach ($seeds as $seed) {
            foreach ($suffixes as $suffix) {
                $tag = $seed.$suffix;

                if (mb_strlen($tag) <= 30 && ! in_array($tag, $tags, true)) {
                    $tags[] = $tag;
                }

                if (count($tags) >= $limit) {
                    return $tags;
                }
            }
        }

        return $tags;
    }

    /**
     * @param  list<string>  $niche
     * @param  list<string>  $broad
     * @param  list<string>  $staples
     * @return list<string>
     */
    private function recommendedSet(array $niche, array $broad, array $staples, int $total): array
    {
        return array_values(array_unique([
            ...array_slice($niche, 0, (int) round($total * 0.7)),
            ...array_slice($broad, 0, (int) round($total * 0.2)),
            ...array_slice($staples, 0, max(1, (int) round($total * 0.1))),
        ]));
    }

    /** @param  list<string>  $tags */
    private function format(array $tags): string
    {
        return implode(' ', array_map(fn (string $t) => '#'.$t, $tags));
    }
}
