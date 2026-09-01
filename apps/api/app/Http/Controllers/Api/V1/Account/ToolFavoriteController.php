<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Domain\Tools\Models\Tool;
use App\Domain\Tools\Services\FavoriteTools;
use App\Domain\Tools\Services\ToolAccessService;
use App\Http\Controllers\Controller;
use App\Http\Resources\ToolResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The saved list, for signed-in members only.
 *
 * There is no anonymous equivalent on purpose: the only identifier we hold for a
 * visitor is a hash whose salt rotates at midnight, so an anonymous favourites list
 * would silently empty itself overnight. Offering it and losing it is worse than
 * making it a reason to create an account.
 */
final class ToolFavoriteController extends Controller
{
    public function __construct(
        private readonly FavoriteTools $favorites,
        private readonly ToolAccessService $access,
    ) {}

    /**
     * The saved tools, as full cards.
     *
     * Cards rather than bare slugs because this is what the favourites screen
     * renders, and `meta.slugs` alongside them is what the catalog needs to mark
     * hearts without a second request.
     */
    public function index(Request $request): JsonResource
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $tools = $user->favoriteTools()
            ->public()
            ->with(['category:id,slug,name,icon,accent_color'])
            ->get();

        return new JsonResource([
            'data' => ToolResource::collection($tools),
            'meta' => [
                'slugs' => $tools->pluck('slug')->values()->all(),
                'access' => $this->access->decideMany($tools, $user),
            ],
        ]);
    }

    /**
     * Save a tool. Idempotent: saving something already saved is a 200, not a 409 —
     * a double-tapped heart is not an error worth showing anybody.
     */
    public function store(Request $request, string $slug): JsonResource
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $tool = Tool::query()->public()->where('slug', $slug)->firstOrFail();

        $this->favorites->add($user, $tool);

        return new JsonResource(['slug' => $tool->slug, 'is_favorite' => true]);
    }

    /** Unsave. Equally idempotent — removing what is not there is the same outcome. */
    public function destroy(Request $request, string $slug): JsonResource
    {
        $user = $request->user();
        abort_if($user === null, 401);

        // Not scoped to `public()`: a tool an admin has since hidden must still be
        // removable from a list it is already on.
        $tool = Tool::query()->where('slug', $slug)->firstOrFail();

        $this->favorites->remove($user, $tool);

        return new JsonResource(['slug' => $tool->slug, 'is_favorite' => false]);
    }
}
