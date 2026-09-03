<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Seo\Actions\AttachToolSocialCard;
use App\Domain\Tools\Models\Tool;
use Illuminate\Console\Command;
use Throwable;

/**
 * Draws the 1200 × 630 social card for one tool, or for every tool.
 *
 *     php artisan tools:social-cards youtube-thumbnail-downloader
 *     php artisan tools:social-cards --all
 *     php artisan tools:social-cards --all --force        # redraw existing
 *     php artisan tools:social-cards --all --dry-run      # sizes only, writes nothing
 *
 * Idempotent by default: a tool that already has a generated card is left alone, so
 * this is safe to run on every deploy and only the tools added since last time cost
 * anything. `--force` redraws — that is the flag to use after the design changes or
 * a tool is renamed, since the card carries the name in the artwork.
 *
 * A card an admin picked by hand in *SEO & Sharing* is never overwritten without
 * `--force`; the report says so per tool rather than silently skipping.
 */
final class GenerateToolSocialCards extends Command
{
    protected $signature = 'tools:social-cards
        {slug?* : One or more tool slugs. Omit with --all for the whole catalog}
        {--all : Every published and draft tool}
        {--force : Redraw cards that already exist}
        {--dry-run : Render and report sizes without writing anything}
        {--site-url= : The address to draw in the URL bar (defaults to FRONTEND_URL)}';

    protected $description = 'Generate the open-graph social card for one tool or all tools';

    public function handle(AttachToolSocialCard $attach): int
    {
        // The card carries the site's address as artwork, so generating from a
        // laptop must not bake "localhost:3000" into what production shares.
        if (is_string($url = $this->option('site-url')) && $url !== '') {
            $attach = $attach->withSiteUrl($url);
        }

        /** @var list<string> $slugs */
        $slugs = (array) $this->argument('slug');
        $all = (bool) $this->option('all');

        if ($slugs === [] && ! $all) {
            $this->components->error('Name at least one slug, or pass --all.');

            return self::FAILURE;
        }

        $tools = Tool::query()
            ->when($slugs !== [], fn ($query) => $query->whereIn('slug', $slugs))
            ->orderBy('slug')
            ->get();

        if ($tools->isEmpty()) {
            $this->components->error($slugs === [] ? 'No tools in the catalog.' : 'No tool matches: '.implode(', ', $slugs));

            return self::FAILURE;
        }

        if ($slugs !== [] && $tools->count() < count($slugs)) {
            $missing = array_diff($slugs, $tools->pluck('slug')->all());
            $this->components->warn('Unknown slug(s): '.implode(', ', $missing));
        }

        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');
        $counts = ['generated' => 0, 'replaced' => 0, 'skipped' => 0, 'failed' => 0];
        $bytes = 0;
        $largest = 0;

        foreach ($tools as $tool) {
            try {
                $result = $attach->handle($tool, force: $force, dryRun: $dryRun);
            } catch (Throwable $e) {
                $counts['failed']++;
                $this->components->twoColumnDetail($tool->slug, '<fg=red>failed</> '.$e->getMessage());

                continue;
            }

            $counts[$result['status']]++;

            if (isset($result['bytes'])) {
                $bytes += $result['bytes'];
                $largest = max($largest, $result['bytes']);
            }

            $this->components->twoColumnDetail(
                $tool->slug,
                match ($result['status']) {
                    'skipped' => '<fg=gray>skipped</> '.($result['reason'] ?? ''),
                    default => sprintf(
                        '<fg=green>%s</> %s · %s',
                        $result['status'],
                        strtoupper((string) ($result['format'] ?? '')),
                        $this->humanBytes((int) ($result['bytes'] ?? 0)),
                    ),
                },
            );
        }

        $drawn = $counts['generated'] + $counts['replaced'];

        $this->newLine();
        $this->components->info(sprintf(
            '%d drawn (%d new, %d replaced), %d skipped, %d failed.',
            $drawn, $counts['generated'], $counts['replaced'], $counts['skipped'], $counts['failed'],
        ));

        if ($drawn > 0) {
            $this->line(sprintf(
                '  <fg=gray>1200 × 630 · %s average · %s largest%s</>',
                $this->humanBytes((int) round($bytes / $drawn)),
                $this->humanBytes($largest),
                $dryRun ? ' · nothing written' : '',
            ));
        }

        // A card over 300 KB is one a crawler may give up on, and a first fetch
        // that times out is cached as "no image" for days.
        if ($largest > 300 * 1024) {
            $this->components->warn('At least one card is over 300 KB — check the mock shapes.');
        }

        return $counts['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function humanBytes(int $bytes): string
    {
        return $bytes >= 1024 * 1024
            ? sprintf('%.1f MB', $bytes / 1024 / 1024)
            : sprintf('%d KB', (int) round($bytes / 1024));
    }
}
