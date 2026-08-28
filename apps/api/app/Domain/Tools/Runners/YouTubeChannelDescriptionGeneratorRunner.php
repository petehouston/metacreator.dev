<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Support\Social\PostLength;

/**
 * Channel descriptions built to a structure, in three lengths.
 *
 * Template-driven rather than generative, for the same reason as the CTA generator:
 * the form is well understood and a proven structure filled with the creator's own
 * specifics beats a novel paragraph. The structure that matters is front-loading —
 * YouTube search indexes the whole description, but a human sees only the first
 * ~150 characters in the channel sidebar and search results, so the first sentence
 * has to name the topic, the audience and the schedule with no wind-up.
 */
final class YouTubeChannelDescriptionGeneratorRunner implements Cacheable, ToolRunner
{
    /** YouTube's hard limit on the About text. */
    private const LIMIT = 1000;

    /** What a viewer sees before "…more". */
    private const FOLD = 150;

    private const TONES = [
        'friendly' => [
            'label' => 'Friendly',
            'opener' => 'Welcome to :channel — :topic, made for :audience.',
            'body' => 'Every video here is built to be useful first: no padding, no ten-minute intros, '
                .'just the thing you came for.',
            'schedule' => 'New videos :schedule, so subscribe and you will not miss one.',
        ],
        'professional' => [
            'label' => 'Professional',
            'opener' => ':channel covers :topic for :audience.',
            'body' => 'The channel focuses on practical, tested material — the methods, tools and decisions '
                .'behind the result, explained in enough detail to be repeatable.',
            'schedule' => 'New videos publish :schedule.',
        ],
        'energetic' => [
            'label' => 'Energetic',
            'opener' => ':topic, without the boring parts. That is :channel.',
            'body' => 'If you are :audience and you would rather learn it in ten minutes than ten hours, '
                .'you are in exactly the right place.',
            'schedule' => 'New videos drop :schedule. Hit subscribe.',
        ],
        'expert' => [
            'label' => 'Expert',
            'opener' => ':channel is a channel about :topic, for :audience who want the detail.',
            'body' => 'Each video works through the reasoning rather than the headline — what was tried, '
                .'what failed, and what the evidence actually supports.',
            'schedule' => 'New analysis :schedule.',
        ],
    ];

    public static function key(): string
    {
        return 'youtube.channel-description-generator';
    }

    public function cacheTtl(): int
    {
        return 86400;
    }

