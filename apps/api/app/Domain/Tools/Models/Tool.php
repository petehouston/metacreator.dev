<?php

declare(strict_types=1);

namespace App\Domain\Tools\Models;

use App\Domain\Billing\Services\BillingFeature;
use App\Domain\Seo\Models\SeoMeta;
use App\Domain\Tools\Enums\QuotaWindow;
use App\Domain\Tools\Enums\ToolStatus;
use App\Domain\Tools\Enums\ToolTier;
use App\Support\Casts\AsPreservedJson;
use App\Support\Concerns\HasUlidKey;
use App\Support\Concerns\PreservesAdminEdits;
use Carbon\CarbonImmutable;
use Database\Factories\ToolFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A catalog entry. The behaviour lives in the runner bound to `key`; this row holds
 * everything an admin can change without a deploy.
 *
 * @property-read string $public_id
 * @property int $id
 * @property string $key
 * @property string $slug
 * @property string $name
 * @property int $version
 * @property ToolTier $tier
 * @property ToolStatus $status
 * @property array<string, mixed>|null $config
 * @property array<string, mixed>|null $input_schema
 * @property array<string, mixed>|null $example
 * @property list<string>|null $locked_fields
 * @property array<int, string>|null $platforms
 * @property CarbonImmutable|null $featured_at
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $updated_at
 */
final class Tool extends Model
{
    /** @use HasFactory<ToolFactory> */
    use HasFactory;

    use HasUlidKey, PreservesAdminEdits, SoftDeletes;

    protected function ulidPrefix(): string
    {
        return 'tl';
    }

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tier' => ToolTier::class,
            'status' => ToolStatus::class,
            'is_visible' => 'boolean',
            'platforms' => 'array',
            // Stored as text, not JSON: MySQL sorts JSON object keys, and a tool's
            // property order is the order its generated form renders fields in.
            'input_schema' => AsPreservedJson::class,
            'config' => 'array',
            'instructions' => 'array',
            'example' => 'array',
            'faq' => 'array',
            'pinned_related' => 'array',
            'locked_fields' => 'array',
            'version' => 'integer',
            'run_count' => 'integer',
            'avg_duration_ms' => 'integer',
            'success_rate' => 'float',
            'featured_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /** Declared because our models live under App\Domain, not App\Models. */
    protected static function newFactory(): ToolFactory
    {
        return ToolFactory::new();
    }

    // ── Relations ────────────────────────────────────────────────────────────

    /** @return BelongsTo<ToolCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ToolCategory::class);
    }

    /** @return HasMany<ToolRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(ToolRun::class);
    }

    /** @return HasMany<ToolGrant, $this> */
    public function grants(): HasMany
    {
        return $this->hasMany(ToolGrant::class);
    }

    /** @return HasMany<ToolFavorite, $this> */
    public function favorites(): HasMany
    {
        return $this->hasMany(ToolFavorite::class);
    }

    /** @return MorphOne<SeoMeta, $this> */
    public function seo(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }

