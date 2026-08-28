<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;

/**
 * Emoji search that matches on meaning, not just on the official name.
 *
 * The system emoji picker only matches Unicode names, so "engagement" finds
 * nothing. Each entry here carries the words creators actually search for.
 */
final class EmojiPickerRunner implements Cacheable, ToolRunner
{
    /** @var array<string, array{name: string, group: string, keywords: string}> */
    private const EMOJI = [
        '🔥' => ['name' => 'Fire', 'group' => 'reaction', 'keywords' => 'hot trending lit viral popular hype'],
        '✨' => ['name' => 'Sparkles', 'group' => 'reaction', 'keywords' => 'magic new shiny special aesthetic clean'],
        '🚀' => ['name' => 'Rocket', 'group' => 'growth', 'keywords' => 'launch growth fast scale startup ship'],
        '📈' => ['name' => 'Chart increasing', 'group' => 'growth', 'keywords' => 'growth analytics up results metrics engagement'],
        '📉' => ['name' => 'Chart decreasing', 'group' => 'growth', 'keywords' => 'decline down loss drop metrics'],
        '📊' => ['name' => 'Bar chart', 'group' => 'growth', 'keywords' => 'analytics data stats report dashboard insights'],
        '💰' => ['name' => 'Money bag', 'group' => 'money', 'keywords' => 'money revenue income earnings paid monetisation'],
        '💸' => ['name' => 'Money with wings', 'group' => 'money', 'keywords' => 'spend cost budget ads price'],
        '🤑' => ['name' => 'Money-mouth face', 'group' => 'money', 'keywords' => 'rich profit payday cash'],
        '🎯' => ['name' => 'Direct hit', 'group' => 'strategy', 'keywords' => 'target goal focus niche audience aim'],
        '🧠' => ['name' => 'Brain', 'group' => 'strategy', 'keywords' => 'smart idea think strategy learn psychology'],
        '💡' => ['name' => 'Light bulb', 'group' => 'strategy', 'keywords' => 'idea tip insight inspiration hint'],
        '🔑' => ['name' => 'Key', 'group' => 'strategy', 'keywords' => 'secret unlock important key takeaway'],
        '🧵' => ['name' => 'Thread', 'group' => 'content', 'keywords' => 'thread twitter x series breakdown'],
        '📌' => ['name' => 'Pushpin', 'group' => 'content', 'keywords' => 'pin save important note bookmark'],
        '📍' => ['name' => 'Round pushpin', 'group' => 'content', 'keywords' => 'location place here map'],
        '👇' => ['name' => 'Down pointing', 'group' => 'cta', 'keywords' => 'below comment cta look down read more'],
        '👉' => ['name' => 'Right pointing', 'group' => 'cta', 'keywords' => 'swipe next link cta bio'],
        '⬇️' => ['name' => 'Down arrow', 'group' => 'cta', 'keywords' => 'download below scroll cta'],
        '🔗' => ['name' => 'Link', 'group' => 'cta', 'keywords' => 'link bio url website click'],
        '🔔' => ['name' => 'Bell', 'group' => 'cta', 'keywords' => 'notification subscribe alert reminder'],
        '💬' => ['name' => 'Speech balloon', 'group' => 'cta', 'keywords' => 'comment reply dm chat discussion'],
        '❤️' => ['name' => 'Red heart', 'group' => 'reaction', 'keywords' => 'love like heart favourite'],
        '🩷' => ['name' => 'Pink heart', 'group' => 'reaction', 'keywords' => 'love soft cute aesthetic pink'],
        '👏' => ['name' => 'Clapping hands', 'group' => 'reaction', 'keywords' => 'applause congrats well done praise'],
        '🙌' => ['name' => 'Raising hands', 'group' => 'reaction', 'keywords' => 'celebrate yes win praise hooray'],
        '🤯' => ['name' => 'Exploding head', 'group' => 'reaction', 'keywords' => 'mind blown shock wow surprising'],
        '😭' => ['name' => 'Loudly crying', 'group' => 'reaction', 'keywords' => 'crying funny emotional relatable'],
        '😅' => ['name' => 'Grinning with sweat', 'group' => 'reaction', 'keywords' => 'awkward relatable nervous oops'],
        '👀' => ['name' => 'Eyes', 'group' => 'reaction', 'keywords' => 'look watch teaser sneak peek attention'],
        '🫶' => ['name' => 'Heart hands', 'group' => 'reaction', 'keywords' => 'love community thanks appreciation'],
        '⚡' => ['name' => 'High voltage', 'group' => 'reaction', 'keywords' => 'fast energy power quick instant'],
        '🎬' => ['name' => 'Clapper board', 'group' => 'video', 'keywords' => 'video film shoot production reel'],
        '🎥' => ['name' => 'Movie camera', 'group' => 'video', 'keywords' => 'video camera record filming youtube'],
        '📹' => ['name' => 'Video camera', 'group' => 'video', 'keywords' => 'video record vlog live'],
        '📱' => ['name' => 'Mobile phone', 'group' => 'video', 'keywords' => 'phone mobile app vertical screen'],
        '🎙️' => ['name' => 'Studio microphone', 'group' => 'video', 'keywords' => 'podcast audio record voice interview'],
        '🎧' => ['name' => 'Headphone', 'group' => 'video', 'keywords' => 'listen audio podcast music sound'],
        '🎵' => ['name' => 'Musical note', 'group' => 'video', 'keywords' => 'music sound audio trending tiktok'],
        '📸' => ['name' => 'Camera with flash', 'group' => 'video', 'keywords' => 'photo picture shoot instagram'],
        '🖼️' => ['name' => 'Framed picture', 'group' => 'video', 'keywords' => 'image photo thumbnail gallery'],
        '✍️' => ['name' => 'Writing hand', 'group' => 'content', 'keywords' => 'write copy blog caption draft'],
        '📝' => ['name' => 'Memo', 'group' => 'content', 'keywords' => 'notes write list plan draft'],
        '📖' => ['name' => 'Open book', 'group' => 'content', 'keywords' => 'read story guide learn newsletter'],
        '📚' => ['name' => 'Books', 'group' => 'content', 'keywords' => 'learn course library resources study'],
        '🗓️' => ['name' => 'Spiral calendar', 'group' => 'content', 'keywords' => 'calendar schedule plan date posting'],
        '⏰' => ['name' => 'Alarm clock', 'group' => 'content', 'keywords' => 'time deadline reminder schedule urgent'],
        '⏳' => ['name' => 'Hourglass', 'group' => 'content', 'keywords' => 'wait time limited soon countdown'],
        '✅' => ['name' => 'Check mark', 'group' => 'list', 'keywords' => 'done yes correct checklist complete'],
        '❌' => ['name' => 'Cross mark', 'group' => 'list', 'keywords' => 'no wrong stop avoid mistake'],
        '⚠️' => ['name' => 'Warning', 'group' => 'list', 'keywords' => 'warning caution careful risk'],
        '❗' => ['name' => 'Exclamation', 'group' => 'list', 'keywords' => 'important attention urgent note'],
        '1️⃣' => ['name' => 'Keycap one', 'group' => 'list', 'keywords' => 'one first step number list'],
        '2️⃣' => ['name' => 'Keycap two', 'group' => 'list', 'keywords' => 'two second step number list'],
        '3️⃣' => ['name' => 'Keycap three', 'group' => 'list', 'keywords' => 'three third step number list'],
        '▪️' => ['name' => 'Small square', 'group' => 'list', 'keywords' => 'bullet point list divider minimal'],
        '➡️' => ['name' => 'Right arrow', 'group' => 'list', 'keywords' => 'next then arrow result swipe'],
        '🏆' => ['name' => 'Trophy', 'group' => 'growth', 'keywords' => 'win award best winner giveaway'],
        '🥇' => ['name' => 'Gold medal', 'group' => 'growth', 'keywords' => 'first best winner top ranking'],
        '🎁' => ['name' => 'Wrapped gift', 'group' => 'growth', 'keywords' => 'giveaway free gift prize bonus'],
        '🎉' => ['name' => 'Party popper', 'group' => 'growth', 'keywords' => 'celebrate milestone launch congrats party'],
        '🥳' => ['name' => 'Partying face', 'group' => 'growth', 'keywords' => 'celebrate milestone birthday happy'],
        '💪' => ['name' => 'Flexed biceps', 'group' => 'growth', 'keywords' => 'strong effort fitness discipline consistency'],
        '🧘' => ['name' => 'Person in lotus', 'group' => 'lifestyle', 'keywords' => 'calm wellness mindful yoga balance'],
        '☕' => ['name' => 'Hot beverage', 'group' => 'lifestyle', 'keywords' => 'coffee morning routine break cosy'],
        '🍳' => ['name' => 'Cooking', 'group' => 'lifestyle', 'keywords' => 'food cooking recipe kitchen breakfast'],
        '🥐' => ['name' => 'Croissant', 'group' => 'lifestyle', 'keywords' => 'bakery bread food pastry baking'],
        '🍞' => ['name' => 'Bread', 'group' => 'lifestyle', 'keywords' => 'bread baking sourdough food loaf'],
        '✈️' => ['name' => 'Airplane', 'group' => 'lifestyle', 'keywords' => 'travel trip flight holiday abroad'],
        '🌍' => ['name' => 'Globe', 'group' => 'lifestyle', 'keywords' => 'world global travel earth international'],
        '🏡' => ['name' => 'House with garden', 'group' => 'lifestyle', 'keywords' => 'home house remote lifestyle interior'],
        '💻' => ['name' => 'Laptop', 'group' => 'work', 'keywords' => 'work laptop remote coding freelance desk'],
        '🖥️' => ['name' => 'Desktop computer', 'group' => 'work', 'keywords' => 'setup desk computer workstation'],
        '⌨️' => ['name' => 'Keyboard', 'group' => 'work', 'keywords' => 'typing writing setup keyboard'],
        '🛠️' => ['name' => 'Hammer and wrench', 'group' => 'work', 'keywords' => 'tools build fix diy workflow'],
        '⚙️' => ['name' => 'Gear', 'group' => 'work', 'keywords' => 'settings system automation process config'],
        '🧩' => ['name' => 'Puzzle piece', 'group' => 'work', 'keywords' => 'piece fit strategy problem solve'],
        '📦' => ['name' => 'Package', 'group' => 'work', 'keywords' => 'product shipping bundle delivery ecommerce'],
        '🛒' => ['name' => 'Shopping cart', 'group' => 'money', 'keywords' => 'buy shop ecommerce cart checkout'],
        '🏷️' => ['name' => 'Label', 'group' => 'money', 'keywords' => 'price tag discount deal sale'],
        '📢' => ['name' => 'Loudspeaker', 'group' => 'cta', 'keywords' => 'announcement news shout launch promo'],
        '🤝' => ['name' => 'Handshake', 'group' => 'work', 'keywords' => 'partnership collab deal sponsor brand'],
        '🙏' => ['name' => 'Folded hands', 'group' => 'reaction', 'keywords' => 'thanks please grateful appreciation'],
        '🫡' => ['name' => 'Saluting face', 'group' => 'reaction', 'keywords' => 'respect got it yes sir acknowledge'],
        '🤔' => ['name' => 'Thinking face', 'group' => 'reaction', 'keywords' => 'question think hmm curious wonder'],
        '😴' => ['name' => 'Sleeping face', 'group' => 'reaction', 'keywords' => 'boring tired sleep dull'],
        '🥱' => ['name' => 'Yawning face', 'group' => 'reaction', 'keywords' => 'boring tired yawn dull'],
        '🫠' => ['name' => 'Melting face', 'group' => 'reaction', 'keywords' => 'overwhelmed burnout awkward hot'],
        '🐐' => ['name' => 'Goat', 'group' => 'reaction', 'keywords' => 'goat best greatest legend'],
        '🌱' => ['name' => 'Seedling', 'group' => 'growth', 'keywords' => 'growth start small beginner new grow'],
        '🌈' => ['name' => 'Rainbow', 'group' => 'lifestyle', 'keywords' => 'colour pride hope aesthetic bright'],
        '☀️' => ['name' => 'Sun', 'group' => 'lifestyle', 'keywords' => 'sun morning summer bright weather'],
        '🌙' => ['name' => 'Crescent moon', 'group' => 'lifestyle', 'keywords' => 'night evening sleep late calm'],
        '🔮' => ['name' => 'Crystal ball', 'group' => 'strategy', 'keywords' => 'predict future forecast trends'],
        '🕵️' => ['name' => 'Detective', 'group' => 'strategy', 'keywords' => 'research investigate competitor audit spy'],
        '📣' => ['name' => 'Megaphone', 'group' => 'cta', 'keywords' => 'announce promote shout marketing'],
        '💎' => ['name' => 'Gem stone', 'group' => 'strategy', 'keywords' => 'premium value quality gem rare'],
        '🧪' => ['name' => 'Test tube', 'group' => 'strategy', 'keywords' => 'test experiment ab try science'],
        '🔍' => ['name' => 'Magnifying glass', 'group' => 'strategy', 'keywords' => 'search seo find research look'],
        '🗣️' => ['name' => 'Speaking head', 'group' => 'cta', 'keywords' => 'talk speak word of mouth opinion'],
    ];

