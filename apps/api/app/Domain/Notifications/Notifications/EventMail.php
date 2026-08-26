<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Notifications;

use App\Domain\Notifications\NotificationEvent;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Renders a catalog event as email.
 *
 * The catalog names the template, so an event that needs a bespoke layout (a
 * receipt, a welcome) gets one without any other event changing. Everything else
 * falls to `mail.event`, which is the same shell with a heading, body and CTA.
 *
 * A plain-text alternative is always generated — docs/13 calls that out explicitly,
 * because a text/plain part is worth real deliverability points.
 */
final class EventMail extends Mailable
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public readonly NotificationEvent $event,
        public readonly array $payload,
        // Named to avoid Mailable's own `$subject` and `$view` properties — a
        // promoted readonly property that shadows a parent's is a fatal error at
        // class-load time, not a warning.
        public readonly string $subjectLine,
        public readonly string $bodyText,
        public readonly ?string $actionUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        $data = [
            'heading' => $this->subjectLine,
            'body' => $this->bodyText,
            'actionLabel' => $this->event->action['label'] ?? null,
            'actionUrl' => $this->actionUrl,
            'payload' => $this->payload,
        ];

        return new Content(
            view: $this->event->template,
            text: 'mail.text.event',
            with: $data,
        );
    }
}
