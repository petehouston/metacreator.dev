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
        ['key' => 'site.support_email', 'value' => 'support@metacreator.dev', 'type' => 'string', 'group' => 'branding', 'is_public' => true],

        // Feature flags — public
        ['key' => 'features.blog_enabled', 'value' => true, 'type' => 'bool', 'group' => 'features', 'is_public' => true,
            'description' => 'Turn the blog off entirely: routes 404, nav links disappear, sitemap entries are dropped.'],
        ['key' => 'features.registration_enabled', 'value' => true, 'type' => 'bool', 'group' => 'features', 'is_public' => true],
        ['key' => 'features.google_login_enabled', 'value' => true, 'type' => 'bool', 'group' => 'features', 'is_public' => true],
        ['key' => 'features.magic_link_enabled', 'value' => true, 'type' => 'bool', 'group' => 'features', 'is_public' => true],
        ['key' => 'features.billing_enabled', 'value' => true, 'type' => 'bool', 'group' => 'features', 'is_public' => true],
        ['key' => 'features.newsletter_enabled', 'value' => true, 'type' => 'bool', 'group' => 'features', 'is_public' => true],

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

        // Newsletter — provider choice public, credentials never
        ['key' => 'newsletter.provider', 'value' => 'local', 'type' => 'string', 'group' => 'newsletter', 'is_public' => true,
            'description' => 'local | mailchimp | mailerlite | moosend | sendy | brevo'],
        ['key' => 'newsletter.double_opt_in', 'value' => true, 'type' => 'bool', 'group' => 'newsletter', 'is_public' => false],
        ['key' => 'newsletter.list_id', 'value' => '', 'type' => 'string', 'group' => 'newsletter', 'is_public' => false],
        ['key' => 'newsletter.api_key', 'value' => '', 'type' => 'secret', 'group' => 'newsletter', 'is_public' => false, 'is_encrypted' => true],

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
