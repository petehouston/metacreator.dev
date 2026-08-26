<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiCollection;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The bell menu and the full history page.
 *
 * Reads are marked in batches (docs/13) — a busy user scrolling the panel should not
 * generate one request per row.
 */
final class NotificationController extends Controller
{
    /** @return ApiCollection<NotificationResource> */
    public function index(Request $request): ApiCollection
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $request->validate([
            'filter.group' => ['sometimes', 'string', 'max:40'],
            'filter.unread' => ['sometimes', 'boolean'],
        ]);

        $notifications = $user->notifications()
            ->when(
                $request->input('filter.group'),
                fn ($query, string $group) => $query->where('data->group', $group),
            )
            ->when(
                $request->boolean('filter.unread'),
                fn ($query) => $query->whereNull('read_at'),
            )
            ->paginate(perPage: min((int) $request->integer('per_page', 20), 100));

        return (new ApiCollection($notifications, NotificationResource::class))
            ->additional(['meta' => ['unread' => $user->unreadNotifications()->count()]]);
    }

    public function read(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'max:200'],
            'ids.*' => ['required', 'uuid'],
        ]);

        $marked = $user->unreadNotifications()
            ->whereIn('id', $validated['ids'])
            ->update(['read_at' => now()]);

        return response()->json([
            'data' => ['marked' => $marked, 'unread' => $user->unreadNotifications()->count()],
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $marked = $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['data' => ['marked' => $marked, 'unread' => 0]]);
    }
}
