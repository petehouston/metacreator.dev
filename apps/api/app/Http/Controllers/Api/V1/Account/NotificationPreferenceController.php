<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Domain\Notifications\EventCatalog;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Notifications\NotificationEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Preferences are presented by human group, never by raw event key (docs/13).
 *
 * Events with `optOut: false` are simply absent from this endpoint — a toggle that
 * cannot be honoured should not be rendered at all.
 */
final class NotificationPreferenceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $saved = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('event_key');

        $groups = [];

        foreach (EventCatalog::optionalByGroup() as $group => $events) {
            $groups[] = [
                'key' => $group,
                'label' => EventCatalog::GROUPS[$group] ?? ucfirst($group),
                'events' => array_map(function (NotificationEvent $event) use ($saved): array {
                    $preference = $saved->get($event->key);

                    return [
                        'key' => $event->key,
                        'title' => $event->title,
                        'channels' => [
                            // Absent row means "catalog default", which is what keeps
                            // adding an event a no-op for existing users.
                            'email' => $event->usesMail()
                                ? (bool) ($preference->email ?? true)
                                : null,
                            'in_app' => (bool) ($preference->in_app ?? true),
                        ],
                    ];
                }, $events),
            ];
        }

        return response()->json(['data' => $groups]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $optional = array_keys(array_filter(
            EventCatalog::all(),
            fn (NotificationEvent $event) => $event->optOut && ! $event->staffOnly,
        ));

        $validated = $request->validate([
            'preferences' => ['required', 'array', 'max:100'],
            'preferences.*.event_key' => ['required', 'string', Rule::in($optional)],
            'preferences.*.email' => ['required', 'boolean'],
            'preferences.*.in_app' => ['required', 'boolean'],
        ]);

        foreach ($validated['preferences'] as $preference) {
            NotificationPreference::query()->updateOrCreate(
                ['user_id' => $user->id, 'event_key' => $preference['event_key']],
                ['email' => $preference['email'], 'in_app' => $preference['in_app']],
            );
        }

        return $this->index($request);
    }
}
