<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdateAvatarRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class AvatarController extends Controller
{
    public function store(UpdateAvatarRequest $request): UserResource
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $previous = $user->avatar_path;

        // Laravel generates the filename, so a crafted upload name can never influence
        // the stored path. The extension comes from the validated mime, not the client.
        $path = $request->file('avatar')->store("avatars/{$user->ulid}", $this->disk());

        $user->forceFill(['avatar_path' => $path])->save();

        $this->deleteIfPresent($previous);

        return new UserResource($user->fresh());
    }

    public function destroy(Request $request): UserResource
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $previous = $user->avatar_path;

        $user->forceFill(['avatar_path' => null])->save();

        $this->deleteIfPresent($previous);

        return new UserResource($user->fresh());
    }

    private function disk(): string
    {
        return (string) config('filesystems.default');
    }

    /** Best-effort cleanup: a missing old file must never fail the request. */
    private function deleteIfPresent(?string $path): void
    {
        if ($path !== null && Storage::disk($this->disk())->exists($path)) {
            Storage::disk($this->disk())->delete($path);
        }
    }
}
