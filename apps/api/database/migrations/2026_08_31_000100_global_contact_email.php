<?php

declare(strict_types=1);

use App\Domain\Settings\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * One contact address for the whole site.
 *
 * `site.support_email` used to be exactly what its name says: the address on the
 * support card. The contact page, the privacy policy and the security page each
 * carried their own hard-coded address instead, which is how a site ends up
 * publishing a `security@` inbox nobody has read in a year. They now all render
 * this setting, so it is the single global contact address and the default moves
 * with it.
 *
 * Only the old default is rewritten. An operator who has already pointed this at
 * their own inbox must not have it swapped for ours because the meaning of the key
 * widened — for them the description is the only thing that changes.
 */
return new class extends Migration
{
    private const PREVIOUS_DEFAULT = 'support@metacreator.dev';

    private const NEW_DEFAULT = 'metacreator.dev@gmail.com';

    private const DESCRIPTION = 'The one address the public site and the dashboard hand out — '
        .'contact, support, privacy questions and security reports all land here. Changing it '
        .'here changes it everywhere; nothing hard-codes an address.';

    public function up(): void
    {
        $setting = Setting::query()->where('key', 'site.support_email')->first();

        // A fresh install has not been seeded yet; the seeder writes both values.
        if ($setting === null) {
            return;
        }

        $setting->description = self::DESCRIPTION;

        if ($setting->typedValue() === self::PREVIOUS_DEFAULT) {
            $setting->setTypedValue(self::NEW_DEFAULT);
        }

        $setting->save();
    }

    public function down(): void
    {
        $setting = Setting::query()->where('key', 'site.support_email')->first();

        if ($setting === null) {
            return;
        }

        $setting->description = null;

        if ($setting->typedValue() === self::NEW_DEFAULT) {
            $setting->setTypedValue(self::PREVIOUS_DEFAULT);
        }

        $setting->save();
    }
};
