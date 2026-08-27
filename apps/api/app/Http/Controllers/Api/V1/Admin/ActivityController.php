<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Access\Services\AuditLogger;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ActivityResource;
use App\Http\Resources\ApiCollection;
use Illuminate\Http\Request;

/**
 * The audit trail.
 *
 * Read-only by construction — there is no route that edits or deletes an entry,
 * because a log an administrator can rewrite answers no question worth asking.
 */
final class ActivityController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @return ApiCollection<ActivityResource> */
    public function index(Request $request): ApiCollection
    {
        $request->validate([
            'filter.log' => ['sometimes', 'nullable', 'string', 'max:60'],
            'filter.event' => ['sometimes', 'nullable', 'string', 'max:60'],
        ]);

        $activities = $this->audit->feed(
            logName: $request->input('filter.log') ?: null,
            event: $request->input('filter.event') ?: null,
            perPage: min(100, $request->integer('per_page', 30)),
        );

        return new ApiCollection($activities, ActivityResource::class);
    }
}
