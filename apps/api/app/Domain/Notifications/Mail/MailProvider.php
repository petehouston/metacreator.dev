<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Mail;

use App\Domain\Settings\Settings;

/**
 * The transports an operator may select for outbound mail.
 *
 * The case value is what lands in the `mail.provider` setting and what
 * `config('mail.default')` is set to, so these names are also the mailer names in
 * config/mail.php. {@see requiredSettings()} is the one place that says what a
 * provider needs to work, and both the admin screen's readiness pill and the test
 * send read it — a provider cannot be "configured" by one and not the other.
 */
enum MailProvider: string
{
    case Smtp = 'smtp';
    case Mailgun = 'mailgun';
    case Postmark = 'postmark';
    case Resend = 'resend';
    case Ses = 'ses';
    case Klaviyo = 'klaviyo';
    case Sendmail = 'sendmail';
    case Log = 'log';

    public function label(): string
    {
        return match ($this) {
            self::Smtp => 'SMTP',
            self::Mailgun => 'Mailgun',
            self::Postmark => 'Postmark',
            self::Resend => 'Resend',
            self::Ses => 'Amazon SES',
            self::Klaviyo => 'Klaviyo (via flow)',
            self::Sendmail => 'Sendmail (local binary)',
            self::Log => 'Log only — nothing is delivered',
        };
    }

    /**
     * Settings that must be non-empty before this provider can send.
     *
     * `log` and `sendmail` need nothing: one writes to the log and the other to a
     * binary the host either has or does not.
     *
     * @return list<string>
     */
    public function requiredSettings(): array
    {
        return match ($this) {
            self::Smtp => ['mail.smtp.host', 'mail.smtp.port'],
            self::Mailgun => ['mail.mailgun.domain', 'mail.mailgun.secret'],
            self::Postmark => ['mail.postmark.token'],
            self::Resend => ['mail.resend.key'],
            self::Ses => ['mail.ses.key', 'mail.ses.secret', 'mail.ses.region'],
            self::Klaviyo => ['mail.klaviyo.api_key', 'mail.klaviyo.metric'],
            self::Sendmail, self::Log => [],
        };
    }

    /** Whether every credential this provider needs — and a From address — is present. */
    public function isConfigured(Settings $settings): bool
    {
        if ($settings->string('mail.from_address') === '') {
            return false;
        }

        foreach ($this->requiredSettings() as $key) {
            if (trim((string) $settings->get($key, '')) === '') {
                return false;
            }
        }

        return true;
    }

    public static function fromSettings(Settings $settings): self
    {
        return self::tryFrom($settings->string('mail.provider', 'smtp')) ?? self::Smtp;
    }
}
