<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The message the settings screen's "Send test" sends.
 *
 * It renders through the real layout rather than a bare string, because half of
 * what a test send proves is presentational — that the template survives the
 * recipient's client, that images and the button render, that the plain-text
 * alternative exists. A test that only proved the credentials authenticate would
 * still let a broken layout reach a customer.
 */
final class TestMail extends Mailable
{
    public function __construct(
        private readonly string $providerLabel,
        private readonly string $sentBy,
        private readonly string $sentAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Test email from '.config('app.name'));
    }

    public function content(): Content
    {
        $data = [
            'heading' => 'Your mail settings work',
            'body' => 'This message was sent through '.$this->providerLabel.' by '.$this->sentBy
                .' at '.$this->sentAt.' to confirm the transactional email configuration. '
                .'If it reached you — in the inbox rather than the spam folder — password resets, '
                .'receipts and ticket replies will reach your users the same way.',
            'actionLabel' => null,
            'actionUrl' => null,
            'payload' => [],
        ];

        return new Content(view: 'mail.event', text: 'mail.text.event', with: $data);
    }
}
