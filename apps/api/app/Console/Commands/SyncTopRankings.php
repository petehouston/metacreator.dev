<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\TopRanking\Actions\SyncRankingAvatars;
use App\Domain\TopRanking\Actions\SyncRankingPageFromWikipedia;
use App\Domain\TopRanking\Jobs\RefreshTopRankingPage;
use App\Domain\TopRanking\Models\TopRankingPage;
use Illuminate\Console\Command;

/**
 * Re-reads the Wikipedia article behind one ranking page, or all of them.
 *
 *     php artisan rankings:sync most-followed-tiktok-accounts
 *     php artisan rankings:sync --all
 *     php artisan rankings:sync --all --queue          # hand each page to a worker
 *     php artisan rankings:sync --all --with-avatars   # and resolve pictures too
 *
 * Safe to re-run: the sync reconciles rather than replaces, so running it twice in
 * a row is a no-op on the second pass. Rows an editor added or pinned survive every
 * run — see {@see SyncRankingPageFromWikipedia} for the rules.
 *
 * `--queue` is what the weekly schedule uses. Run inline (the default) when you
 * want to watch it, which is every time you have just changed `source_page` or
 * `source_table` and need to know whether the new setting parses.
 */
final class SyncTopRankings extends Command
{
    protected $signature = 'rankings:sync
        {slug?* : One or more page slugs. Omit with --all for every page}
        {--all : Every ranking page, published or not}
        {--queue : Dispatch to the maintenance queue instead of running now}
        {--with-avatars : Also resolve missing avatars (implies a much longer run)}';

    protected $description = 'Refresh top ranking pages from their Wikipedia source';

    public function handle(SyncRankingPageFromWikipedia $sync, SyncRankingAvatars $avatars): int
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
                ? 'There are no ranking pages. Run `php artisan db:seed --class=TopRankingSeeder` first.'
                : 'No ranking page matches: '.implode(', ', $slugs));

            return self::FAILURE;
        }

        if ($slugs !== [] && $pages->count() < count($slugs)) {
            $this->components->warn('Unknown slug(s): '.implode(', ', array_diff($slugs, $pages->pluck('slug')->all())));
        }

        if ($this->option('queue')) {
            foreach ($pages as $index => $page) {
                // Spread out rather than dispatched together: nine articles
                // requested in the same second is a burst Wikipedia has no reason to
                // welcome, and nothing here is time-sensitive to the minute.
                RefreshTopRankingPage::dispatch($page->id, (bool) $this->option('with-avatars'))
                    ->delay(now()->addSeconds($index * 30));
            }

            $this->components->info(sprintf('Queued %d page(s) on the maintenance queue.', $pages->count()));

            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($pages as $page) {
            $result = $sync->handle($page);

            if ($result->status->isProblem()) {
                $failed++;
            }

            $this->components->twoColumnDetail(
                $page->slug,
                match ($result->status->value) {
                    'ok' => '<fg=green>ok</> '.$result->summary(),
                    'partial' => '<fg=yellow>partial</> '.$result->summary(),
                    default => '<fg=red>failed</> '.$result->summary(),
                },
            );

            if ($this->option('with-avatars')) {
                $counts = $avatars->forPage($page);

                $this->components->twoColumnDetail(
                    '',
                    sprintf(
                        '<fg=gray>avatars: %d resolved, %d unavailable, %d already good</>',
                        $counts['resolved'],
                        $counts['unavailable'],
                        $counts['kept'] + $counts['skipped'],
                    ),
                );
            }
        }

        $this->newLine();
        $this->components->info(sprintf('%d page(s) synced, %d with problems.', $pages->count(), $failed));

        if (! $this->option('with-avatars')) {
            $this->line('  <fg=gray>Pictures were left alone. Run `rankings:avatars` for those.</>');
        }

        return $failed === $pages->count() ? self::FAILURE : self::SUCCESS;
    }
}
