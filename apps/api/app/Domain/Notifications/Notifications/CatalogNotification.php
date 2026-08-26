<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Notifications;

use App\Domain\Notifications\NotificationEvent;
use App\Domain\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

/**
 * The one notification class. Every event in the catalog is delivered through it,
 * driven by the catalog entry rather than by a bespoke subclass.
 *
 * Forty notification classes that each differ only in their copy is forty places to
 * forget a queue setting or a plain-text alternative. The trade-off is that per-event
 * copy lives in the catalog as data — which is also what lets the preferences screen
 * and the admin previewer render an event without instantiating anything.
 */
final class CatalogNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Backoff matches docs/13: retry quickly, then back off, then dead-letter.
     *
     * @var list<int>
     */
    public array $backoff = [10, 60, 300];

    public int $tries = 4;

    /**
     * @param  list<string>  $channels  Already resolved against user preferences.
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        private readonly NotificationEvent $event,
        private readonly array $payload,
        private readonly array $channels,
        private readonly ?string $actionUrl = null,
    ) {
        $this->onQueue('mail');
    }

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return $this->channels;
    }

    public function toMail(User $notifiable): Mailable
    {
        // A Mailable returned from a notification addresses itself — the mail channel
        // hands it straight to the mailer and does not add a recipient for it.
        return (new EventMail(
            event: $this->event,
            payload: $this->payload,
            subjectLine: $this->title(),
            bodyText: $this->body(),
            actionUrl: $this->resolvedActionUrl(),
        ))->to($notifiable->routeNotificationFor('mail', $this));
    }

    /** @return array<string, mixed> */
    public function toDatabase(User $notifiable): array
    {
        return [
            'event' => $this->event->key,
            'group' => $this->event->group,
            'icon' => $this->event->icon,
            'title' => $this->title(),
            'body' => $this->body(),
            'action_label' => $this->event->action['label'] ?? null,
            'action_url' => $this->resolvedActionUrl(),
        ];
    }

    private function title(): string
    {
        return $this->event->render($this->event->title, $this->payload);
    }

    private function body(): string
    {
        return $this->event->render($this->event->body, $this->payload);
    }

    /**
     * Catalog URLs are frontend-relative so the same entry works across environments;
     * an explicit override (a signed link, a specific ticket) wins.
     */
    private function resolvedActionUrl(): ?string
    {
        if ($this->actionUrl !== null) {
            return $this->actionUrl;
        }

        $path = $this->event->action['url'] ?? null;

        if ($path === null) {
            return null;
        }

        return rtrim((string) config('app.frontend_url'), '/').'/'.ltrim($path, '/');
    }
}
