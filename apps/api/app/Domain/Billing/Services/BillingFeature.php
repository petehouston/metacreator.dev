<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Settings\Settings;
use App\Domain\Tools\Enums\ToolTier;

/**
 * The master switch for money.
 *
 * `features.billing_enabled` is a different question from `payments.enabled`:
 * payments answers "can a card be charged right now", billing answers "does this
 * product have paid plans at all". A site with billing off is not a shop with the
 * till closed — it has no shop, and every surface that would sell something has to
 * be absent rather than merely inert.
 *
 * The consequence that matters most is on the catalog. A `premium` tool with
 * nothing to buy would be permanently unreachable, so while billing is off every
 * such tool reads and behaves as `account`: signing up is the whole price. Tools
 * already marked `free` or `account` are untouched, and nothing is written to the
 * database — flipping the switch back restores the paywall exactly as it was.
 *
 * Everything reads through this one service so the API, the catalog and the UI
 * cannot disagree about which tier a tool is in.
 */
final readonly class BillingFeature
{
    public function __construct(private Settings $settings) {}

    /**
     * Defaults to on: an install whose settings row is missing should behave like
     * the product it was built as, not silently give its paid tools away.
     */
    public function enabled(): bool
    {
        return $this->settings->bool('features.billing_enabled', true);
    }

    public function disabled(): bool
    {
        return ! $this->enabled();
    }

    /**
     * The tier a tool is actually gated at right now.
     *
     * Stored tier in, effective tier out — the row keeps saying `premium` so the
     * admin editor still shows what was configured and turning billing back on
     * needs no migration.
     */
    public function effectiveTier(ToolTier $tier): ToolTier
    {
        return $this->disabled() && $tier === ToolTier::Premium
            ? ToolTier::Account
            : $tier;
    }

    /** The top of the ladder as it currently stands — there is no rung above `account` without billing. */
    public function highestTier(): ToolTier
    {
        return $this->enabled() ? ToolTier::Premium : ToolTier::Account;
    }
}