    public static function key(): string
    {
        return 'content.emoji-picker';
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
            'required' => [],
            'additionalProperties' => false,
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'title' => 'Search',
                    'description' => 'Search by meaning — “growth”, “money”, “comment”, “boring”. Leave empty to browse.',
                    'maxLength' => 60,
                    'default' => '',
                    'examples' => ['growth'],
                ],
                'group' => [
                    'type' => 'string',
                    'title' => 'Group',
                    'enum' => ['', 'reaction', 'growth', 'money', 'strategy', 'content', 'cta', 'video', 'list', 'work', 'lifestyle'],
                    'default' => '',
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $query = mb_strtolower(trim($input->string('query')));
        $group = $input->string('group');

        $rows = [];

        foreach (self::EMOJI as $emoji => $meta) {
            if ($group !== '' && $meta['group'] !== $group) {
                continue;
            }

            if ($query !== '' && ! str_contains($meta['keywords'].' '.mb_strtolower($meta['name']), $query)) {
                continue;
            }

            $rows[] = [
                'emoji' => $emoji,
                'name' => $meta['name'],
                'group' => $meta['group'],
                'keywords' => $meta['keywords'],
            ];
        }

        return ToolResult::table(
            columns: [
                ['key' => 'emoji', 'label' => 'Emoji'],
                ['key' => 'name', 'label' => 'Name'],
                ['key' => 'group', 'label' => 'Group'],
                ['key' => 'keywords', 'label' => 'Also matches'],
            ],
            rows: $rows,
            summary: $rows === []
                ? "Nothing matches “{$query}”. Try a broader word — “money”, “growth”, “video”."
                : count($rows).' emoji matched.',
        )->withWarnings($rows === [] ? [] : [
            'Two or three emoji per caption is the sweet spot. A wall of them reads as spam and '
            .'is painful on a screen reader.',
        ]);
    }
}
