<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Billing\Services\BillingFeature;
use App\Domain\Settings\Settings;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The public half of the settings table.
 *
 * The frontend renders the site; some of what it renders is configurable without a
 * deploy — whether an article shows its author, how many posts a listing page holds.
 * Those decisions live in the same table an admin edits, so this endpoint hands over
 * exactly the rows marked `is_public` and never encrypted.
 *
 * {@see Settings::publicMap()} is where that filter lives, deliberately in one place:
 * a secret leaks the moment "which settings are safe" is answered twice.
 */
final class SettingsController extends Controller
{
    public function __construct(
        private readonly Settings $settings,
        private readonly BillingFeature $billing,
    ) {}

    public function __invoke(): JsonResource
    {
        $map = $this->settings->publicMap();

        // With billing off, the gateway settings are not merely unused — publishing a
        // provider name and a publishable key would let a client render a checkout
        // the server will not honour. `features.billing_enabled` stays, because it is
        // the flag the frontend needs in order to hide everything else.
        if ($this->billing->disabled()) {
            $map = array_filter(
                $map,
                static fn (string $key): bool => ! str_starts_with($key, 'payments.'),
                ARRAY_FILTER_USE_KEY,
            );
        }

        return new JsonResource($map);
    }
}
