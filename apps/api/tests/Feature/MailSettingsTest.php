<?php

declare(strict_types=1);

use App\Domain\Notifications\Mail\MailConfigurator;
use App\Domain\Notifications\Mail\MailProvider;
use App\Domain\Notifications\Mail\TestMail;
use App\Domain\Notifications\Mail\Transports\KlaviyoTransport;
use App\Domain\Notifications\Mail\Transports\MailgunTransport;
use App\Domain\Notifications\Mail\Transports\PostmarkTransport;
use App\Domain\Notifications\Mail\Transports\ResendTransport;
use App\Domain\Settings\Setting;
use App\Domain\Settings\Settings;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Email;

/**
 * Transactional mail configured from the database.
 *
 * The cases worth writing here are the ones where being wrong is silent. Mail that
 * does not send looks like nothing at all from the outside — the site is up, the
 * form submits, and the password reset never arrives — so the interesting
 * assertions are about the *seams*: that a saved secret is not readable back, that
 * a blank setting does not erase the deployment's own configuration, and that a
 * provider the test harness never selected cannot start putting mail on the wire.
 */

/** Write settings straight to the table, as the seeder does, and drop the cache. */
function mailSetting(string $key, mixed $value): void
{
    $setting = Setting::query()->where('key', $key)->firstOrFail();
    $setting->setTypedValue($value);
    $setting->save();

    app(Settings::class)->flush();
}

beforeEach(function (): void {
    $this->seed(SettingsSeeder::class);
});

// ── Applying settings over the environment ───────────────────────────────────

it('applies the stored provider and credentials over config', function () {
    mailSetting('mail.provider', 'mailgun');
    mailSetting('mail.from_address', 'hello@example.com');
    mailSetting('mail.from_name', 'MetaCreator');
    mailSetting('mail.mailgun.domain', 'mg.example.com');
    mailSetting('mail.mailgun.secret', 'key-123');
    mailSetting('mail.mailgun.endpoint', 'api.eu.mailgun.net');

    // Something other than `array`, which the configurator deliberately refuses to
    // displace — see the test below.
    config(['mail.default' => 'smtp']);

    app(MailConfigurator::class)->apply();

    expect(config('mail.default'))->toBe('mailgun')
        ->and(config('mail.from.address'))->toBe('hello@example.com')
        ->and(config('mail.from.name'))->toBe('MetaCreator')
        ->and(config('mail.mailers.mailgun.domain'))->toBe('mg.example.com')
        ->and(config('mail.mailers.mailgun.secret'))->toBe('key-123')
        ->and(config('mail.mailers.mailgun.endpoint'))->toBe('api.eu.mailgun.net');
});

it('leaves the environment alone where a setting is blank', function () {
    // The seeded defaults are empty, which has to mean "not configured here" rather
    // than "configured as empty" — otherwise saving the screen once would wipe the
    // MAIL_* configuration a deployment was already running on.
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => 'mailpit',
        'mail.mailers.smtp.port' => 1025,
        'mail.from.address' => 'env@example.com',
    ]);

    app(MailConfigurator::class)->apply();

    expect(config('mail.mailers.smtp.host'))->toBe('mailpit')
        ->and(config('mail.mailers.smtp.port'))->toBe(1025)
        ->and(config('mail.from.address'))->toBe('env@example.com');
});

it('never displaces the array mailer a harness pinned', function () {
    mailSetting('mail.provider', 'mailgun');
    mailSetting('mail.mailgun.domain', 'mg.example.com');
    mailSetting('mail.mailgun.secret', 'key-123');

    config(['mail.default' => 'array']);

    app(MailConfigurator::class)->apply();

    // Otherwise a seeded row would be enough to make a test run send real mail.
    expect(config('mail.default'))->toBe('array');
});

it('leaves the SMTP scheme to Symfony when set to auto', function () {
    mailSetting('mail.provider', 'smtp');
    mailSetting('mail.smtp.host', 'smtp.example.com');
    mailSetting('mail.smtp.port', '587');
    mailSetting('mail.smtp.scheme', 'auto');

    config(['mail.default' => 'smtp', 'mail.mailers.smtp.scheme' => null]);

    app(MailConfigurator::class)->apply();

    expect(config('mail.mailers.smtp.host'))->toBe('smtp.example.com')
        ->and(config('mail.mailers.smtp.scheme'))->toBeNull();

    mailSetting('mail.smtp.scheme', 'smtps');
    app(MailConfigurator::class)->apply();

    expect(config('mail.mailers.smtp.scheme'))->toBe('smtps');
});

