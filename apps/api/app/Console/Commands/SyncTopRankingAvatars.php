<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\TopRanking\Actions\SyncRankingAvatars;
use App\Domain\TopRanking\Models\TopRankingPage;
use Illuminate\Console\Command;
use Throwable;

/**
 * Resolves the account pictures on one ranking page, or on all of them.
 *
 *     php artisan rankings:avatars most-subscribed-youtube-channels
 *     php artisan rankings:avatars --all
 *     php artisan rankings:avatars --all --force    # re-check every row
 *
 * Incremental by default: a row whose picture is present and not near its expiry is
 * skipped, so a second run costs almost nothing and only the gaps are paid for.
 * `--force` re-checks everything — the flag for after a platform changes the shape
 * of its profile page and every stored link needs proving again.
 *
 * Expect misses. These are public pages read anonymously, and a platform is free to
 * answer with a login wall on any given day; a row that cannot be resolved renders
 * a monogram rather than a gap, and an editor can paste a URL in by hand. The
 * per-page tally below is the honest number, not a success rate to optimise.
 */
final class SyncTopRankingAvatars extends Command
{
    protected $signature = 'rankings:avatars
        {slug?* : One or more page slugs. Omit with --all for every page}
        {--all : Every ranking page, published or not}
        {--force : Re-check rows that already have a good picture}';

    protected $description = 'Resolve account avatars for top ranking pages';

    public function handle(SyncRankingAvatars $avatars): int
    {
        /** @var list<string> $slugs */
        $slugs = (array) $this->argument('slug');

        if ($slugs === [] && ! $this->option('all')) {
            $this->components->error('Name at least one slug, or pass --all.');

            return self::FAILURE;
        }

        $pages = TopRankingPage::query()
            ->when($slugs !== [], fn ($query) => $query->whereIn('slug', $slugs))
            ->inMenuOrder()
            ->get();

        if ($pages->isEmpty()) {
            $this->components->error($slugs === []
                ? 'There are no ranking pages yet.'
                : 'No ranking page matches: '.implode(', ', $slugs));

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $totals = ['resolved' => 0, 'kept' => 0, 'unavailable' => 0, 'skipped' => 0];

        foreach ($pages as $page) {
            try {
                $counts = $avatars->forPage($page, $force);
            } catch (Throwable $e) {
                $this->components->twoColumnDetail($page->slug, '<fg=red>failed</> '.$e->getMessage());

                continue;
            }

            foreach ($counts as $key => $value) {
                $totals[$key] += $value;
            }

            $this->components->twoColumnDetail($page->slug, sprintf(
                '<fg=green>%d resolved</> · <fg=yellow>%d unavailable</> · <fg=gray>%d kept, %d already good</>',
                $counts['resolved'],
                $counts['unavailable'],
                $counts['kept'],
                $counts['skipped'],
            ));
        }

        $this->newLine();
        $this->components->info(sprintf(
            '%d resolved, %d unavailable, %d kept, %d skipped across %d page(s).',
            $totals['resolved'],
            $totals['unavailable'],
            $totals['kept'],
            $totals['skipped'],
            $pages->count(),
        ));

        return self::SUCCESS;
    }
}
