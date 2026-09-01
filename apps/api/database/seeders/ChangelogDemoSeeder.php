<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Changelog\Enums\ChangeType;
use App\Domain\Changelog\Enums\ReleaseStatus;
use App\Domain\Changelog\Models\ChangelogRelease;
use App\Domain\Users\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * A year of demo releases, plus one draft and one scheduled entry so the admin's
 * status tabs and the public scope can be checked by eye as well as by test.
 *
 * Local only — see {@see DatabaseSeeder}.
 */
final class ChangelogDemoSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::query()->where('email', 'editor@metacreator.dev')->first()
            ?? User::query()->first();

        if ($author === null) {
            $this->command->warn('No users to attribute releases to — skipping changelog demo data.');

            return;
        }

        foreach ($this->releases() as $definition) {
            $release = ChangelogRelease::query()->firstOrNew(['slug' => $definition['slug']]);

            $release->fill([
                'version' => $definition['version'],
                'title' => $definition['title'],
                'summary' => $definition['summary'],
                'status' => $definition['status'],
                'released_at' => $definition['released_at'],
                'is_major' => $definition['is_major'] ?? false,
                'author_id' => $author->id,
            ])->save();

            $release->items()->delete();

            foreach (array_values($definition['items']) as $index => [$type, $title, $description]) {
                $release->items()->create([
                    'type' => $type,
                    'title' => $title,
                    'description' => $description,
                    'sort_order' => $index,
                ]);
            }
        }

        $this->command->info('Seeded '.count($this->releases()).' demo releases.');
    }

    /**
     * Dates are relative to "now" so the timeline never looks abandoned on a
     * checkout made a year after this file was written.
     *
     * @return list<array<string, mixed>>
     */
    private function releases(): array
    {
        $now = Carbon::now();

        return [
            [
                'slug' => 'v3-0-0',
                'version' => '3.0.0',
                'title' => 'Saved tools, run history and a faster catalog',
                'summary' => 'The largest release this year. Every tool run is now kept against your account, the catalog got roughly twice as fast, and you can pin the tools you use daily.',
                'status' => ReleaseStatus::Published,
                'released_at' => $now->copy()->subDays(6),
                'is_major' => true,
                'items' => [
                    [ChangeType::Added, 'Saved tools', 'Pin any tool from its page or the catalog. Saved tools appear first in your dashboard and sync across devices.'],
                    [ChangeType::Added, 'Run history', 'Every run is kept for 90 days with its inputs and outputs, so you can re-open a result instead of re-running it.'],
                    [ChangeType::Added, 'Export results as CSV', 'Available on every analysis tool, from the result panel.'],
                    [ChangeType::Improved, 'Catalog loads about twice as fast', 'The tool list is now cached at the edge and paginated server-side. First paint went from 1.4s to 0.6s at the median.'],
                    [ChangeType::Improved, 'Clearer quota messages', 'Hitting a daily limit now tells you when it resets and what a plan would raise it to, instead of a generic error.'],
                    [ChangeType::Fixed, 'Tag suggestions dropped non-Latin characters', 'Titles in Japanese, Korean, Thai and Arabic produced empty suggestions. They no longer do.'],
                    [ChangeType::Fixed, 'Dark mode flashed white on first load', null],
                ],
            ],
            [
                'slug' => 'v2-8-0',
                'version' => '2.8.0',
                'title' => 'Two new YouTube tools',
                'summary' => null,
                'status' => ReleaseStatus::Published,
                'released_at' => $now->copy()->subDays(24),
                'items' => [
                    [ChangeType::Added, 'YouTube thumbnail analyzer', 'Scores contrast, face size and text legibility at the size a thumbnail is actually viewed.'],
                    [ChangeType::Added, 'Chapter generator', 'Turns a transcript into timestamped chapters you can paste straight into a description.'],
                    [ChangeType::Improved, 'Hashtag generator now ranks by recent reach', 'Rankings previously used all-time volume, which surfaced tags that peaked years ago.'],
                    [ChangeType::Fixed, 'Runs occasionally timed out at exactly 30 seconds', 'A queue worker was recycling mid-run. It now drains cleanly.'],
                ],
            ],
            [
                'slug' => 'v2-7-2',
                'version' => '2.7.2',
                'title' => 'Security and reliability',
                'summary' => null,
                'status' => ReleaseStatus::Published,
                'released_at' => $now->copy()->subDays(41),
                'items' => [
                    [ChangeType::Security, 'Session cookies are now rotated on privilege change', 'Signing in, changing a password or having a role changed issues a fresh session. Reported responsibly; no accounts were affected.'],
                    [ChangeType::Security, 'Tightened the content security policy', 'Inline scripts are gone from every page.'],
                    [ChangeType::Fixed, 'Password reset links expired an hour early', 'The stated hour was being applied twice.'],
                    [ChangeType::Deprecated, 'The v1 API stops receiving fixes on 1 March', 'Everything in it exists in v1 of the current API. See the migration notes in the docs.'],
                ],
            ],
            [
                'slug' => 'v2-7-0',
                'version' => '2.7.0',
                'title' => 'Accounts, billing and a rebuilt dashboard',
                'summary' => 'Plans, invoices and a dashboard that finally tells you what you have used this month.',
                'status' => ReleaseStatus::Published,
                'released_at' => $now->copy()->subDays(78),
                'is_major' => true,
                'items' => [
                    [ChangeType::Added, 'Subscriptions and invoices', 'Upgrade, downgrade and download every invoice from your billing page.'],
                    [ChangeType::Added, 'Usage on the dashboard', 'Runs used against your quota, by tool, for the current period.'],
                    [ChangeType::Added, 'Sign in with a magic link', 'No password needed. The link is single-use and expires in fifteen minutes.'],
                    [ChangeType::Improved, 'Rebuilt the dashboard around what you actually opened last', null],
                    [ChangeType::Removed, 'The old usage-meter widget', 'Replaced by the usage panel above, which reports the same numbers accurately.'],
                ],
            ],
            [
                'slug' => 'february-maintenance',
                'version' => null,
                'title' => 'Maintenance and small fixes',
                'summary' => null,
                'status' => ReleaseStatus::Published,
                'released_at' => $now->copy()->subDays(110),
                'items' => [
                    [ChangeType::Fixed, 'Empty results showed a spinner forever', null],
                    [ChangeType::Fixed, 'The catalog filter reset when you used the back button', null],
                    [ChangeType::Improved, 'Keyboard navigation across the whole catalog', 'Every card, filter and pagination control is reachable and visibly focused.'],
                ],
            ],

            // The two rows that make the admin's status tabs meaningful.
            [
                'slug' => 'v3-1-0',
                'version' => '3.1.0',
                'title' => 'Team workspaces',
                'summary' => 'Shared workspaces, seat-based billing and per-member run history.',
                'status' => ReleaseStatus::Scheduled,
                'released_at' => $now->copy()->addDays(9),
                'items' => [
                    [ChangeType::Added, 'Workspaces', 'Invite your team, share saved tools and see everyone’s runs in one history.'],
                    [ChangeType::Added, 'Seat-based billing', null],
                ],
            ],
            [
                'slug' => 'api-keys',
                'version' => null,
                'title' => 'Personal API keys',
                'summary' => 'Still being written up.',
                'status' => ReleaseStatus::Draft,
                'released_at' => null,
                'items' => [
                    [ChangeType::Added, 'Generate and revoke API keys from your account', null],
                ],
            ],
        ];
    }
}
