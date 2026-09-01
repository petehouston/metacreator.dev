<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Mail;

use App\Domain\Settings\Settings;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Applies the stored mail settings over config/mail.php at runtime.
 *
 * Transactional email is the one integration whose breakage is invisible from the
 * outside — the site looks fine while nobody can reset a password — and until now
 * fixing it meant editing `.env` and redeploying. Holding the credentials in
 * settings puts that behind the same audited admin screen as every other provider,
 * and the secret ones are encrypted at rest and never returned to the browser.
 *
 * The env values stay the fallback rather than being replaced. A blank setting
 * means "not configured here", not "blank", so an operator who has not touched the
 * screen keeps whatever the deployment already gave them, and local development
 * keeps pointing at Mailpit without seeding anything.
 */
final class MailConfigurator
{
    public function __construct(
        private readonly Settings $settings,
        private readonly Config $config,
    ) {}

    /**
     * Never let a mail-config problem take down the request that triggered it.
     *
     * This runs the first time anything resolves the mailer, which includes contexts
     * where the settings table cannot be read at all — `migrate` on a fresh database,
     * a container booting before migrations. Falling back to the env configuration is
     * correct there, and louder failure would only turn a working deploy into a
     * broken one.
     */
    public function apply(): void
    {
        try {
            $this->applySettings();
        } catch (Throwable $e) {
            Log::warning('Mail settings could not be applied; falling back to the environment.', [
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function applySettings(): void
    {
        // `array` is the mailer that collects and never delivers, and nothing but a
        // harness selects it — tests/bootstrap.php pins it so no suite can send real
        // mail. It is not offered in the settings dropdown, so a stored provider
        // silently overriding it would mean a seeded row could put outbound mail on
        // the wire from a test run. The environment wins here.
        if ($this->config->get('mail.default') === 'array') {
            return;
        }

        $provider = MailProvider::fromSettings($this->settings);

        $this->config->set('mail.default', $provider->value);

        $this->set('mail.from.address', 'mail.from_address');
        $this->set('mail.from.name', 'mail.from_name');

        // SMTP
        $this->set('mail.mailers.smtp.host', 'mail.smtp.host');
        $this->set('mail.mailers.smtp.port', 'mail.smtp.port');
        $this->set('mail.mailers.smtp.username', 'mail.smtp.username');
        $this->set('mail.mailers.smtp.password', 'mail.smtp.password');

        // `scheme` is how Laravel 11+ decides TLS. An explicit `smtps` is implicit
        // TLS on 465; `smtp` is a plaintext connection upgraded by STARTTLS. Left
        // as "auto" the setting is not written at all, and Symfony infers it from
        // the port — which is right far more often than a wrong explicit choice.
        $scheme = $this->settings->string('mail.smtp.scheme', 'auto');

        if ($scheme !== 'auto' && $scheme !== '') {
            $this->config->set('mail.mailers.smtp.scheme', $scheme);
        }

        // Mailgun
        $this->set('mail.mailers.mailgun.domain', 'mail.mailgun.domain');
        $this->set('mail.mailers.mailgun.secret', 'mail.mailgun.secret');
        $this->set('mail.mailers.mailgun.endpoint', 'mail.mailgun.endpoint');

        // Postmark
        $this->set('mail.mailers.postmark.token', 'mail.postmark.token');
        $this->set('mail.mailers.postmark.message_stream_id', 'mail.postmark.message_stream');

        // Resend
        $this->set('mail.mailers.resend.key', 'mail.resend.key');

        // SES. Laravel's own transport reads `services.ses`, not the mailer entry.
        $this->set('services.ses.key', 'mail.ses.key');
        $this->set('services.ses.secret', 'mail.ses.secret');
        $this->set('services.ses.region', 'mail.ses.region');

        // Klaviyo
        $this->set('mail.mailers.klaviyo.api_key', 'mail.klaviyo.api_key');
        $this->set('mail.mailers.klaviyo.metric', 'mail.klaviyo.metric');
    }

    /** Write the setting over the config key, unless the setting is empty. */
    private function set(string $configKey, string $settingKey): void
    {
        $value = $this->settings->get($settingKey);

        if ($value === null || $value === '' || $value === []) {
            return;
        }

        $this->config->set($configKey, $value);
    }
}
