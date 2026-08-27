<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Analytics\Data\Period;
use App\Domain\Analytics\Services\OverviewMetrics;
use App\Domain\Analytics\Services\ToolAnalytics;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `/admin` in one request.
 *
 * One endpoint rather than six, because the overview screen is useless partially
 * loaded — a dashboard that fills in over four seconds is a dashboard people stop
 * trusting. The expensive parts are cached in the services behind it.
 */
final class OverviewController extends Controller
{
    public function __invoke(
        Request $request,
        OverviewMetrics $metrics,
        ToolAnalytics $tools,
    ): JsonResource {
        $period = Period::fromRequest($request->query('period'));

        $headline = $metrics->headline($period);

        return new JsonResource([
            'period' => $headline['period'],
            'periods' => Period::PRESETS,
            'metrics' => $headline['metrics'],
            'volume' => $tools->volumeSeries($period),
            'funnel' => $tools->funnel($period),
            'access_reasons' => $tools->accessReasonSplit($period),
            // Ten rows, not two hundred: the overview answers "what is happening",
            // and the tool analytics screen answers "why".
            'top_tools' => array_slice($tools->byTool($period)['rows'], 0, 8),
            'top_errors' => $tools->topErrors($period, 5),
        ]);
    }
}
