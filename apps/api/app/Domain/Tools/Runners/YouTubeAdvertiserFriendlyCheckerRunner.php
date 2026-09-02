<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;

/**
 * Your script read against the categories YouTube publishes in its
 * advertiser-friendly content guidelines — before you record it, rather than after
 * the yellow icon appears.
 *
 * Be clear about what this is. YouTube's classifier reads the video: the audio, the
 * frames, the thumbnail, the title and the context around every word. **This reads
 * text, and text alone.** It cannot tell an anti-drug documentary from a drug
 * advertisement, and neither can any other checker that claims to.
 *
 * What text *can* do is find the terms that put a video into one of the published
 * categories in the first place, and say which category and where. That is worth
 * doing at the script stage for one specific reason: the guidelines single out the
 * **opening** of a video, and the opening is the cheapest part of a script to
 * rewrite. A word at 0:04 and the same word at 14:20 are not the same risk, and
 * this weights them accordingly.
 *
 * Every category below is one YouTube names. Two of them — hateful content and
 * controversial issues — are deliberately not given a word list: they are decided
 * by meaning rather than vocabulary, and a list of trigger words for them would
 * flag every news channel on the platform while missing the videos that actually
 * demonetize. They appear as prompts to review instead, which is the honest thing a
 * text tool can offer.
 */
final class YouTubeAdvertiserFriendlyCheckerRunner implements Cacheable, ToolRunner
{
    /** Words in roughly the first thirty seconds of narration, at ~150 wpm. */
    private const OPENING_WORDS = 75;

    /**
     * The categories, with the vocabulary that lands a script in each.
     *
     * `weight` is how much a single hit costs the score, before the opening
     * multiplier. `terms` are matched on word boundaries and case-insensitively.
     *
     * @var array<string, array{label: string, weight: int, guidance: string, terms: list<string>}>
     */
    private const CATEGORIES = [
        'language' => [
            'label' => 'Inappropriate language',
            'weight' => 6,
            'guidance' => 'Strong profanity in the first several seconds, or used repeatedly '
                .'throughout, is the single most common cause of limited ads.',
            'terms' => ['fuck', 'fucking', 'fucked', 'motherfucker', 'shit', 'bullshit', 'bitch',
                'bastard', 'asshole', 'dick', 'piss', 'cunt', 'wtf', 'stfu'],
        ],
        'violence' => [
            'label' => 'Violence',
            'weight' => 7,
            'guidance' => 'Descriptions of real violence, injury or death. Dramatised or gaming '
                .'violence is treated differently, but the words still trip the first pass.',
            'terms' => ['kill', 'killed', 'killing', 'murder', 'murdered', 'stabbed', 'shooting',
                'shot dead', 'massacre', 'torture', 'beheading', 'blood', 'gore', 'brutal',
                'execution', 'assault'],
        ],
        'adult' => [
            'label' => 'Adult content',
            'weight' => 8,
            'guidance' => 'Sexual language, even used clinically, is one of the strictest categories.',
            'terms' => ['porn', 'pornography', 'nude', 'nudity', 'naked', 'sex', 'sexual', 'erotic',
                'onlyfans', 'strip club', 'fetish', 'orgasm'],
        ],
        'shocking' => [
            'label' => 'Shocking content',
            'guidance' => 'Language written to disturb — the thumbnail-and-title register that gets '
                .'a video clicked and demonetized in the same move.',
            'weight' => 5,
            'terms' => ['disturbing', 'horrifying', 'gruesome', 'graphic footage', 'nightmare fuel',
                'traumatic', 'sickening'],
        ],
        'harmful' => [
            'label' => 'Harmful or dangerous acts',
            'weight' => 8,
            'guidance' => 'Stunts, challenges, hacking, and anything a viewer could copy and be hurt '
                .'by. Naming the act is enough to be classified, even in a warning.',
            'terms' => ['challenge gone wrong', 'prank', 'stunt', 'hack', 'hacking', 'exploit',
                'bypass', 'jailbreak', 'crack', 'diy weapon', 'homemade explosive'],
        ],
        'drugs' => [
            'label' => 'Recreational drugs',
            'weight' => 7,
            'guidance' => 'Includes cannabis in territories where it is legal, and includes '
                .'depictions in an educational frame.',
            'terms' => ['cocaine', 'heroin', 'meth', 'methamphetamine', 'weed', 'marijuana',
                'cannabis', 'edibles', 'lsd', 'mdma', 'ecstasy', 'shrooms', 'vape', 'vaping',
                'high as'],
        ],
        'firearms' => [
            'label' => 'Firearms',
            'weight' => 7,
            'guidance' => 'Sale, assembly, modification or demonstration of firearms and their '
                .'parts. Mentioning one in passing is not the same as the guidelines’ target, but '
                .'it is what a text pass sees.',
            'terms' => ['gun', 'guns', 'rifle', 'shotgun', 'pistol', 'handgun', 'ammo', 'ammunition',
                'silencer', 'suppressor', 'ar-15', 'glock', 'firearm'],
        ],
        'sensitive' => [
            'label' => 'Sensitive events',
            'weight' => 6,
            'guidance' => 'War, terrorism, natural disaster and mass tragedy. Reporting on one is '
                .'covered by the news exception; a channel without that context usually is not.',
            'terms' => ['terrorist', 'terrorism', 'bombing', 'war crime', 'genocide', 'pandemic',
                'mass shooting', 'hostage', 'suicide', 'self-harm'],
        ],
        'tobacco' => [
            'label' => 'Tobacco',
            'weight' => 5,
            'guidance' => 'Tobacco and tobacco-adjacent products, including promotion of vaping '
                .'hardware.',
            'terms' => ['cigarette', 'cigarettes', 'smoking', 'tobacco', 'nicotine', 'cigar',
                'juul'],
        ],
        'gambling' => [
            'label' => 'Gambling-related content',
            'weight' => 6,
            'guidance' => 'Casino play, betting, and the loot-box and skin-trading content that sits '
                .'beside it.',
            'terms' => ['casino', 'gambling', 'betting', 'bet365', 'roulette', 'slots', 'poker',
                'sportsbook', 'loot box', 'csgo skins'],
        ],
    ];

