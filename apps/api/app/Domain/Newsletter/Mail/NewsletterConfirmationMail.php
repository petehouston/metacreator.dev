<?php

declare(strict_types=1);

namespace App\Domain\Newsletter\Mail;

use App\Domain\Notifications\Notifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The double opt-in confirmation.
 *
 * Sent with a Mailable rather than through {@see Notifier}
 * on purpose: a subscriber is an email address, not a user, and the notifier's
 * preference and channel machinery is all keyed to accounts.
 */
final class NewsletterConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(private readonly string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirm your newsletter subscription');
    }

    public function content(): Content
    {
        $data = [
            'heading' => 'One click to confirm',
            'body' => 'Confirm this address and the weekly issue — new tools and creator '
                .'tactics — starts arriving. If you did not sign up, ignore this email and '
                .'nothing further will be sent.',
            'actionLabel' => 'Confirm subscription',
            'actionUrl' => rtrim((string) config('app.frontend_url'), '/')
                .'/newsletter/confirm?token='.$this->token,
            // The account footer does not apply: a subscriber may have no account, and
            // pointing them at notification preferences they cannot open is a dead end.
            'footerNote' => 'You are receiving this because this address was entered on '
                .'a MetaCreator.dev newsletter form.',
            'preferencesUrl' => null,
        ];

        return new Content(view: 'mail.event', text: 'mail.text.event', with: $data);
    }
}