    public function inputSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'required' => ['channel_name', 'topic'],
            'additionalProperties' => false,
            'properties' => [
                'channel_name' => [
                    'type' => 'string',
                    'title' => 'Channel name',
                    'minLength' => 1,
                    'maxLength' => 100,
                    'examples' => ['The Slow Loaf'],
                ],
                'topic' => [
                    'type' => 'string',
                    'title' => 'What the channel is about',
                    'description' => 'Write it as the phrase somebody would search for.',
                    'minLength' => 2,
                    'maxLength' => 150,
                    'examples' => ['sourdough baking for small kitchens'],
                ],
                'audience' => [
                    'type' => 'string',
                    'title' => 'Who it is for',
                    'maxLength' => 150,
                    'default' => 'anyone starting out',
                    'examples' => ['home bakers with no proving drawer'],
                ],
                'schedule' => [
                    'type' => 'string',
                    'title' => 'Upload schedule',
                    'maxLength' => 100,
                    'default' => 'every week',
                    'examples' => ['every Tuesday'],
                ],
                'tone' => [
                    'type' => 'string',
                    'title' => 'Tone',
                    'enum' => array_keys(self::TONES),
                    'default' => 'friendly',
                ],
                'keywords' => [
                    'type' => 'string',
                    'title' => 'Search terms to include (comma separated)',
                    'description' => 'The whole description is indexed, so the terms you want to be found '
                        .'for belong in it — in sentences, not as a keyword list.',
                    'maxLength' => 300,
                    'default' => '',
                    'examples' => ['sourdough starter, no-knead bread, bread scoring'],
                ],
                'links' => [
                    'type' => 'string',
                    'title' => 'Links (one per line, as “Label | URL”)',
                    'maxLength' => 600,
                    'default' => '',
                    'examples' => ["Newsletter | https://example.com\nRecipes | https://example.com/recipes"],
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $channel = trim($input->string('channel_name'));
        $topic = trim($input->string('topic'));
        $audience = trim($input->string('audience', 'anyone starting out')) ?: 'anyone starting out';
        $schedule = trim($input->string('schedule', 'every week')) ?: 'every week';
        $tone = self::TONES[$input->string('tone', 'friendly')] ?? self::TONES['friendly'];
        $keywords = $this->keywords($input->string('keywords'));
        $links = $this->links($input->string('links'));

        $fill = fn (string $pattern): string => strtr($pattern, [
            ':channel' => $channel,
            ':topic' => $topic,
            ':audience' => $audience,
            ':schedule' => $schedule,
        ]);

        $opener = $fill($tone['opener']);
        $body = $fill($tone['body']);
        $scheduleLine = $fill($tone['schedule']);

        $short = trim($opener.' '.$scheduleLine);
        $full = trim($opener."\n\n".$body.' '.$scheduleLine);

        if ($keywords !== []) {
            $full .= "\n\n".$this->keywordSentence($topic, $keywords);
        }

        if ($links !== []) {
            $full .= "\n\n".implode("\n", $links);
        }

        $blocks = [
            [
                'label' => 'Short version — fits the '.self::FOLD.'-character preview',
                'text' => $short,
            ],
            [
                'label' => 'Full version — the one to paste into About',
                'text' => $full,
            ],
            [
                'label' => 'First '.self::FOLD.' characters, as viewers will see them',
                'text' => $this->fold($full),
            ],
        ];

        $length = PostLength::graphemeCount($full);

        return ToolResult::textBlocks($blocks, summary: sprintf(
            'Three versions in a %s tone. The full description is %s of %s characters.',
            mb_strtolower($tone['label']),
            number_format($length),
            number_format(self::LIMIT),
        ))->withMeta([
            'characters' => $length,
            'characters_short' => PostLength::graphemeCount($short),
            'limit' => self::LIMIT,
            'fold' => self::FOLD,
        ])->withWarnings($this->warnings($length, $keywords));
    }

    /** @param  list<string>  $keywords */
    private function keywordSentence(string $topic, array $keywords): string
    {
        $last = array_pop($keywords);

        $list = $keywords === [] ? $last : implode(', ', $keywords).' and '.$last;

        return "You will find videos on {$list} here, along with everything else that comes under {$topic}.";
    }

    /** @return list<string> */
    private function keywords(string $value): array
    {
        return array_slice(array_filter(
            array_map('trim', explode(',', $value)),
            fn (string $keyword) => $keyword !== '',
        ), 0, 8);
    }

    /**
     * Links are written one per line so YouTube's About panel renders each as its
     * own row; a label with no URL is dropped rather than shown half-formed.
     *
     * @return list<string>
     */
    private function links(string $value): array
    {
        $links = [];

        foreach (preg_split('/\R/u', $value) ?: [] as $line) {
            $parts = array_map('trim', explode('|', $line, 2));

            if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
                continue;
            }

            $links[] = "{$parts[0]}: {$parts[1]}";
        }

        return array_slice($links, 0, 10);
    }

    private function fold(string $description): string
    {
        $flat = trim(preg_replace('/\s+/u', ' ', $description) ?? $description);

        return PostLength::graphemeCount($flat) <= self::FOLD
            ? $flat
            : mb_substr($flat, 0, self::FOLD).'…';
    }

    /**
     * @param  list<string>  $keywords
     * @return list<string>
     */
    private function warnings(int $length, array $keywords): array
    {
        $warnings = [];

        if ($length > self::LIMIT) {
            $warnings[] = 'The full version is '.number_format($length - self::LIMIT)
                .' characters over YouTube’s 1,000-character limit. Trim the keyword sentence or the links.';
        }

        if ($keywords === []) {
            $warnings[] = 'No search terms were given, so the description carries only the words in the '
                .'template. Adding three or four real search phrases is the single biggest improvement '
                .'available here.';
        }

        return $warnings;
    }
}