    /**
     * Categories decided by meaning rather than vocabulary.
     *
     * @var array<string, string>
     */
    private const JUDGEMENT_ONLY = [
        'Hateful & derogatory content' => 'Decided by who a statement is about and what it says '
            .'about them, which no word list can see. Read your script back and ask whether any '
            .'passage would read as demeaning a group rather than criticising an argument.',
        'Controversial issues' => 'Abortion, immigration, elections and similar subjects are '
            .'classified on treatment rather than on terms. If your video takes a side on one, '
            .'expect limited ads regardless of how it is worded.',
    ];

    public static function key(): string
    {
        return 'youtube.advertiser-friendly-checker';
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
            'required' => ['script'],
            'additionalProperties' => false,
            'properties' => [
                'script' => [
                    'type' => 'string',
                    'x-control' => 'textarea',
                    'title' => 'Script, transcript or description',
                    'description' => 'Paste what will be said. A transcript of a video you have '
                        .'already published works just as well.',
                    'minLength' => 10,
                    'maxLength' => 60000,
                ],
                'title' => [
                    'type' => 'string',
                    'x-control' => 'text',
                    'title' => 'Video title (optional)',
                    'description' => 'Checked separately and weighted hardest — the title is read on '
                        .'every surface the video appears on.',
                    'maxLength' => 200,
                    'default' => '',
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $script = trim($input->string('script'));
        $title = trim($input->string('title'));

        $words = preg_split('/\s+/u', mb_strtolower($script), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $haystack = ' '.mb_strtolower($script).' ';
        $titleHaystack = ' '.mb_strtolower($title).' ';

        $sections = [];
        $fixes = [];
        $penalty = 0;
        $flaggedTerms = [];

        foreach (self::CATEGORIES as $key => $category) {
            $hits = $this->hits($category['terms'], $haystack, $words, $titleHaystack);

            if ($hits === []) {
                $sections[] = ['key' => $key, 'label' => $category['label'], 'score' => 100];

                continue;
            }

            $cost = 0;
            $notes = [];

            foreach ($hits as $hit) {
                $multiplier = $hit['in_title'] ? 3.0 : ($hit['in_opening'] ? 2.0 : 1.0);
                $cost += (int) round($category['weight'] * $multiplier * min($hit['count'], 3) / 1);
                $flaggedTerms[] = $hit['term'];

                $notes[] = '“'.$hit['term'].'” × '.$hit['count']
                    .($hit['in_title'] ? ' — in the title' : ($hit['in_opening'] ? ' — in the opening' : ''));
            }

            $score = max(0, 100 - $cost * 4);
            $penalty += $cost;

            $sections[] = [
                'key' => $key,
                'label' => $category['label'],
                'score' => $score,
                'notes' => array_slice($notes, 0, 4),
            ];

            $fixes[] = [
                'severity' => $score < 40 ? 'high' : ($score < 75 ? 'medium' : 'low'),
                'title' => $category['label'].': '.count($hits).' term'
                    .(count($hits) === 1 ? '' : 's').' found',
                'detail' => $category['guidance'].' Found: '.implode(', ', array_slice(
                    array_map(fn (array $hit) => $hit['term'], $hits), 0, 8,
                )).'.',
            ];
        }

        foreach (array_keys(self::JUDGEMENT_ONLY) as $label) {
            // Scored 100 because nothing was found, not because nothing is there:
            // the note says so, and the fix list carries the review prompt.
            $sections[] = ['key' => 'judgement.'.md5($label), 'label' => $label, 'score' => 100,
                'notes' => ['Not checked by vocabulary — review this one by hand.']];
        }

        $overall = max(0, 100 - min(100, $penalty * 3));

        usort($fixes, fn (array $a, array $b) => $this->severityRank($b['severity'])
            <=> $this->severityRank($a['severity']));

        return ToolResult::score(
            overall: $overall,
            sections: $sections,
            fixes: [...$fixes, ...array_map(
                fn (string $label) => [
                    'severity' => 'low',
                    'title' => $label.': review this by hand',
                    'detail' => self::JUDGEMENT_ONLY[$label],
                ],
                array_keys(self::JUDGEMENT_ONLY),
            )],
            summary: $this->summary($overall, count($flaggedTerms), $title !== ''),
        )->withMeta([
            'words' => count($words),
            'flagged_terms' => array_values(array_unique($flaggedTerms)),
            'opening_words_checked' => min(count($words), self::OPENING_WORDS),
        ])->withWarnings([
            'This reads text. YouTube’s classifier watches the video, hears the audio and reads the '
            .'thumbnail and title in context — so a clean score here is not a promise of a green '
            .'icon, and a low one is not a verdict. It is a list of the words worth a second look.',
            'Context is the whole game and a word list cannot see it: a documentary about addiction '
            .'and a video glorifying it use the same vocabulary. Where a term is load-bearing for '
            .'your subject, keep it and expect the review — self-certifying honestly is what keeps a '
            .'channel out of trouble.',
            'The categories are YouTube’s own, from its advertiser-friendly content guidelines. The '
            .'vocabulary under each is ours, and it is not exhaustive.',
        ]);
    }

    /**
     * @param  list<string>  $terms
     * @param  list<string>  $words
     * @return list<array{term: string, count: int, in_opening: bool, in_title: bool}>
     */
    private function hits(array $terms, string $haystack, array $words, string $titleHaystack): array
    {
        $opening = ' '.implode(' ', array_slice($words, 0, self::OPENING_WORDS)).' ';
        $hits = [];

        foreach ($terms as $term) {
            $pattern = '/\b'.preg_quote($term, '/').'\b/iu';
            $count = preg_match_all($pattern, $haystack);

            $inTitle = $titleHaystack !== '  ' && preg_match($pattern, $titleHaystack) === 1;

            if ($count < 1 && ! $inTitle) {
                continue;
            }

            $hits[] = [
                'term' => $term,
                'count' => max($count, 1),
                'in_opening' => preg_match($pattern, $opening) === 1,
                'in_title' => $inTitle,
            ];
        }

        return $hits;
    }

    private function severityRank(string $severity): int
    {
        return match ($severity) {
            'high' => 3,
            'medium' => 2,
            default => 1,
        };
    }

    private function summary(int $overall, int $flagged, bool $hasTitle): string
    {
        if ($flagged === 0) {
            return 'Nothing in this script matches the vocabulary behind any of YouTube’s published '
                .'categories'.($hasTitle ? ', title included' : '')
                .'. The two categories decided by meaning rather than words are still yours to '
                .'review.';
        }

        return $flagged.' term'.($flagged === 1 ? '' : 's').' worth a second look, scoring '
            .$overall.'/100. Terms in the title and in the opening thirty seconds are weighted '
            .'hardest, because that is where the guidelines say placement matters most.';
    }
}
