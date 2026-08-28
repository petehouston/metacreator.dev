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
        Runners\AspectRatioCalculatorRunner::class,
        Runners\CarouselSplitterRunner::class,
        Runners\CharacterCounterRunner::class,
        Runners\ColorPaletteExtractorRunner::class,
        Runners\CtaGeneratorRunner::class,
        Runners\EmojiPickerRunner::class,
        Runners\EngagementRateCalculatorRunner::class,
        Runners\FacebookAdTextCounterRunner::class,
        Runners\FacebookPostPreviewRunner::class,
        Runners\FancyTextGeneratorRunner::class,
        Runners\FollowerMilestoneCountdownRunner::class,
        Runners\GiveawayWinnerPickerRunner::class,
        Runners\HandleStrengthRunner::class,
        Runners\HashtagGeneratorRunner::class,
        Runners\HeadlineAnalyzerRunner::class,
        Runners\ImageCompressorRunner::class,
        Runners\ImageFormatConverterRunner::class,
        Runners\InstagramBioPreviewRunner::class,
        Runners\LinkedInPostPreviewRunner::class,
        Runners\MetadataPreviewRunner::class,
        Runners\PinImageSizerRunner::class,
        Runners\PinterestPinPreviewRunner::class,
        Runners\PinterestPinSeoCheckerRunner::class,
        Runners\PostingTimezoneConverterRunner::class,
        Runners\QrCodeGeneratorRunner::class,
        Runners\ReadabilityCheckerRunner::class,
        Runners\ReelsCoverCropperRunner::class,
        Runners\RichPinValidatorRunner::class,
        Runners\SafeZoneGuideRunner::class,
        Runners\ScriptTimerRunner::class,
        Runners\SocialImageResizerRunner::class,
        Runners\StoryTemplateSizerRunner::class,
        Runners\TextCaseConverterRunner::class,
        Runners\ThreadsBioPreviewRunner::class,
        Runners\ThreadSplitterRunner::class,
        Runners\ThreadsPostPreviewRunner::class,
        Runners\TikTokMoneyCalculatorRunner::class,
        Runners\TweetScreenshotRunner::class,
        Runners\UsernameAvailabilityRunner::class,
        Runners\UtmBuilderRunner::class,
        Runners\WordCounterRunner::class,
        Runners\YouTubeChannelDescriptionGeneratorRunner::class,
        Runners\YouTubeChannelIdFinderRunner::class,
        Runners\YouTubeChannelMonetizationCheckerRunner::class,
        Runners\YouTubeCitationGeneratorRunner::class,
        Runners\YouTubeCommentFinderRunner::class,
        Runners\YouTubeContentCalendarRunner::class,
        Runners\YouTubeEmbedCodeGeneratorRunner::class,
        Runners\YouTubeHandleAvailabilityRunner::class,
        Runners\YouTubeImageDownloaderRunner::class,
        Runners\YouTubeMetadataViewerRunner::class,
        Runners\YouTubeMoneyCalculatorRunner::class,
        Runners\YouTubePartnerProgramCheckerRunner::class,
        Runners\YouTubeRssFeedGeneratorRunner::class,
        Runners\YouTubeSearchSuggestRunner::class,
        Runners\YouTubeShadowbanDetectorRunner::class,
        Runners\YouTubeSubscribeLinkGeneratorRunner::class,
        Runners\YouTubeTagExtractorRunner::class,
        Runners\YouTubeThumbnailDownloaderRunner::class,
        Runners\YouTubeTimestampLinkBuilderRunner::class,
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