    /** @return BelongsToMany<Tool, $this> */
    public function related(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'tool_related', 'tool_id', 'related_tool_id')
            ->withPivot(['score', 'is_pinned'])
            ->orderByPivot('is_pinned', 'desc')
            ->orderByPivot('score', 'desc');
    }

    /** @return BelongsTo<Tool, $this> */
    public function successor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'successor_id');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    /** Everything a visitor is allowed to see in the catalog. */
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query
            ->where('is_visible', true)
            ->whereIn('status', [ToolStatus::Published->value, ToolStatus::Deprecated->value]);
    }

    /**
     * Filter by the tier a visitor is actually shown, not the stored one.
     *
     * With billing off a `premium` row presents as `account` everywhere else, so
     * filtering must agree: asking for `account` has to include it, and asking for
     * `premium` has to come back empty rather than listing tools whose cards say
     * "Account Required".
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeTier(Builder $query, ToolTier $tier): Builder
    {
        if (app(BillingFeature::class)->enabled()) {
            return $query->where('tier', $tier->value);
        }

        return match ($tier) {
            ToolTier::Account => $query->whereIn('tier', [ToolTier::Account->value, ToolTier::Premium->value]),
            ToolTier::Premium => $query->whereRaw('1 = 0'),
            ToolTier::Free => $query->where('tier', ToolTier::Free->value),
        };
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePlatform(Builder $query, string $platform): Builder
    {
        return $query->whereExists(
            fn ($sub) => $sub->from('tool_platform')
                ->whereColumn('tool_platform.tool_id', 'tools.id')
                ->where('tool_platform.platform', $platform)
        );
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        // MATCH…AGAINST for relevance, with a LIKE fallback so short terms and
        // partial words (which fulltext ignores) still find the obvious tool.
        return $query->where(function (Builder $q) use ($term): void {
            $q->whereFullText(['name', 'tagline', 'description'], $term)
                ->orWhere('name', 'like', $term.'%')
                ->orWhere('slug', 'like', '%'.str($term)->slug().'%');
        });
    }

    // ── Behaviour ────────────────────────────────────────────────────────────

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * The tier this tool is gated at right now — `tier` filtered through the
     * billing switch. Every public surface reads this; the admin editor reads the
     * raw `tier`, because that is the value it is there to edit.
     */
    public function effectiveTier(): ToolTier
    {
        return app(BillingFeature::class)->effectiveTier($this->tier);
    }

    public function isRunnable(): bool
    {
        return $this->status->isRunnable();
    }

    public function isFeatured(): bool
    {
        return $this->featured_at !== null;
    }

    /**
     * Cache namespace for this tool's results.
     *
     * Including the version means bumping `tools.version` retires every cached
     * result without a single Redis command.
     */
    public function cacheNamespace(): string
    {
        return "tool:{$this->key}:v{$this->version}";
    }

    /** @return list<string> */
    public function platformList(): array
    {
        return array_values($this->platforms ?? []);
    }

    /**
     * This tool's own cap for one window, or null when it defers to the tier.
     *
     * Stored under `config.limits.{window}` so a tool can carry a different shape of
     * budget from the global one — a metered provider is usually fine with a
     * generous day and a hard month, which a single "runs per day" number cannot
     * express. `config.runs_per_day` is still honoured as the daily value for rows
     * written before windows existed.
     */
    public function quotaOverride(QuotaWindow $window): ?int
    {
        $value = $this->config['limits'][$window->value] ?? null;

        if (! is_numeric($value) && $window === QuotaWindow::Daily) {
            $value = $this->config['runs_per_day'] ?? null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $limit = (int) $value;

        // A tool cap only ever narrows, so a negative here would be meaningless —
        // treat it as "no cap" rather than letting a tool claim to be unlimited.
        return $limit < 0 ? null : $limit;
    }

    /**
     * Every window this tool caps, keyed by window. Windows it defers on are absent.
     *
     * @return array<string, int>
     */
    public function quotaOverrides(): array
    {
        $overrides = [];

        foreach (QuotaWindow::all() as $window) {
            $limit = $this->quotaOverride($window);

            if ($limit !== null) {
                $overrides[$window->value] = $limit;
            }
        }

        return $overrides;
    }

    /**
     * Columns a save from the admin console does not take ownership of.
     *
     * `input_schema` is generated from the runner, and `version` retires the result
     * cache from a deploy — both belong to the code, so an admin editing the tagline
     * beside them must not freeze the next deploy out. The counters are written by
     * the application after every run and are nobody's decision.
     *
     * @return list<string>
     */
    protected function codeOwnedAttributes(): array
    {
        return ['input_schema', 'version', 'run_count', 'avg_duration_ms', 'success_rate'];
    }

    /**
     * The form schema as a visitor should see it, with the admin's own wording.
     *
     * The schema itself belongs to the runner — it is what the server validates
     * against, and it is regenerated on every deploy. What an admin *can* change is
     * how a field presents: the line of help under it, the greyed-out example in an
     * empty box, and the value it starts out holding. Those live in
     * `config.field_overrides` and are layered on here, so the stored schema and the
     * runner's can never disagree.
     *
     * @return array<string, mixed>
     */
    public function presentedInputSchema(): array
    {
        $schema = $this->input_schema ?? [];
        $overrides = $this->fieldOverrides();

        if ($overrides === [] || ! isset($schema['properties']) || ! is_array($schema['properties'])) {
            return $schema;
        }

        foreach ($schema['properties'] as $field => $property) {
            $override = $overrides[$field] ?? null;

            if (! is_array($override) || ! is_array($property)) {
                continue;
            }

            // The line of help under the box. Free text in the admin's own words,
            // never cast: a hint on an integer field is still a sentence.
            if (array_key_exists('hint', $override)) {
                $hint = is_scalar($override['hint']) ? trim((string) $override['hint']) : '';

                if ($hint === '') {
                    unset($schema['properties'][$field]['description']);
                } else {
                    $schema['properties'][$field]['description'] = $hint;
                }
            }

            // A blank sample is "show no placeholder", which is a real choice — so
            // it clears the runner's example rather than falling back to it.
            if (array_key_exists('sample', $override)) {
                $sample = $this->castForField($property, $override['sample']);

                $schema['properties'][$field]['examples'] = $sample === null ? [] : [$sample];
            }

            if (array_key_exists('default', $override)) {
                $default = $this->castForField($property, $override['default']);

                if ($default === null) {
                    unset($schema['properties'][$field]['default']);
                } else {
                    $schema['properties'][$field]['default'] = $default;
                }
            }
        }

        return $schema;
    }

    /**
     * The worked example behind "Try with sample data", with the admin's values.
     *
     * One value per field drives both this and the placeholder: an admin who fixes a
     * dead sample link is fixing the same fact in both places, and asking them to
     * type it twice is how the two drift apart.
     *
     * A tool that shipped without a worked example gets one as soon as an admin
     * fills in a sample, rather than storing the value and hiding the button it was
     * typed for — filling in samples is exactly how you give a tool that button.
     *
     * @return array<string, mixed>|null
     */
    public function presentedExample(): ?array
    {
        $example = $this->example;
        $overrides = $this->fieldOverrides();

        if ($overrides === []) {
            return $example;
        }

        $example = is_array($example) ? $example : ['input' => []];

        if (! is_array($example['input'] ?? null)) {
            $example['input'] = [];
        }

        $properties = is_array($this->input_schema['properties'] ?? null)
            ? $this->input_schema['properties']
            : [];

        foreach ($overrides as $field => $override) {
            if (! is_array($override) || ! array_key_exists('sample', $override)) {
                continue;
            }

            $sample = $this->castForField($properties[$field] ?? [], $override['sample']);

            if ($sample === null) {
                unset($example['input'][$field]);

                continue;
            }

            $example['input'][$field] = $sample;
        }

        // An example with nothing to fill in is not an example: the button would run
        // the tool on a blank form and show the visitor a validation error.
        return $example['input'] === [] ? null : $example;
    }

    /**
     * Per-field presentation set in the admin console, keyed by field name.
     *
     * Typed loosely, and read defensively everywhere it is used: this is decoded
     * JSON from a column, not a structure any type system has checked.
     *
     * @return array<string, mixed>
     */
    public function fieldOverrides(): array
    {
        $overrides = $this->config['field_overrides'] ?? [];

        return is_array($overrides) ? $overrides : [];
    }

    /**
     * Coerce an admin-typed value to what the field's schema actually accepts.
     *
     * Every override arrives from a text input as a string. Left that way, a "50"
     * sample on an integer field would be posted back by "Try with sample data" and
     * rejected by the very schema this value is meant to demonstrate.
     */
    private function castForField(mixed $property, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $type = is_array($property) ? ($property['type'] ?? 'string') : 'string';

        return match ($type) {
            'integer' => is_numeric($value) ? (int) $value : null,
            'number' => is_numeric($value) ? (float) $value : null,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            default => is_scalar($value) ? (string) $value : null,
        };
    }
}
