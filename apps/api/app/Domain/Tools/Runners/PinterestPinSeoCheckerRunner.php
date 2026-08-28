<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Domain\Tools\Exceptions\ToolExecutionException;
use App\Support\Social\PostLength;

/**
 * Pinterest is a search engine, so a Pin is scored like a search result.
 *
 * Everything here follows from that one fact: the keyword has to appear where
 * Pinterest indexes it (title, description, board), the description has to read as a
 * sentence rather than a hashtag pile, and the destination link has to exist or the
 * Pin cannot rank for anything commercial at all.
 */
final class PinterestPinSeoCheckerRunner implements Cacheable, ToolRunner
{
    private const TITLE_LIMIT = 100;

    private const DESCRIPTION_LIMIT = 500;

    public static function key(): string
    {
        return 'pinterest.pin-seo-checker';
    }

    public function cacheTtl(): int
    {
        return 3600;
    }

    public function inputSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'required' => ['title', 'keyword'],
            'additionalProperties' => false,
            'properties' => [
                'keyword' => [
                    'type' => 'string',
                    'title' => 'Target keyword',
                    'description' => 'The search someone would type to find this Pin.',
                    'minLength' => 2,
                    'maxLength' => 80,
                    'examples' => ['sourdough starter schedule'],
                ],
                'title' => [
                    'type' => 'string',
                    'title' => 'Pin title',
                    'minLength' => 1,
                    'maxLength' => 200,
                ],
                'description' => [
                    'type' => 'string',
                    'title' => 'Pin description',
                    'maxLength' => 800,
                    'default' => '',
                ],
                'board' => [
                    'type' => 'string',
                    'title' => 'Board name',
                    'description' => 'Board names are indexed too — a Pin on a relevant board ranks better.',
                    'maxLength' => 60,
                    'default' => '',
                ],
                'link' => [
                    'type' => 'string',
                    'title' => 'Destination link',
                    'maxLength' => 300,
                    'default' => '',
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $keyword = trim($input->string('keyword'));
        $title = trim($input->string('title'));

        if ($keyword === '' || $title === '') {
            throw ToolExecutionException::invalidInput(
                'A keyword and a title are both needed to score a Pin.',
                ['keyword' => 'Enter the search this Pin should rank for.'],
            );
        }

        $sections = [
            $this->titleSection($title, $keyword),
            $this->descriptionSection(trim($input->string('description')), $keyword),
            $this->boardSection(trim($input->string('board')), $keyword),
            $this->linkSection(trim($input->string('link'))),
        ];

        $overall = (int) round(array_sum(array_map(
            fn (array $section) => $section['score'] * $section['weight'],
            $sections,
        )));

        $fixes = [];

        foreach ($sections as $section) {
            foreach ($section['fixes'] as $fix) {
                $fixes[] = $fix;
            }
        }

        usort($fixes, fn (array $a, array $b) => $this->rank($b['severity']) <=> $this->rank($a['severity']));

        return ToolResult::score(
            overall: $overall,
            sections: array_map(fn (array $section) => [
                'key' => $section['key'],
                'label' => $section['label'],
                'score' => $section['score'],
                'weight' => $section['weight'],
                'notes' => $section['notes'],
            ], $sections),
            fixes: array_slice($fixes, 0, 6),
            summary: match (true) {
                $overall >= 80 => "This Pin is set up to be found for “{$keyword}”.",
                $overall >= 55 => "The basics are there, but “{$keyword}” is not working as hard as it could.",
                default => "Pinterest has very little to index for “{$keyword}” here.",
            },
        )->withMeta(['keyword' => $keyword, 'title_characters' => PostLength::graphemeCount($title)]);
    }

    /** @return array<string, mixed> */
    private function titleSection(string $title, string $keyword): array
    {
        $count = PostLength::graphemeCount($title);
        $score = 40;
        $notes = ["{$count}/".self::TITLE_LIMIT.' characters'];
        $fixes = [];

        if ($this->contains($title, $keyword)) {
            $score += 45;
            $notes[] = 'The keyword is in the title.';
        } else {
            $fixes[] = [
                'severity' => 'high',
                'title' => 'Put the keyword in the title',
                'detail' => "The title is the strongest signal Pinterest has. Work “{$keyword}” into it naturally.",
            ];
        }

        if ($count <= self::TITLE_LIMIT && $count >= 25) {
            $score += 15;
        }

        if ($count > self::TITLE_LIMIT) {
            $fixes[] = [
                'severity' => 'high',
                'title' => 'Shorten the title by '.($count - self::TITLE_LIMIT).' characters',
                'detail' => 'Anything past 100 characters is cut, and the feed tile shows far less than that.',
            ];
        }

        if ($count < 25) {
            $fixes[] = [
                'severity' => 'medium',
                'title' => 'Give the title more to index',
                'detail' => 'A very short title carries one signal. Add the outcome or the context: who it is for, what it produces.',
            ];
        }

        return ['key' => 'title', 'label' => 'Title', 'score' => min(100, $score), 'weight' => 0.35,
            'notes' => $notes, 'fixes' => $fixes];
    }

    /** @return array<string, mixed> */
    private function descriptionSection(string $description, string $keyword): array
    {
        $count = PostLength::graphemeCount($description);
        $hashtags = preg_match_all('/#\w+/u', $description);
        $words = count(preg_split('/\s+/u', $description, -1, PREG_SPLIT_NO_EMPTY) ?: []);

        $score = 25;
        $notes = ["{$count}/".self::DESCRIPTION_LIMIT.' characters'];
        $fixes = [];

        if ($description === '') {
            return ['key' => 'description', 'label' => 'Description', 'score' => 0, 'weight' => 0.3,
                'notes' => ['Empty.'], 'fixes' => [[
                    'severity' => 'high',
                    'title' => 'Write a description',
                    'detail' => 'An empty description throws away the largest indexable field on the Pin.',
                ]]];
        }

        if ($this->contains($description, $keyword)) {
            $score += 35;
            $notes[] = 'The keyword appears in the description.';
        } else {
            $fixes[] = [
                'severity' => 'high',
                'title' => 'Use the keyword in the first sentence',
                'detail' => "Pinterest reads the description as prose. Open with “{$keyword}” in a real sentence.",
            ];
        }

        if ($words >= 20) {
            $score += 25;
            $notes[] = "{$words} words — enough context to rank for related searches.";
        } else {
            $fixes[] = [
                'severity' => 'medium',
                'title' => 'Expand to at least 20 words',
                'detail' => 'Short descriptions rank for one phrase. Two or three sentences pick up the long tail.',
            ];
        }

        if ($count <= self::DESCRIPTION_LIMIT) {
            $score += 15;
        } else {
            $fixes[] = [
                'severity' => 'medium',
                'title' => 'Trim '.($count - self::DESCRIPTION_LIMIT).' characters',
                'detail' => 'Pinterest cuts the description at 500 characters.',
            ];
        }

        if ($hashtags > 3) {
            $score -= 20;
            $notes[] = "{$hashtags} hashtags.";
            $fixes[] = [
                'severity' => 'medium',
                'title' => 'Cut the hashtags back',
                'detail' => 'Hashtags do very little on Pinterest and a wall of them reads as spam. '
                    .'Keywords in a sentence do the work instead.',
            ];
        }

        return ['key' => 'description', 'label' => 'Description', 'score' => max(0, min(100, $score)),
            'weight' => 0.3, 'notes' => $notes, 'fixes' => $fixes];
    }

    /** @return array<string, mixed> */
    private function boardSection(string $board, string $keyword): array
    {
        if ($board === '') {
            return ['key' => 'board', 'label' => 'Board', 'score' => 40, 'weight' => 0.15,
                'notes' => ['Not given.'], 'fixes' => [[
                    'severity' => 'low',
                    'title' => 'Save it to a topic board',
                    'detail' => 'Board names are indexed. A Pin on a board about the same subject ranks better than '
                        .'the same Pin on a general one.',
                ]]];
        }

        $overlap = $this->overlaps($board, $keyword);

        return ['key' => 'board', 'label' => 'Board', 'score' => $overlap ? 100 : 60, 'weight' => 0.15,
            'notes' => [$overlap ? 'The board name shares terms with the keyword.' : "Board: “{$board}”."],
            'fixes' => $overlap ? [] : [[
                'severity' => 'low',
                'title' => 'Save it to a closer board',
                'detail' => "A board named around “{$keyword}” reinforces what this Pin is about.",
            ]]];
    }

    /** @return array<string, mixed> */
    private function linkSection(string $link): array
    {
        if ($link === '') {
            return ['key' => 'link', 'label' => 'Destination', 'score' => 20, 'weight' => 0.2,
                'notes' => ['No link.'], 'fixes' => [[
                    'severity' => 'high',
                    'title' => 'Add a destination link',
                    'detail' => 'A Pin with no link cannot send traffic and Pinterest has no page to corroborate '
                        .'what the Pin claims to be about.',
                ]]];
        }

        $https = str_starts_with(mb_strtolower($link), 'https://');

        return ['key' => 'link', 'label' => 'Destination', 'score' => $https ? 100 : 70, 'weight' => 0.2,
            'notes' => [$https ? 'Links out over HTTPS.' : 'The link is not HTTPS.'],
            'fixes' => $https ? [] : [[
                'severity' => 'medium',
                'title' => 'Use an https:// link',
                'detail' => 'Pinterest downranks and sometimes blocks insecure destinations.',
            ]]];
    }

    private function contains(string $haystack, string $keyword): bool
    {
        return str_contains($this->normalise($haystack), $this->normalise($keyword));
    }

    /** True when the two strings share a meaningful word, not just a stop word. */
    private function overlaps(string $a, string $b): bool
    {
        $words = fn (string $value) => array_filter(
            preg_split('/\s+/u', $this->normalise($value), -1, PREG_SPLIT_NO_EMPTY) ?: [],
            fn (string $word) => mb_strlen($word) > 3,
        );

        return array_intersect($words($a), $words($b)) !== [];
    }

    private function normalise(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower($value)) ?? $value);
    }

    private function rank(string $severity): int
    {
        return ['high' => 3, 'medium' => 2, 'low' => 1][$severity] ?? 0;
    }
}
