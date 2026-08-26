<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Users\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * The public byline. Deliberately far narrower than {@see UserResource}: a blog
 * reader has no business seeing an author's email, roles or account state.
 *
 * @mixin User
 */
final class PostAuthorResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->displayName(),
            'avatar_url' => $this->avatar_path === null
                ? null
                : Storage::disk(config('filesystems.default'))->url($this->avatar_path),
        ];
    }
}
