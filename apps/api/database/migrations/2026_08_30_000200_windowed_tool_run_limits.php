<?php

declare(strict_types=1);

use App\Domain\Settings\Setting;
use App\Domain\Tools\Enums\QuotaWindow;
use App\Domain\Tools\Enums\ToolTier;
use Illuminate\Database\Migrations\Migration;

/**
 * Run limits gain a window.
 *
 * `tools.limits.{tier}` was implicitly daily. It becomes `tools.limits.{tier}.daily`
 * and gains a weekly and a monthly sibling, so the same three tiers can be capped
 * over three periods at once.
 *
 * The rename carries the operator's own number across rather than reseeding the
 * default: a site running on a free allowance of 2 must not silently go back to 5
 * because the key moved.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (ToolTier::cases() as $tier) {
            $legacy = Setting::query()->where('key', "tools.limits.{$tier->value}")->first();

            foreach (QuotaWindow::all() as $window) {
                $setting = Setting::query()->firstOrNew(['key' => $window->settingKey($tier)]);

                if ($setting->exists) {
                    continue;
                }

                $setting->fill([
                    'type' => 'int',
                    'group' => 'tools',
                    'is_public' => true,
                    'is_encrypted' => false,
                    'description' => self::describe($tier, $window),
                ]);

                // Only the daily window inherits: a site that has never had a weekly
                // budget starts without one, rather than acquiring yesterday's daily
                // number as a surprise weekly ceiling.
                $setting->setTypedValue(
                    $window === QuotaWindow::Daily
                        ? ($legacy?->typedValue() ?? self::DEFAULT_DAILY[$tier->value])
                        : -1,
                );

                $setting->save();
            }

            $legacy?->delete();
        }
    }

    public function down(): void
    {
        foreach (ToolTier::cases() as $tier) {
            $daily = Setting::query()->where('key', QuotaWindow::Daily->settingKey($tier))->first();

            $setting = Setting::query()->firstOrNew(['key' => "tools.limits.{$tier->value}"]);
            $setting->fill(['type' => 'int', 'group' => 'tools', 'is_public' => true, 'is_encrypted' => false]);
            $setting->setTypedValue($daily?->typedValue() ?? self::DEFAULT_DAILY[$tier->value]);
            $setting->save();

            Setting::query()
                ->whereIn('key', array_map(
                    fn (QuotaWindow $window): string => $window->settingKey($tier),
                    QuotaWindow::all(),
                ))
                ->delete();
        }
    }

    private const DEFAULT_DAILY = [
        'free' => 5,
        'account' => 20,
        'premium' => -1,
    ];

    private static function describe(ToolTier $tier, QuotaWindow $window): string
    {
        $who = match ($tier) {
            ToolTier::Free => 'an anonymous visitor, counted per IP',
            ToolTier::Account => 'a signed-in visitor without a paid plan',
            ToolTier::Premium => 'a subscriber or pass holder',
        };

        $per = match ($window) {
            QuotaWindow::Daily => 'day',
            QuotaWindow::Weekly => 'week',
            QuotaWindow::Monthly => 'calendar month',
        };

        return "Runs per {$per} for {$who}. "
            .'-1 leaves this window uncounted; 0 closes the tier outright.';
    }
};
