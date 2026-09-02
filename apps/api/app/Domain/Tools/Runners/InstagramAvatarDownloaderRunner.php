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
use App\Support\Social\PageMeta;
use App\Support\Social\SocialUrl;

/**
 * An Instagram profile picture at full size, which the app itself will not give you.
 *
 * Instagram renders the avatar at 150 pixels in the web profile and smaller again
 * in the app, and there is no "view profile photo" anywhere in either. Everybody
 * who needs one — for a podcast guest card, a collaboration deck, a press page, a
 * "who is this account" check — ends up screenshotting a 150px circle and upscaling
 * it. The larger copy is published in the profile's own `og:image` tag, because
 * that is what renders when somebody shares the profile link.
 *
 * Only public profiles, and only the tag the page already publishes for link cards
 * (docs/08). A private account, or a page Instagram decides to answer with a
 * sign-in wall, is reported as such — there is no logged-in session here and the
 * tool will not pretend there is one.
 */
final class InstagramAvatarDownloaderRunner implements Cacheable, ToolRunner, UsesProvider
{
    /** Reserved paths that are not usernames, so a pasted deep link fails clearly. */
    private const RESERVED = ['p', 'reel', 'reels', 'tv', 'stories', 'explore', 'accounts', 'direct'];

    public static function key(): string
    {
        return 'instagram.avatar-downloader';
    }

    public function providers(): array
    {
        return ['instagram'];
    }

    public function cacheTtl(): int
    {
        // Meta signs its CDN URLs and expires them within hours, so a long cache
        // would serve links that 403 by the time somebody clicked them.
        return 900;
    }

    public function inputSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'required' => ['username'],
            'additionalProperties' => false,
            'properties' => [
                'username' => [
                    'type' => 'string',
                    'x-control' => 'text',
                    'title' => 'Instagram username or profile URL',
                    'description' => 'With or without the @, or the whole instagram.com link.',
                    'minLength' => 1,
                    'maxLength' => 300,
                    'examples' => ['@instagram'],
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $username = $this->username(trim($input->string('username')));
        $profileUrl = "https://www.instagram.com/{$username}/";

        $page = PageMeta::fetch($profileUrl);
        $avatar = $page->image();

        if ($avatar === null || $page->isLoginWall()) {
            throw ToolExecutionException::notFound(
                "a public profile picture for @{$username}. Either the account does not exist, it is "
                .'private, or Instagram answered with a sign-in page — which it does intermittently even '
                .'for public profiles. Opening the profile in a browser will tell you which',
            );
        }

        $title = $page->title();
        $displayName = $title === null ? null : trim((string) preg_replace('/\s*\(@.*$/u', '', $title));

        $rows = [
            ['size' => 'Full size — as Instagram publishes it', 'use' => 'Press pages, decks, guest cards',
                'url' => $avatar],
        ];

        return ToolResult::table(
            columns: [
                ['key' => 'size', 'label' => 'Version'],
                ['key' => 'use', 'label' => 'Good for'],
                ['key' => 'url', 'label' => 'Download', 'align' => 'right', 'type' => 'download'],
            ],
            rows: $rows,
            summary: 'Profile picture for @'.$username
                .($displayName !== null && $displayName !== '' ? ' — '.$displayName : '')
                .', at the largest size Instagram publishes.',
        )->withMeta([
            'username' => $username,
            'profile_url' => $profileUrl,
            'display_name' => $displayName,
            'avatar_url' => $avatar,
            'preview_url' => $avatar,
        ])->withWarnings([
            'A profile picture belongs to the person in it. Use it to identify an account — a guest card, '
            .'a credit, a collaboration deck — not as stock imagery, and not to impersonate anyone.',
            'Instagram signs this URL and expires it, usually within a few hours. Save the file now rather '
            .'than bookmarking the link.',
            'Instagram publishes one size to link cards, so there is no larger version to ask for. If it '
            .'looks soft, that is the resolution the account uploaded.',
        ]);
    }

    /** Accept `@name`, `name`, or any instagram.com URL, and reject everything else clearly. */
    private function username(string $input): string
    {
        $candidate = ltrim($input, '@');

        if (SocialUrl::host($input) !== null && str_contains($input, '/')) {
            if (SocialUrl::platform($input) !== 'instagram') {
                throw ToolExecutionException::invalidInput(
                    'That is not an Instagram link.',
                    ['username' => 'Expected an instagram.com profile URL, or just the username.'],
                );
            }

            $path = trim((string) (parse_url(SocialUrl::normalise($input), PHP_URL_PATH) ?: ''), '/');
            $candidate = explode('/', $path)[0];
        }

        $candidate = ltrim($candidate, '@');

        if (in_array(mb_strtolower($candidate), self::RESERVED, true)) {
            throw ToolExecutionException::invalidInput(
                'That link points at a post, not a profile. Paste the profile URL or the username.',
                ['username' => 'Expected a profile, not a post.'],
            );
        }

        // Instagram usernames: letters, digits, periods and underscores, up to 30.
        if (preg_match('/^[A-Za-z0-9._]{1,30}$/', $candidate) !== 1) {
            throw ToolExecutionException::invalidInput(
                'That is not a valid Instagram username.',
                ['username' => 'Letters, numbers, periods and underscores only.'],
            );
        }

        return $candidate;
    }
}
