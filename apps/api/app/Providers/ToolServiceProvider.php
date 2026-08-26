<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Runners;
use App\Domain\Tools\ToolRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the tool registry.
 *
 * Adding a tool means adding one line to {@see self::RUNNERS} and one catalog row —
 * no routes, no controller, no frontend component (see docs/08).
 */
final class ToolServiceProvider extends ServiceProvider
{
    /** @var list<class-string<ToolRunner>> */
    private const RUNNERS = [
        Runners\CharacterCounterRunner::class,
        Runners\EngagementRateCalculatorRunner::class,
        Runners\GiveawayWinnerPickerRunner::class,
        Runners\HashtagGeneratorRunner::class,
        Runners\HeadlineAnalyzerRunner::class,
        Runners\ThreadSplitterRunner::class,
        Runners\UtmBuilderRunner::class,
        Runners\YouTubeThumbnailDownloaderRunner::class,
    ];

    public function register(): void
    {
        $this->app->singleton(ToolRegistry::class, function ($app): ToolRegistry {
            $registry = new ToolRegistry($app);
            $registry->registerMany(self::RUNNERS);

            return $registry;
        });
    }

    /** @return list<class-string<ToolRunner>> */
    public static function runners(): array
    {
        return self::RUNNERS;
    }
}
