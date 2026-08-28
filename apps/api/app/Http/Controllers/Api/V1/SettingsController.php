<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

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
    public function __construct(private readonly Settings $settings) {}

    public function __invoke(): JsonResource
    {
        return new JsonResource($this->settings->publicMap());
    }
}
