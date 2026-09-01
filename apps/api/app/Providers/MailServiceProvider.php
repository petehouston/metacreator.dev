<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Notifications\Mail\MailConfigurator;
use App\Domain\Notifications\Mail\Transports\KlaviyoTransport;
use App\Domain\Notifications\Mail\Transports\MailgunTransport;
use App\Domain\Notifications\Mail\Transports\PostmarkTransport;
use App\Domain\Notifications\Mail\Transports\ResendTransport;
use App\Domain\Settings\Settings;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Outbound mail: the admin-configured transport, and the providers Laravel does
 * not ship a driver for.
 *
 * Everything hangs off `resolving('mail.manager')` rather than `boot()` on purpose.
 * Applying the settings reads the settings cache, and the overwhelming majority of
 * requests this application serves never send an email — a tool run, a blog page, a
 * catalog listing. Deferring to the first time something actually asks for a mailer
 * keeps that work off every other request, and the callback still runs before the
 * manager is handed out, so no caller can observe the unconfigured state.
 */
final class MailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->resolving('mail.manager', function (MailManager $manager): void {
            $this->app->make(MailConfigurator::class)->apply();

            $manager->extend('mailgun', fn (array $config): MailgunTransport => new MailgunTransport(
                domain: (string) ($config['domain'] ?? ''),
                secret: (string) ($config['secret'] ?? ''),
                endpoint: (string) ($config['endpoint'] ?? 'api.mailgun.net'),
            ));

            $manager->extend('klaviyo', fn (array $config): KlaviyoTransport => new KlaviyoTransport(
                apiKey: (string) ($config['api_key'] ?? ''),
                metric: (string) ($config['metric'] ?? 'Transactional Email'),
            ));

            // Postmark and Resend are named in Laravel's default config but their
            // drivers live in packages this application does not install, so the
            // manager would throw on either without these.
            $manager->extend('postmark', fn (array $config): PostmarkTransport => new PostmarkTransport(
                token: (string) ($config['token'] ?? ''),
                messageStream: (string) ($config['message_stream_id'] ?? 'outbound'),
            ));

            $manager->extend('resend', fn (array $config): ResendTransport => new ResendTransport(
                key: (string) ($config['key'] ?? ''),
            ));
        });
    }

    public function boot(): void
    {
        $this->configureReplyTo();
    }

    /**
     * One reply-to address for everything the application sends.
     *
     * Laravel has a global From but no global Reply-To, and the two are genuinely
     * different: From is the sending domain — which has to stay on the domain that
     * holds the SPF and DKIM records, or delivery suffers — while Reply-To is where
     * a human answering the email should land. Without this, replies go to
     * `no-reply@` and quietly disappear.
     *
     * Set on the message rather than in config so a mailable that names its own
     * reply-to keeps it.
     */
    private function configureReplyTo(): void
    {
        Event::listen(function (MessageSending $event): void {
            if ($event->message->getReplyTo() !== []) {
                return;
            }

            $replyTo = $this->app->make(Settings::class)->string('mail.reply_to_address');

            if ($replyTo !== '') {
                $event->message->replyTo($replyTo);
            }
        });
    }
}
