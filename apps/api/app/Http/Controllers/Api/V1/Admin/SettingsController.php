<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Access\Services\AuditLogger;
use App\Domain\Settings\Setting;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Site configuration: branding, feature flags, SEO defaults, newsletter provider
 * and the tracking scripts.
 *
 * Three permissions guard one table, and the split is deliberate (docs/15):
 * `settings.update` for ordinary values, `settings.scripts.update` because raw HTML
 * in `<head>` is arbitrary code execution on every page of the site, and
 * `settings.secrets.update` because provider API keys are credentials. An admin
 * holds the first and not the others.
 */
final class SettingsController extends Controller
{
    /** Groups whose writes need the separate scripts permission. */
    private const SCRIPT_GROUP = 'scripts';

    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): JsonResource
    {
        $actor = $request->user();

        $settings = Setting::query()->orderBy('group')->orderBy('key')->get();

        return new JsonResource([
            'groups' => $settings
                ->groupBy('group')
                ->map(fn ($group, string $name) => [
                    'group' => $name,
                    'can_update' => $this->mayUpdateGroup($request, $name),
                    'settings' => $group->map(fn (Setting $setting): array => [
                        'key' => $setting->key,
                        'type' => $setting->type,
                        'group' => $setting->group,
                        'is_public' => $setting->is_public,
                        'is_secret' => $setting->is_encrypted,
                        'description' => $setting->description,
                        // A secret is never returned, only reported as set or not.
                        // A settings screen that round-trips API keys through a
                        // browser is a settings screen that leaks them.
                        'value' => $setting->is_encrypted ? null : $setting->typedValue(),
                        'is_set' => $setting->is_encrypted
                            ? ($setting->typedValue() !== null && $setting->typedValue() !== '')
                            : null,
                    ])->values()->all(),
                ])
                ->values()
                ->all(),
            'permissions' => [
                'settings.update' => $actor?->can('settings.update') === true,
                'settings.scripts.update' => $actor?->can('settings.scripts.update') === true,
                'settings.secrets.update' => $actor?->can('settings.secrets.update') === true,
            ],
        ]);
    }

    public function update(Request $request): JsonResource
    {
        $validated = $request->validate([
            'settings' => ['required', 'array', 'min:1', 'max:100'],
            'settings.*.key' => ['required', 'string', 'exists:settings,key'],
            'settings.*.value' => ['present'],
        ]);

        $changed = [];

        foreach ($validated['settings'] as $input) {
            $setting = Setting::query()->where('key', $input['key'])->firstOrFail();

            // Per-key, not per-request: one payload may legitimately touch several
            // groups, and the actor must clear the bar for each one they touch.
            abort_unless(
                $this->mayUpdateGroup($request, $setting->group, $setting->is_encrypted),
                403,
                "You do not have permission to change [{$setting->key}].",
            );

            $value = $this->coerce($setting, $input['value']);

            // A blank secret means "leave it alone", not "erase it" — otherwise every
            // save of the newsletter form would wipe the API key it never displayed.
            if ($setting->is_encrypted && ($value === null || $value === '')) {
                continue;
            }

            $before = $setting->is_encrypted ? '••••' : $setting->typedValue();

            $setting->setTypedValue($value);
            $setting->save();

            $changed[$setting->key] = ['from' => $before, 'to' => $setting->is_encrypted ? '••••' : $value];

            $this->audit->record(
                event: 'updated',
                subject: $setting,
                causer: $request->user(),
                before: [$setting->key => $before],
                after: [$setting->key => $setting->is_encrypted ? '••••' : $value],
                description: "Setting {$setting->key} updated",
            );
        }

        return new JsonResource(['updated' => array_keys($changed), 'changes' => $changed]);
    }

    private function mayUpdateGroup(Request $request, string $group, bool $isSecret = false): bool
    {
        $actor = $request->user();

        if ($actor === null) {
            return false;
        }

        if ($isSecret) {
            return $actor->can('settings.secrets.update');
        }

        return $group === self::SCRIPT_GROUP
            ? $actor->can('settings.scripts.update')
            : $actor->can('settings.update');
    }

    /** Coerce to the setting's declared type so `"false"` never lands as truthy. */
    private function coerce(Setting $setting, mixed $value): mixed
    {
        return match ($setting->type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOL),
            'int' => (int) $value,
            'json' => is_array($value) ? $value : json_decode((string) $value, true),
            default => is_scalar($value) ? (string) $value : null,
        };
    }
}
