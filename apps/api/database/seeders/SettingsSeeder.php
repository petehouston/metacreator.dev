<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Settings\Setting;
use Illuminate\Database\Seeder;

final class SettingsSeeder extends Seeder
{
    /**
     * Every setting the admin UI can edit, with a safe default.
     *
     * Seeding the full set (rather than creating rows on first save) means the admin
     * screen always has something to render and a fresh install behaves predictably.
     */
    private const SETTINGS = [
        // Branding — public
        ['key' => 'site.name', 'value' => 'MetaCreator.Dev', 'type' => 'string', 'group' => 'branding', 'is_public' => true],
        ['key' => 'site.tagline', 'value' => 'Tools that help creators grow', 'type' => 'string', 'group' => 'branding', 'is_public' => true],
        ['key' => 'site.description', 'value' => 'A professional toolkit for creators and influencers — analyze, optimize and grow your accounts across YouTube, Instagram, TikTok, X, Facebook and LinkedIn.', 'type' => 'string', 'group' => 'branding', 'is_public' => true],
        ['key' => 'site.support_email', 'value' => 'metacreator.dev@gmail.com', 'type' => 'string', 'group' => 'branding', 'is_public' => true,
            'description' => 'The one address the public site and the dashboard hand out — contact, '
                .'support, privacy questions and security reports all land here. Changing it here '
                .'changes it everywhere; nothing hard-codes an address.'],

        // Feature flags — public
        ['key' => 'features.blog_enabled', 'value' => true, 'type' => 'bool', 'group' => 'features', 'is_public' => true,
            'description' => 'Turn the blog off entirely: routes 404, nav links disappear, sitemap entries are dropped.'],
        ['key' => 'features.changelog_enabled', 'value' => true, 'type' => 'bool', 'group' => 'features', 'is_public' => true,
            'description' => 'Turn the public changelog off: routes 404, the footer link disappears, sitemap entries are dropped. Releases are kept and stay editable in admin.'],
        ['key' => 'features.registration_enabled', 'value' => true, 'type' => 'bool', 'group' => 'features', 'is_public' => true],
        ['key' => 'features.google_login_enabled', 'value' => true, 'type' => 'bool', 'group' => 'features', 'is_public' => true],
        ['key' => 'features.magic_link_enabled', 'value' => true, 'type' => 'bool', 'group' => 'features', 'is_public' => true],
        ['key' => 'features.billing_enabled', 'value' => true, 'type' => 'bool', 'group' => 'features', 'is_public' => true,
            'description' => 'The master switch for money. Off, the product has no paid plans at all: '
                .'pricing and billing pages 404, upgrade prompts disappear, and every Pro tool is gated '
                .'at Account Required instead. Nothing is written to the tools table.'],
        ['key' => 'features.newsletter_enabled', 'value' => true, 'type' => 'bool', 'group' => 'features', 'is_public' => true],
        ['key' => 'features.search_enabled', 'value' => false, 'type' => 'bool', 'group' => 'features', 'is_public' => true,
            'description' => 'Global search across tools, posts, rankings and the static pages. Off by '
                .'default: /search 404s and the header search box disappears. Turn it on once the '
                .'catalog is worth searching.'],

        // Blog — how an article presents itself. Public: the frontend reads these
        // when it renders a post, so they have to be readable without a session.
        ['key' => 'blog.show_author', 'value' => true, 'type' => 'bool', 'group' => 'blog', 'is_public' => true,
            'description' => 'Show the author name and avatar on posts and cards.'],
        ['key' => 'blog.show_published_date', 'value' => true, 'type' => 'bool', 'group' => 'blog', 'is_public' => true,
            'description' => 'Show when a post was published. Off, the article reads as evergreen.'],
        ['key' => 'blog.show_reading_time', 'value' => true, 'type' => 'bool', 'group' => 'blog', 'is_public' => true],
        ['key' => 'blog.show_categories', 'value' => true, 'type' => 'bool', 'group' => 'blog', 'is_public' => true],
        ['key' => 'blog.show_tags', 'value' => true, 'type' => 'bool', 'group' => 'blog', 'is_public' => true],
        ['key' => 'blog.show_related_posts', 'value' => true, 'type' => 'bool', 'group' => 'blog', 'is_public' => true],
        ['key' => 'blog.show_featured_image', 'value' => true, 'type' => 'bool', 'group' => 'blog', 'is_public' => true],
        ['key' => 'blog.posts_per_page', 'value' => 12, 'type' => 'int', 'group' => 'blog', 'is_public' => true,
            'description' => 'How many posts a listing page shows. 12 fills a three-column grid exactly.'],

        // Tracking — public, but writable only with settings.scripts.update
        ['key' => 'tracking.ga4_id', 'value' => '', 'type' => 'string', 'group' => 'scripts', 'is_public' => true],
        ['key' => 'tracking.gtm_id', 'value' => '', 'type' => 'string', 'group' => 'scripts', 'is_public' => true],
        ['key' => 'tracking.meta_pixel_id', 'value' => '', 'type' => 'string', 'group' => 'scripts', 'is_public' => true],
        ['key' => 'tracking.tiktok_pixel_id', 'value' => '', 'type' => 'string', 'group' => 'scripts', 'is_public' => true],
        ['key' => 'scripts.head_start', 'value' => '', 'type' => 'string', 'group' => 'scripts', 'is_public' => true,
            'description' => 'Raw HTML injected at the start of <head>. Never injected into /admin or /dashboard.'],
        ['key' => 'scripts.head_end', 'value' => '', 'type' => 'string', 'group' => 'scripts', 'is_public' => true],
        ['key' => 'scripts.body_start', 'value' => '', 'type' => 'string', 'group' => 'scripts', 'is_public' => true],
        ['key' => 'scripts.body_end', 'value' => '', 'type' => 'string', 'group' => 'scripts', 'is_public' => true],

        // Transactional email — which transport carries password resets, receipts and
        // ticket replies, and the credentials for it. None of this is public: the
        // frontend never needs to know how mail leaves the building, and an SMTP
        // host is reconnaissance. Credentials are encrypted at rest, so changing one
        // needs `settings.secrets.update` rather than plain `settings.update`.
        //
        // Every value here is an *override*. Blank means "not configured here" and
        // the deployment's own MAIL_* environment keeps working — which is what lets
        // local development stay pointed at Mailpit with nothing seeded.
        ['key' => 'mail.provider', 'value' => 'smtp', 'type' => 'string', 'group' => 'mail', 'is_public' => false,
            'description' => 'smtp | mailgun | postmark | resend | ses | klaviyo | sendmail | log'],
        ['key' => 'mail.from_address', 'value' => '', 'type' => 'string', 'group' => 'mail', 'is_public' => false,
            'description' => 'The address every message is sent from. It has to be on a domain whose '
                .'SPF and DKIM records name the provider above, or the mail will send and land in spam.'],
        ['key' => 'mail.from_name', 'value' => '', 'type' => 'string', 'group' => 'mail', 'is_public' => false,
            'description' => 'The display name beside that address. Defaults to the app name when blank.'],
        ['key' => 'mail.reply_to_address', 'value' => '', 'type' => 'string', 'group' => 'mail', 'is_public' => false,
            'description' => 'Where a reply should land, when that is not the From address — the usual '
                .'reason being that From is a no-reply on the sending domain. Blank sends replies to From. '
                .'A mailable that sets its own reply-to keeps it.'],

        ['key' => 'mail.smtp.host', 'value' => '', 'type' => 'string', 'group' => 'mail', 'is_public' => false],
        ['key' => 'mail.smtp.port', 'value' => '', 'type' => 'string', 'group' => 'mail', 'is_public' => false,
            'description' => '587 for STARTTLS, 465 for implicit TLS, 2525 where 587 is blocked.'],
        ['key' => 'mail.smtp.scheme', 'value' => 'auto', 'type' => 'string', 'group' => 'mail', 'is_public' => false,
            'description' => 'auto | smtp | smtps. Auto infers encryption from the port and is right '
                .'far more often than a wrong explicit choice.'],
        ['key' => 'mail.smtp.username', 'value' => '', 'type' => 'string', 'group' => 'mail', 'is_public' => false],
        ['key' => 'mail.smtp.password', 'value' => '', 'type' => 'secret', 'group' => 'mail', 'is_public' => false, 'is_encrypted' => true],

        ['key' => 'mail.mailgun.domain', 'value' => '', 'type' => 'string', 'group' => 'mail', 'is_public' => false,
            'description' => 'The sending domain as Mailgun spells it, e.g. mg.example.com.'],
        ['key' => 'mail.mailgun.secret', 'value' => '', 'type' => 'secret', 'group' => 'mail', 'is_public' => false, 'is_encrypted' => true,
            'description' => 'A Mailgun sending API key.'],
        ['key' => 'mail.mailgun.endpoint', 'value' => 'api.mailgun.net', 'type' => 'string', 'group' => 'mail', 'is_public' => false,
            'description' => 'api.mailgun.net, or api.eu.mailgun.net for an EU-region account. The wrong '
                .'one authenticates and then reports the domain as unknown.'],

        ['key' => 'mail.postmark.token', 'value' => '', 'type' => 'secret', 'group' => 'mail', 'is_public' => false, 'is_encrypted' => true,
            'description' => 'A Postmark server API token — the server token, not the account token.'],
        ['key' => 'mail.postmark.message_stream', 'value' => 'outbound', 'type' => 'string', 'group' => 'mail', 'is_public' => false,
            'description' => 'Postmark keeps transactional and broadcast traffic in separate streams and '
                .'refuses a send to the wrong one. `outbound` is the default transactional stream.'],

        ['key' => 'mail.resend.key', 'value' => '', 'type' => 'secret', 'group' => 'mail', 'is_public' => false, 'is_encrypted' => true],

        ['key' => 'mail.ses.key', 'value' => '', 'type' => 'string', 'group' => 'mail', 'is_public' => false,
            'description' => 'An IAM access key ID whose policy allows ses:SendRawEmail and nothing else.'],
        ['key' => 'mail.ses.secret', 'value' => '', 'type' => 'secret', 'group' => 'mail', 'is_public' => false, 'is_encrypted' => true],
        ['key' => 'mail.ses.region', 'value' => 'us-east-1', 'type' => 'string', 'group' => 'mail', 'is_public' => false,
            'description' => 'The region the sending identity is verified in. An identity verified in one '
                .'region does not exist in another.'],

        ['key' => 'mail.klaviyo.api_key', 'value' => '', 'type' => 'secret', 'group' => 'mail', 'is_public' => false, 'is_encrypted' => true,
            'description' => 'A Klaviyo private API key with events:write.'],
        ['key' => 'mail.klaviyo.metric', 'value' => 'Transactional Email', 'type' => 'string', 'group' => 'mail', 'is_public' => false,
            'description' => 'Klaviyo cannot send a rendered message directly, so this transport posts '
                .'an event on this metric carrying the subject and body, and a flow triggered by the '
                .'metric sends it. Until that flow exists, every send succeeds and delivers nothing.'],

        // Newsletter — provider choice public, credentials never
        ['key' => 'newsletter.provider', 'value' => 'local', 'type' => 'string', 'group' => 'newsletter', 'is_public' => true,
            'description' => 'local | mailchimp | mailerlite | moosend | sendy | brevo'],
        ['key' => 'newsletter.double_opt_in', 'value' => true, 'type' => 'bool', 'group' => 'newsletter', 'is_public' => false],
        ['key' => 'newsletter.list_id', 'value' => '', 'type' => 'string', 'group' => 'newsletter', 'is_public' => false],
        ['key' => 'newsletter.api_key', 'value' => '', 'type' => 'secret', 'group' => 'newsletter', 'is_public' => false, 'is_encrypted' => true],

        // Payments — which gateway takes the money, and the credentials for it.
        // One provider is live at a time; the rest keep their keys so switching back
        // is a dropdown rather than a re-onboarding.
        ['key' => 'payments.provider', 'value' => 'stripe', 'type' => 'string', 'group' => 'payments', 'is_public' => true,
            'description' => 'none | stripe | paypal | braintree'],
        ['key' => 'payments.enabled', 'value' => false, 'type' => 'bool', 'group' => 'payments', 'is_public' => true,
            'description' => 'Turn checkout on. Off, the pricing page still lists plans but no purchase '
                .'can start. This is the till, not the shop — to remove paid plans entirely, use '
                .'Billing enabled under Features.'],
        ['key' => 'payments.test_mode', 'value' => true, 'type' => 'bool', 'group' => 'payments', 'is_public' => true],
        ['key' => 'payments.currency', 'value' => 'USD', 'type' => 'string', 'group' => 'payments', 'is_public' => true],

        ['key' => 'payments.stripe.publishable_key', 'value' => '', 'type' => 'string', 'group' => 'payments', 'is_public' => true],
        ['key' => 'payments.stripe.secret_key', 'value' => '', 'type' => 'secret', 'group' => 'payments', 'is_public' => false, 'is_encrypted' => true],
        ['key' => 'payments.stripe.webhook_secret', 'value' => '', 'type' => 'secret', 'group' => 'payments', 'is_public' => false, 'is_encrypted' => true],

        ['key' => 'payments.paypal.client_id', 'value' => '', 'type' => 'string', 'group' => 'payments', 'is_public' => true],
        ['key' => 'payments.paypal.client_secret', 'value' => '', 'type' => 'secret', 'group' => 'payments', 'is_public' => false, 'is_encrypted' => true],
        ['key' => 'payments.paypal.webhook_id', 'value' => '', 'type' => 'string', 'group' => 'payments', 'is_public' => false],

        ['key' => 'payments.braintree.merchant_id', 'value' => '', 'type' => 'string', 'group' => 'payments', 'is_public' => false],
        ['key' => 'payments.braintree.public_key', 'value' => '', 'type' => 'string', 'group' => 'payments', 'is_public' => false],
        ['key' => 'payments.braintree.private_key', 'value' => '', 'type' => 'secret', 'group' => 'payments', 'is_public' => false, 'is_encrypted' => true],

        // External providers. Tools that need a key say so plainly when it is missing,
        // rather than failing as though the site were broken.
        ['key' => 'providers.youtube.api_key', 'value' => '', 'type' => 'secret', 'group' => 'providers',
            'is_public' => false, 'is_encrypted' => true,
            'description' => 'YouTube Data API v3 key. Required by the Comment Finder; every other '
                .'YouTube tool works without it.'],

        // Tools — how much of the catalog a given actor may use in a day, and what
        // counts as trending. These are the levers pricing is actually made of, so
        // they are settings rather than constants: changing the free allowance must
        // not need a deploy.
        //
        // The keys name *access tiers*, not tool tiers: `free` is an
        // anonymous visitor (counted per IP), `account` is anyone signed in without
        // a paid plan, `premium` is a paying subscriber or pass holder. A negative
        // value means unlimited; zero blocks the tier outright.
        // Each tier is capped over three windows at once — daily, weekly, monthly —
        // and the first one to run out is the one that walls. Only the daily window
        // is on by default: a weekly or monthly ceiling is a cost decision an
        // operator makes deliberately, not something a fresh install should impose.
        // -1 leaves a window uncounted; 0 closes the tier outright.
        ['key' => 'tools.limits.free.daily', 'value' => 5, 'type' => 'int', 'group' => 'tools', 'is_public' => true,
            'description' => 'Runs per day for an anonymous visitor, counted per IP. '
                .'Use -1 for unlimited, 0 to require an account for everything.'],
        ['key' => 'tools.limits.free.weekly', 'value' => -1, 'type' => 'int', 'group' => 'tools', 'is_public' => true,
            'description' => 'Runs per calendar week for an anonymous visitor. -1 leaves the week uncounted.'],
        ['key' => 'tools.limits.free.monthly', 'value' => -1, 'type' => 'int', 'group' => 'tools', 'is_public' => true,
            'description' => 'Runs per calendar month for an anonymous visitor. -1 leaves the month uncounted.'],

        ['key' => 'tools.limits.account.daily', 'value' => 20, 'type' => 'int', 'group' => 'tools', 'is_public' => true,
            'description' => 'Runs per day for a signed-in visitor without a paid plan. -1 for unlimited.'],
        ['key' => 'tools.limits.account.weekly', 'value' => -1, 'type' => 'int', 'group' => 'tools', 'is_public' => true,
            'description' => 'Runs per calendar week for a signed-in visitor without a paid plan.'],
        ['key' => 'tools.limits.account.monthly', 'value' => -1, 'type' => 'int', 'group' => 'tools', 'is_public' => true,
            'description' => 'Runs per calendar month for a signed-in visitor without a paid plan.'],

        ['key' => 'tools.limits.premium.daily', 'value' => -1, 'type' => 'int', 'group' => 'tools', 'is_public' => true,
            'description' => 'Runs per day for a subscriber or pass holder. -1 (the default) is unlimited.'],
        ['key' => 'tools.limits.premium.weekly', 'value' => -1, 'type' => 'int', 'group' => 'tools', 'is_public' => true,
            'description' => 'Runs per calendar week for a subscriber or pass holder.'],
        ['key' => 'tools.limits.premium.monthly', 'value' => -1, 'type' => 'int', 'group' => 'tools', 'is_public' => true,
            'description' => 'Runs per calendar month for a subscriber or pass holder. A monthly ceiling is '
                .'the honest way to make an "unlimited" plan survive one abusive account.'],

        ['key' => 'tools.trending_days', 'value' => 3, 'type' => 'int', 'group' => 'tools', 'is_public' => true,
            'description' => 'How far back the Trending sort looks. Shorter reacts faster and is noisier; '
                .'longer is steadier and closer to Popular.'],
        ['key' => 'tools.trending_min_runs', 'value' => 1, 'type' => 'int', 'group' => 'tools', 'is_public' => true,
            'description' => 'Runs a tool needs inside that window before it can be called trending at all. '
                .'Stops a single run on a quiet day from topping the list.'],

        // SEO templates
        ['key' => 'seo.title_template', 'value' => '{{title}} | MetaCreator.Dev', 'type' => 'string', 'group' => 'seo', 'is_public' => true],
        ['key' => 'seo.tool_title_template', 'value' => '{{name}} — Free {{platform}} Tool | MetaCreator.Dev', 'type' => 'string', 'group' => 'seo', 'is_public' => true],
        ['key' => 'seo.default_og_image', 'value' => '/og/default.png', 'type' => 'string', 'group' => 'seo', 'is_public' => true],
        ['key' => 'seo.google_verification', 'value' => '', 'type' => 'string', 'group' => 'seo', 'is_public' => true],
        ['key' => 'seo.bing_verification', 'value' => '', 'type' => 'string', 'group' => 'seo', 'is_public' => true],
    ];

    public function run(): void
    {
        foreach (self::SETTINGS as $definition) {
            $setting = Setting::query()->firstOrNew(['key' => $definition['key']]);

            // Never clobber a value an admin has already set.
            if ($setting->exists) {
                continue;
            }

            $setting->fill([
                'type' => $definition['type'],
                'group' => $definition['group'],
                'is_public' => $definition['is_public'],
                'is_encrypted' => $definition['is_encrypted'] ?? false,
                'description' => $definition['description'] ?? null,
            ]);

            $setting->setTypedValue($definition['value']);
            $setting->save();
        }
    }
}
