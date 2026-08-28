<?php

declare(strict_types=1);

namespace App\Domain\Tools\Enums;

/**
 * How the frontend should render a result.
 *
 * Every case maps to exactly one renderer component in
 * `apps/web/src/components/tools/results`. A tool that fits one of these needs no
 * frontend code at all — which is the entire point of the engine (see docs/08).
 */
enum ResultView: string
{
    /** Label/value pairs. Calculators, counters. */
    case KeyValue = 'keyvalue';

    /** Tabular rows with typed columns. Extractors, bulk checks. */
    case Table = 'table';

    /** Cards with a title, body and copy action. Idea and hashtag generators. */
    case ListCards = 'list.cards';

    /** Long-form text split into copyable blocks. Descriptions, captions, scripts. */
    case TextBlocks = 'text.blocks';

    /** Downloadable images or video. Downloaders, resizers, croppers. */
    case MediaGallery = 'media.gallery';

    /** Headline score plus weighted sub-scores and prioritised fixes. Audits. */
    case ScoreReport = 'score.report';

    /** One or more series over time. Growth trackers. */
    case ChartTimeseries = 'chart.timeseries';

    /** Two or more variants compared side by side. A/B testers. */
    case DiffCompare = 'diff.compare';

    /** A single archive to download. Bulk exports. */
    case DownloadBundle = 'download.bundle';

    /**
     * A mock-up of the post, profile, link card or pin as the platform draws it.
     * Preview tools: a table of fold positions tells you a post is cut, a picture of
     * the cut post tells you whether that matters.
     */
    case SocialPreview = 'preview.social';
}