it('points the SES transport at the services config it actually reads', function () {
    mailSetting('mail.provider', 'ses');
    mailSetting('mail.ses.key', 'AKIA000');
    mailSetting('mail.ses.secret', 'shh');
    mailSetting('mail.ses.region', 'eu-west-1');

    config(['mail.default' => 'smtp']);

    app(MailConfigurator::class)->apply();

    // Laravel's SES transport reads services.ses, not the mailer entry — a plausible
    // implementation that writes mail.mailers.ses.* configures nothing at all.
    expect(config('services.ses.key'))->toBe('AKIA000')
        ->and(config('services.ses.secret'))->toBe('shh')
        ->and(config('services.ses.region'))->toBe('eu-west-1');
});

// ── Readiness ────────────────────────────────────────────────────────────────

it('reports a provider as unconfigured until every credential it needs is present', function () {
    $settings = app(Settings::class);

    mailSetting('mail.provider', 'postmark');
    mailSetting('mail.from_address', 'hello@example.com');

    expect(MailProvider::fromSettings($settings)->isConfigured($settings))->toBeFalse();

    mailSetting('mail.postmark.token', 'token-123');

    expect(MailProvider::fromSettings(app(Settings::class))->isConfigured(app(Settings::class)))->toBeTrue();
});

it('treats a missing From address as unconfigured whatever the provider', function () {
    mailSetting('mail.provider', 'resend');
    mailSetting('mail.resend.key', 're_123');
    mailSetting('mail.from_address', '');

    $settings = app(Settings::class);

    // A provider with valid credentials and no From address authenticates happily
    // and then rejects every message.
    expect(MailProvider::fromSettings($settings)->isConfigured($settings))->toBeFalse();
});

// ── The admin endpoints ──────────────────────────────────────────────────────

it('reports mail status to staff who may view settings', function () {
    mailSetting('mail.provider', 'mailgun');
    mailSetting('mail.from_address', 'hello@example.com');

    $this->actingAs(staff('super-admin'))
        ->getJson('/api/v1/admin/settings/mail')
        ->assertOk()
        ->assertJsonPath('data.provider', 'mailgun')
        ->assertJsonPath('data.configured', false)
        ->assertJsonPath('data.from_address', 'hello@example.com')
        // Named so the screen can say what to fill in rather than "not configured".
        ->assertJsonPath('data.missing', ['mail.mailgun.domain', 'mail.mailgun.secret']);
});

it('flags Klaviyo as delivering through a flow', function () {
    mailSetting('mail.provider', 'klaviyo');

    $this->actingAs(staff('super-admin'))
        ->getJson('/api/v1/admin/settings/mail')
        ->assertOk()
        // Green credentials there still mean nothing is delivered until an operator
        // builds the flow, and the screen has to say so.
        ->assertJsonPath('data.delivers_via_flow', true);
});

it('sends a test message to the actor by default', function () {
    Mail::fake();

    mailSetting('mail.provider', 'smtp');
    mailSetting('mail.from_address', 'hello@example.com');
    mailSetting('mail.smtp.host', 'smtp.example.com');
    mailSetting('mail.smtp.port', '587');

    $actor = staff('super-admin');

    $this->actingAs($actor)
        ->postJson('/api/v1/admin/settings/mail/test')
        ->assertOk()
        ->assertJsonPath('data.sent', true)
        ->assertJsonPath('data.recipient', $actor->email);

    Mail::assertSent(TestMail::class, fn (TestMail $mail): bool => $mail->hasTo($actor->email));
});

