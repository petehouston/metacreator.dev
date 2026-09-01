<?php

declare(strict_types=1);

namespace App\Domain\Changelog\Enums;

/**
 * What kind of change an entry describes.
 *
 * Modelled on Keep a Changelog, trimmed to the categories this product actually
 * ships. The set is deliberately small: a reader scanning a release wants to know
 * "is this new, is it different, or was it broken", and eleven categories answer
 * that worse than five do.
 *
 * `tone` is here rather than in the frontend because the same colour has to appear
 * on the public timeline, in the admin list and in a release's own badge — three
 * places that would otherwise each hold an opinion.
 */
enum ChangeType: string
{
    /** Something that did not exist before. */
    case Added = 'added';

    /** Something that existed and now works better. */
    case Improved = 'improved';

    /** Something that was broken and is not any more. */
    case Fixed = 'fixed';

    /** Still there, still working, and going away on a stated date. */
    case Deprecated = 'deprecated';

    /** Gone. */
    case Removed = 'removed';

    /** A vulnerability closed, or a hardening change worth naming. */
    case Security = 'security';

    public function label(): string
    {
        return match ($this) {
            self::Added => 'New',
            self::Improved => 'Improved',
            self::Fixed => 'Fixed',
            self::Deprecated => 'Deprecated',
            self::Removed => 'Removed',
            self::Security => 'Security',
        };
    }

    /**
     * One line an editor can read in a select, so the difference between
     * "improved" and "fixed" is decided the same way by everyone.
     */
    public function hint(): string
    {
        return match ($this) {
            self::Added => 'Did not exist before this release',
            self::Improved => 'Already existed and now works better',
            self::Fixed => 'Was broken and now is not',
            self::Deprecated => 'Still works, going away on a stated date',
            self::Removed => 'No longer available',
            self::Security => 'A vulnerability closed or hardening applied',
        };
    }

    /** The semantic tone the UI paints this type with. */
    public function tone(): string
    {
        return match ($this) {
            self::Added => 'success',
            self::Improved => 'info',
            self::Fixed => 'warning',
            self::Deprecated => 'muted',
            self::Removed => 'muted',
            self::Security => 'danger',
        };
    }

    /**
     * Reading order within a release.
     *
     * New things first, housekeeping last — the order a reader would choose if they
     * only had time for the top of the card.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Added => 0,
            self::Improved => 1,
            self::Fixed => 2,
            self::Security => 3,
            self::Deprecated => 4,
            self::Removed => 5,
        };
    }

    /**
     * Every type with its presentation, for a client that has to render a picker.
     *
     * `weight` travels with it so the admin's "group by type" button orders entries
     * the same way this enum says they read — rather than the frontend keeping a
     * second copy of that opinion, which is how the two come apart.
     *
     * @return list<array{value: string, label: string, hint: string, tone: string, weight: int}>
     */
    public static function catalog(): array
    {
        return array_map(fn (self $type) => [
            'value' => $type->value,
            'label' => $type->label(),
            'hint' => $type->hint(),
            'tone' => $type->tone(),
            'weight' => $type->weight(),
        ], self::cases());
    }
}
