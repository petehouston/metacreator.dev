<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Analytics\Data\Period;
use App\Domain\Analytics\Services\ToolAnalytics;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** The panels from docs/15 that drive roadmap and pricing decisions. */
final class ToolAnalyticsController extends Controller
{
    public function __construct(private readonly ToolAnalytics $analytics) {}

    public function index(Request $request): JsonResource
    {
        $request->validate([
            'tier' => ['sometimes', 'nullable', 'in:free,account,premium'],
            'category' => ['sometimes', 'nullable', 'string', 'max:120'],
            'sort' => ['sometimes', 'nullable', 'in:runs,failure_rate,paywall_hits,p95,unique_actors'],
        ]);

        $period = Period::fromRequest($request->query('period'));

        $result = $this->analytics->byTool($period, [
            'tier' => $this->stringOrNull($request->query('tier')),
            'category' => $this->stringOrNull($request->query('category')),
            'sort' => $this->stringOrNull($request->query('sort')),
        ]);

        return new JsonResource([
            'period' => $period->toArray(),
            'periods' => Period::PRESETS,
            'as_of' => $result['as_of'],
            'totals' => $result['totals'],
            'rows' => $result['rows'],
            'volume' => $this->analytics->volumeSeries($period),
            'access_reasons' => $this->analytics->accessReasonSplit($period),
            'top_errors' => $this->analytics->topErrors($period),
        ]);
    }

    /** Query parameters arrive as `string|array|null`; the service takes a string. */
    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    public function funnel(Request $request): JsonResource
    {
        $period = Period::fromRequest($request->query('period'));

        return new JsonResource([
            'period' => $period->toArray(),
            'periods' => Period::PRESETS,
            'steps' => $this->analytics->funnel($period),
        ]);
    }

    public function content(Request $request): JsonResource
    {
        $period = Period::fromRequest($request->query('period'));

        return new JsonResource([
            'period' => $period->toArray(),
            'periods' => Period::PRESETS,
            'rows' => $this->analytics->content($period),
        ]);
    }
}
