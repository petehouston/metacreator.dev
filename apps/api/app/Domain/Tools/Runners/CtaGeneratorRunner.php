<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;

/**
 * Calls to action built from patterns that work, filled with your specifics.
 *
 * Template-driven rather than generative: a CTA is a short, well-understood form,
 * and a proven pattern with the user's own subject in it beats a novel sentence.
 */
final class CtaGeneratorRunner implements Cacheable, ToolRunner
{
    /**
     * `:topic` and `:audience` are substituted. Grouped by goal because the goal is
     * the only thing that reliably changes which pattern works.
     *
     * @var array<string, array{label: string, patterns: list<string>}>
     */
    private const GOALS = [
        'follow' => ['label' => 'Grow followers', 'patterns' => [
            'Follow for more on :topic — one post a day, no fluff.',
            'If :topic is your thing, you are in the right place. Hit follow.',
            'I post :topic breakdowns every week. Follow so the next one finds you.',
            'Saving this? Follow too — the rest of the series lands here first.',
        ]],
        'comment' => ['label' => 'Drive comments', 'patterns' => [
            'What is the hardest part of :topic for you right now? Tell me below.',
            'Comment “:keyword” and I will send you the full :topic breakdown.',
            'Agree or disagree with this take on :topic? I want the argument.',
            'Which of these :topic tips are you actually going to try? Number it below.',
        ]],
        'save' => ['label' => 'Earn saves', 'patterns' => [
            'Save this — you will want it the next time you touch :topic.',
            'Bookmark this before it disappears down your feed.',
            'This is the :topic checklist. Save it, then work through it.',
        ]],
        'click' => ['label' => 'Send traffic', 'patterns' => [
            'Full :topic guide is in my bio — it is free.',
            'Link in bio for the complete :topic walkthrough.',
            'I wrote the long version for :audience. Link in bio.',
        ]],
        'subscribe' => ['label' => 'Get signups', 'patterns' => [
            'Join :audience getting one :topic idea every Tuesday. Link in bio.',
            'The newsletter goes deeper than the feed allows. Subscribe in bio.',
            'Want the template I used here? It goes out to subscribers this week.',
        ]],
        'buy' => ['label' => 'Sell something', 'patterns' => [
            'If you want this done for you instead, the link is in my bio.',
            'I built the :topic system I use into a product. Link in bio.',
            'Doors close Friday for :audience who want the full :topic system.',
        ]],
    ];

    public static function key(): string
    {
        return 'content.cta-generator';
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
            'required' => ['topic', 'goal'],
            'additionalProperties' => false,
            'properties' => [
                'topic' => [
                    'type' => 'string',
                    'title' => 'What is the post about?',
                    'minLength' => 2,
                    'maxLength' => 120,
                    'examples' => ['sourdough baking'],
                ],
                'goal' => [
                    'type' => 'string',
                    'title' => 'What do you want them to do?',
                    'enum' => array_keys(self::GOALS),
                    'default' => 'comment',
                ],
                'audience' => [
                    'type' => 'string',
                    'title' => 'Who is it for? (optional)',
                    'maxLength' => 120,
                    'default' => 'creators',
                    'examples' => ['home bakers'],
                ],
                'keyword' => [
                    'type' => 'string',
                    'title' => 'Comment keyword (optional)',
                    'description' => 'Used by the “comment this word” patterns.',
                    'maxLength' => 20,
                    'default' => 'GUIDE',
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $topic = trim($input->string('topic'));
        $goal = $input->string('goal', 'comment');
        $audience = trim($input->string('audience', 'creators')) ?: 'creators';
        $keyword = mb_strtoupper(trim($input->string('keyword', 'GUIDE')) ?: 'GUIDE');

        $group = self::GOALS[$goal] ?? self::GOALS['comment'];

        $items = array_map(fn (string $pattern) => [
            'title' => $group['label'],
            'body' => strtr($pattern, [':topic' => $topic, ':audience' => $audience, ':keyword' => $keyword]),
        ], $group['patterns']);

        // A second goal's worth of options, so there is always something to test against.
        foreach (self::GOALS as $key => $other) {
            if ($key === $goal) {
                continue;
            }

            $items[] = [
                'title' => $other['label'],
                'body' => strtr($other['patterns'][0], [':topic' => $topic, ':audience' => $audience, ':keyword' => $keyword]),
            ];
        }

        return ToolResult::cards(
            $items,
            summary: 'One CTA per post. Pick the one that matches what this post earned — '
                .'asking for a sale after a tip post is why CTAs get ignored.',
        );
    }
}