it('refuses a test send while the provider is incomplete, without touching it', function () {
    Mail::fake();

    mailSetting('mail.provider', 'mailgun');
    mailSetting('mail.from_address', 'hello@example.com');

    $this->actingAs(staff('super-admin'))
        ->postJson('/api/v1/admin/settings/mail/test')
        ->assertOk()
        ->assertJsonPath('data.sent', false)
        // The names of the empty keys, so the fix is one read away.
        ->assertJsonFragment(['error' => 'Mailgun is selected but not fully configured. '
            .'Missing: mail.mailgun.domain, mail.mailgun.secret.']);

    Mail::assertNothingSent();
});

it('reports the provider’s own words when a test send fails', function () {
    mailSetting('mail.provider', 'mailgun');
    mailSetting('mail.from_address', 'hello@example.com');
    mailSetting('mail.mailgun.domain', 'mg.example.com');
    mailSetting('mail.mailgun.secret', 'key-123');

    Http::fake(['*' => Http::response(['message' => 'Domain not found: mg.example.com'], 404)]);

    // The array mailer would swallow this, and the point of the endpoint is the
    // transport's answer.
    config(['mail.default' => 'mailgun']);

    $response = $this->actingAs(staff('super-admin'))
        ->postJson('/api/v1/admin/settings/mail/test')
        ->assertOk()
        ->assertJsonPath('data.sent', false);

    expect($response->json('data.error'))->toContain('Domain not found');
});

it('records a successful test send in the audit log', function () {
    Mail::fake();

    mailSetting('mail.provider', 'smtp');
    mailSetting('mail.from_address', 'hello@example.com');
    mailSetting('mail.smtp.host', 'smtp.example.com');
    mailSetting('mail.smtp.port', '587');

    $actor = staff('super-admin');

    $this->actingAs($actor)->postJson('/api/v1/admin/settings/mail/test')->assertOk();

    expect(Activity::query()->where('event', 'mail.test_sent')->exists())->toBeTrue();
});

it('keeps the mail settings out of the public settings endpoint', function () {
    mailSetting('mail.smtp.host', 'smtp.internal.example.com');
    mailSetting('mail.from_address', 'hello@example.com');

    $response = $this->getJson('/api/v1/settings')->assertOk();

    // An SMTP host is reconnaissance, and a From address is a spoofing target.
    // Neither belongs in a payload anyone can fetch.
    expect(json_encode($response->json()))
        ->not->toContain('smtp.internal.example.com')
        ->not->toContain('mail.from_address');
});

it('never returns a stored mail credential to the browser', function () {
    mailSetting('mail.postmark.token', 'token-super-secret');

    $response = $this->actingAs(staff('super-admin'))
        ->getJson('/api/v1/admin/settings')
        ->assertOk();

    $token = collect($response->json('data.groups'))
        ->firstWhere('group', 'mail')['settings'];

    $stored = collect($token)->firstWhere('key', 'mail.postmark.token');

    // Only whether one is set — an admin screen that round-trips credentials is an
    // admin screen that leaks them into browser history and logs.
    expect($stored['value'])->toBeNull()
        ->and($stored['is_set'])->toBeTrue()
        ->and(json_encode($response->json()))->not->toContain('token-super-secret');
});

it('keeps a blank secret from erasing the stored one', function () {
    mailSetting('mail.postmark.token', 'token-super-secret');

    $this->actingAs(staff('super-admin'))
        ->putJson('/api/v1/admin/settings', [
            'settings' => [
                ['key' => 'mail.provider', 'value' => 'postmark'],
                ['key' => 'mail.postmark.token', 'value' => ''],
            ],
        ])
        ->assertOk();

    // Saving the form after a page load — where the credential box is empty because
    // the value was never sent — must not wipe the key.
    expect(app(Settings::class)->string('mail.postmark.token'))->toBe('token-super-secret');
});

it('needs the secrets permission to change a mail credential', function () {
    // `admin` deliberately does not hold settings.secrets.update.
    $this->actingAs(staff('admin'))
        ->putJson('/api/v1/admin/settings', [
            'settings' => [['key' => 'mail.resend.key', 'value' => 're_123']],
        ])
        ->assertForbidden();

    expect(app(Settings::class)->string('mail.resend.key'))->toBe('');
});

// ── The transports ───────────────────────────────────────────────────────────

