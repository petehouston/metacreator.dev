<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Access\Services\AuditLogger;
use App\Domain\Media\Models\Media;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminMediaResource;
use App\Http\Resources\ApiCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The media library: browse, upload, describe, remove.
 *
 * Alt text is a first-class field rather than an afterthought — an image library
 * without it produces an inaccessible site one upload at a time — and
 * `is_decorative` exists so "no alt text" can be a deliberate, recorded choice
 * instead of an omission.
 */
final class MediaController extends Controller
{
    /** Uploads are constrained by extension *and* by MIME, and never executable. */
    private const ALLOWED = 'jpg,jpeg,png,gif,webp,avif,svg,mp4,webm,mp3,wav,ogg,pdf';

    public function __construct(private readonly AuditLogger $audit) {}

    /** @return ApiCollection<AdminMediaResource> */
    public function index(Request $request): ApiCollection
    {
        $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:180'],
            'filter.kind' => ['sometimes', 'nullable', 'in:image,video,audio,application'],
        ]);

        $media = Media::query()
            ->with('uploader:id,ulid,display_name,name')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($sub) => $sub
                ->where('filename', 'like', '%'.$request->string('q').'%')
                ->orWhere('title', 'like', '%'.$request->string('q').'%')
                ->orWhere('alt_text', 'like', '%'.$request->string('q').'%')))
            ->when($request->filled('filter.kind'), fn ($q) => $q->where(
                'mime_type', 'like', $request->string('filter.kind').'/%'
            ))
            ->latest('id')
            ->paginate(perPage: min(100, $request->integer('per_page', 40)))
            ->withQueryString();

        return new ApiCollection($media, AdminMediaResource::class);
    }

    /**
     * One file, addressed by its public id.
     *
     * The library grid used to open an editing panel over itself; it now navigates
     * to a page, and a page has to be able to load its own subject after a refresh
     * rather than inheriting a row somebody happened to click.
     */
    public function show(Media $media): AdminMediaResource
    {
        return new AdminMediaResource($media->load('uploader'));
    }

    public function store(Request $request): AdminMediaResource
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:51200', 'mimes:'.self::ALLOWED],
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $file = $request->file('file');
        $disk = (string) config('filesystems.default');

        // Foldered by month so a library that runs for years does not end up with a
        // single directory holding a hundred thousand files.
        $path = $file->storeAs(
            'media/'.now()->format('Y/m'),
            Str::ulid().'.'.$file->getClientOriginalExtension(),
            ['disk' => $disk, 'visibility' => 'public'],
        );

        $dimensions = @getimagesize($file->getRealPath()) ?: null;

        $media = Media::query()->create([
            'disk' => $disk,
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'width' => $dimensions[0] ?? null,
            'height' => $dimensions[1] ?? null,
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'alt_text' => $validated['alt_text'] ?? null,
            'title' => $validated['title'] ?? $file->getClientOriginalName(),
            'uploaded_by' => $request->user()?->id,
        ]);

        $this->audit->record('uploaded', $media, $request->user(), after: ['filename' => $media->filename]);

        return new AdminMediaResource($media->load('uploader'));
    }

    public function update(Request $request, Media $media): AdminMediaResource
    {
        $this->authorizeRow($request, $media, 'media.update');

        $validated = $request->validate([
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'caption' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'credit' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_decorative' => ['sometimes', 'boolean'],
        ]);

        $before = $media->only(array_keys($validated));

        $media->fill($validated)->save();

        $this->audit->record('updated', $media, $request->user(), before: $before, after: $validated);

        return new AdminMediaResource($media->load('uploader'));
    }

    public function destroy(Request $request, Media $media): JsonResponse
    {
        $this->authorizeRow($request, $media, 'media.delete');

        // Soft delete only. The file stays on the disk because a post published last
        // year may still reference it, and a 404 in an article is worse than a byte
        // of storage. A separate reaper sweeps files with no usages.
        $this->audit->record('deleted', $media, $request->user(), before: [
            'filename' => $media->filename,
            'usage_count' => $media->usage_count,
        ]);

        $media->delete();

        return response()->json(status: 204);
    }

    /** `media.update.own` and `media.delete.own` only mean something per row. */
    private function authorizeRow(Request $request, Media $media, string $permission): void
    {
        $actor = $request->user();

        abort_unless(
            $actor?->can($permission) === true
            || ($actor?->can("{$permission}.own") === true && $media->uploaded_by === $actor->id),
            403,
        );
    }
}
