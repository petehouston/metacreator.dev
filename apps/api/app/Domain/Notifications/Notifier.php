<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use App\Domain\Notifications\Models\EmailSuppression;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Notifications\Notifications\CatalogNotification;
use App\Domain\Users\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The only way a notification leaves the system.
 *
 * Call sites pass an event key and a payload; this resolves the channels against the
 * catalog, the user's preferences and the suppression list, then hands off to
 * Laravel's notification stack. Nothing here decides copy — that is the catalog's job.
 */
final class Notifier
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  string|null  $actionUrl  Overrides the catalog's CTA (a signed link, a specific record).
     */
    public function send(User $user, string $eventKey, array $payload = [], ?string $actionUrl = null): void
    {
        $event = EventCatalog::get($eventKey);
        $event->assertPayloadSatisfied($payload);

        $channels = $this->channelsFor($user, $event);

        if ($channels === []) {
            return;
        }

        try {
            $user->notify(new CatalogNotification($event, $payload, $channels, $actionUrl));
        } catch (Throwable $e) {
            // A notification is never the point of the request that triggered it.
            // When the queue is running this only pushes a job and cannot fail on
            // transport, but under `QUEUE_CONNECTION=sync` — local development, and
            // any environment misconfigured that way — an unreachable SMTP host would
            // otherwise turn a successful signup into a 500 for a user whose account
            // was already created. Log it loudly and let the request succeed.
            Log::error('Notification delivery failed', [
                'event' => $eventKey,
                'user' => $user->public_id,
                'channels' => $channels,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fan an event out to every staff member who can act on it.
     *
     * Staff alerts are addressed to a permission, not to a person: whoever can handle
     * tickets hears about tickets, and nobody has to remember to update a list when
     * someone joins or leaves.
     *
     * @param  array<string, mixed>  $payload
     */
    public function sendToStaff(string $permission, string $eventKey, array $payload = [], ?string $actionUrl = null): void
    {
        $this->staffWith($permission)->each(
            fn (User $staff) => $this->send($staff, $eventKey, $payload, $actionUrl)
        );
    }

    /**
     * Resolve the delivery channels for one user and one event.
     *
     * Order matters: the catalog is the ceiling, preferences can only subtract, and
     * suppression can only subtract further. A user cannot opt *into* a channel the
     * event does not declare, and cannot opt out of a security email at all.
     *
     * @return list<string>
     */
    public function channelsFor(User $user, NotificationEvent $event): array
    {
        $channels = $event->channels;

        if ($event->optOut) {
            $preference = NotificationPreference::query()
                ->where('user_id', $user->id)
                ->where('event_key', $event->key)
                ->first();

            if ($preference !== null) {
                if (! $preference->email) {
                    $channels = array_values(array_diff($channels, ['mail']));
                }

                if (! $preference->in_app) {
                    $channels = array_values(array_diff($channels, ['database']));
                }
            }
        }

        if (in_array('mail', $channels, true) && $this->mailIsUndeliverable($user)) {
            $channels = array_values(array_diff($channels, ['mail']));
        }

        return $channels;
    }

    /** @return Collection<int, User> */
    private function staffWith(string $permission): Collection
    {
        return User::query()
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->get()
            ->filter(fn (User $user) => $user->isStaff() && $user->can($permission))
            ->values();
    }

    private function mailIsUndeliverable(User $user): bool
    {
        if (EmailSuppression::suppresses($user->email)) {
            Log::info('Suppressed email skipped', ['user' => $user->public_id]);

            return true;
        }

        return false;
    }
}