it('posts the assembled MIME message to Mailgun', function () {
    Http::fake(['api.mailgun.net/*' => Http::response(['id' => '<abc@mg.example.com>'], 200)]);

    $transport = new MailgunTransport('mg.example.com', 'key-123');

    $transport->send(mailMessage());

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://api.mailgun.net/v3/mg.example.com/messages.mime'
            // Bcc has to be addressed by envelope, not by header, or it is not delivered.
            && str_contains($request->body(), 'to@example.com');
    });
});

it('maps the message into Postmark’s fields', function () {
    Http::fake(['api.postmarkapp.com/*' => Http::response(['ErrorCode' => 0, 'MessageID' => 'pm-1'], 200)]);

    (new PostmarkTransport('token-123', 'broadcast'))->send(mailMessage());

    Http::assertSent(function ($request): bool {
        $body = $request->data();

        return $request->url() === 'https://api.postmarkapp.com/email'
            && $body['Subject'] === 'Hello'
            && str_contains($body['To'], 'to@example.com')
            && $body['HtmlBody'] === '<p>Body</p>'
            && $body['MessageStream'] === 'broadcast'
            && $request->header('X-Postmark-Server-Token')[0] === 'token-123';
    });
});

it('treats a Postmark error code as a failure despite the 200', function () {
    // Postmark answers 200 with a non-zero ErrorCode for a suppressed recipient, so
    // a transport that trusts the status reports a delivery that never happened.
    Http::fake(['api.postmarkapp.com/*' => Http::response(['ErrorCode' => 406, 'Message' => 'Inactive recipient'], 200)]);

    expect(fn () => (new PostmarkTransport('token-123'))->send(mailMessage()))
        ->toThrow(TransportException::class, 'Inactive recipient');
});

it('posts JSON to Resend', function () {
    Http::fake(['api.resend.com/*' => Http::response(['id' => 're-1'], 200)]);

    (new ResendTransport('re_123'))->send(mailMessage());

    Http::assertSent(function ($request): bool {
        $body = $request->data();

        return $request->url() === 'https://api.resend.com/emails'
            && $body['subject'] === 'Hello'
            && $body['to'] === ['to@example.com']
            && $request->header('Authorization')[0] === 'Bearer re_123';
    });
});

it('posts one Klaviyo event per recipient, carrying the rendered body', function () {
    Http::fake(['a.klaviyo.com/*' => Http::response([], 202)]);

    (new KlaviyoTransport('pk_123', 'Transactional Email'))->send(mailMessage());

    Http::assertSent(function ($request): bool {
        $body = json_decode($request->body(), true);
        $attributes = $body['data']['attributes'];

        return $request->url() === 'https://a.klaviyo.com/api/events/'
            && $attributes['metric']['data']['attributes']['name'] === 'Transactional Email'
            && $attributes['profile']['data']['attributes']['email'] === 'to@example.com'
            // The flow template has nothing to render without these.
            && $attributes['properties']['subject'] === 'Hello'
            && $attributes['properties']['html_body'] === '<p>Body</p>';
    });
});

it('refuses to send an attachment through Klaviyo rather than dropping it', function () {
    Http::fake();

    $message = mailMessage(fn (Email $email) => $email->attach('data', 'invoice.pdf', 'application/pdf'));

    // A flow template cannot carry an attachment, and an invoice email delivered
    // without the invoice is worse than one that failed loudly.
    expect(fn () => (new KlaviyoTransport('pk_123'))->send($message))
        ->toThrow(TransportException::class, 'cannot carry attachments');

    Http::assertNothingSent();
});

it('surfaces an HTTP failure as a transport exception', function () {
    Http::fake(['api.resend.com/*' => Http::response(['message' => 'Invalid API key'], 401)]);

    expect(fn () => (new ResendTransport('re_bad'))->send(mailMessage()))
        ->toThrow(TransportException::class, 'Invalid API key');
});

/** A minimal Symfony message the transports can be pointed at. */
function mailMessage(?Closure $customise = null): Email
{
    $email = (new Email)
        ->from('from@example.com')
        ->to('to@example.com')
        ->subject('Hello')
        ->text('Body')
        ->html('<p>Body</p>');

    if ($customise !== null) {
        $customise($email);
    }

    return $email;
}
