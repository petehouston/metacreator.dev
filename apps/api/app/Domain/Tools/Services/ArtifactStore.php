<?php

declare(strict_types=1);

namespace App\Domain\Tools\Services;

use App\Domain\Tools\Data\ResultArtifact;
use App\Domain\Tools\Data\ToolResult;
use App\Domain\Tools\Models\ToolRun;
use Illuminate\Support\Facades\Storage;

/**
 * Holds files produced by tool runs.
 *
 * Artifacts live in a private bucket and are only ever handed out as short-lived
 * signed URLs — they are not listable, not in the shared media library, and not
 * guessable. A user's exported media kit is their business, not the internet's.
 */
final class ArtifactStore
{
    private const SIGNED_URL_TTL_MINUTES = 60;

    private const RESULT_TTL_DAYS = 30;

    /**
     * Attach freshly signed URLs to every artifact on a result.
     */
    public function persist(ToolResult $result, ToolRun $run): ToolResult
    {
        if ($result->artifacts === []) {
            return $result;
        }

        $signed = array_map(
            fn (ResultArtifact $artifact) => $artifact->withUrl($this->signedUrl($artifact->key)),
            $result->artifacts,
        );

        return new ToolResult(
            $result->view,
            $result->data,
            $signed,
            $result->warnings,
            [...$result->meta, 'run' => $run->public_id],
            $result->summary,
        );
    }

    /**
     * Write a large result payload to object storage and return its key.
     *
     * Small results stay in the response; only payloads big enough to bloat the runs
     * table get externalised, which keeps the common case a single query.
     */
    public function store(ToolRun $run, ToolResult $result): ?string
    {
        $payload = json_encode($result->toArray(), JSON_THROW_ON_ERROR);

        if (strlen($payload) < 16 * 1024) {
            return null;
        }

        $key = "runs/{$run->ulid}/result.json";
        Storage::disk('private')->put($key, $payload);

        return $key;
    }

    /** @return array<string, mixed>|null */
    public function retrieve(ToolRun $run): ?array
    {
        if ($run->result_ref === null) {
            return null;
        }

        $payload = Storage::disk('private')->get($run->result_ref);

        return $payload === null ? null : json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
    }

    public function put(string $key, string $contents, string $mimeType): ResultArtifact
    {
        Storage::disk('private')->put($key, $contents, ['ContentType' => $mimeType]);

        return new ResultArtifact(
            key: $key,
            filename: basename($key),
            mimeType: $mimeType,
            size: strlen($contents),
            url: $this->signedUrl($key),
        );
    }

    public function signedUrl(string $key): string
    {
        return Storage::disk('private')->temporaryUrl($key, now()->addMinutes(self::SIGNED_URL_TTL_MINUTES));
    }

    /** Deletes artifacts older than the retention window. Called by the maintenance schedule. */
    public function prune(): int
    {
        $disk = Storage::disk('private');
        $cutoff = now()->subDays(self::RESULT_TTL_DAYS)->getTimestamp();
        $deleted = 0;

        foreach ($disk->allFiles('runs') as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }
}
