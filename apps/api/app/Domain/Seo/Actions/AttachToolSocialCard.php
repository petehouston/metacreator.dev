<?php

declare(strict_types=1);

namespace App\Domain\Seo\Actions;

use App\Domain\Media\Models\Media;
use App\Domain\Seo\Models\SeoMeta;
use App\Domain\Seo\Services\ToolSeoDefaults;
use App\Domain\Seo\Services\ToolSocialCard;
use App\Domain\Tools\Models\Tool;
use Illuminate\Support\Facades\Storage;

/**
 * Draws a tool's social card, files it in the media library, and points the tool's
 * SEO record at it.
 *
 * Written as one action rather than three so the three cannot drift apart: a file
 * on the disk with no media row is invisible to the admin, and a media row nothing
 * references is a leak. Either the tool ends up with a card the admin can see in
 * *SEO & Sharing*, or nothing was written at all.
 *
 * The path is derived from the slug, so re-running overwrites the tool's own card
 * rather than growing a new file per run. The media row is matched on that path for
 * the same reason — the library shows one card per tool, not one per deploy.
 */
final readonly class AttachToolSocialCard
{
    public function __construct(
        private ToolSocialCard $card,
        private ToolSeoDefaults $defaults,
    ) {}

    /** Draw a different address in the URL bar than the environment's own. */
    public function withSiteUrl(string $siteUrl): self
    {
        return new self(new ToolSocialCard(siteUrl: $siteUrl), $this->defaults);
    }

    /**
     * @return array{status: 'generated'|'replaced'|'skipped', reason?: string, bytes?: int, format?: string, url?: string}
     */
    public function handle(Tool $tool, bool $force = false, bool $dryRun = false): array
    {
        $seo = $this->seoFor($tool);
        $existing = $seo->og_media_id !== null ? Media::query()->find($seo->og_media_id) : null;

        // An image somebody chose by hand outranks a generated one. Overwriting it
        // on the next deploy is the bug that makes people stop trusting a command.
        if ($existing !== null && ! $this->isGenerated($existing, $tool) && ! $force) {
            return ['status' => 'skipped', 'reason' => 'a hand-picked image is set — pass --force to replace it'];
        }

        if ($existing !== null && $this->isGenerated($existing, $tool) && ! $force) {
            return ['status' => 'skipped', 'reason' => 'already generated — pass --force to redraw'];
        }

        $rendered = $this->card->render($tool);

        if ($dryRun) {
            return [
                'status' => $existing !== null ? 'replaced' : 'generated',
                'bytes' => strlen($rendered['bytes']),
                'format' => $rendered['extension'],
                'reason' => 'dry run — nothing written',
            ];
        }

        $disk = (string) config('filesystems.default');
        $path = $this->pathFor($tool, $rendered['extension']);

        Storage::disk($disk)->put($path, $rendered['bytes'], 'public');

        $media = Media::query()->firstOrNew(['disk' => $disk, 'path' => $path]);
        $wasReplaced = $media->exists;

        $media->forceFill([
            'filename' => basename($path),
            'mime_type' => $rendered['mime'],
            'size' => strlen($rendered['bytes']),
            'width' => $rendered['width'],
            'height' => $rendered['height'],
            'checksum' => hash('sha256', $rendered['bytes']),
            'alt_text' => $rendered['alt'],
            'title' => "{$tool->name} — social card",
            'usage_count' => 1,
        ])->save();

        // The rest of the sharing block is filled only where it is empty: the
        // command owns the picture, an editor owns the words.
        $fallbacks = $this->defaults->for($tool);

        $seo->forceFill([
            'og_media_id' => $media->id,
            'og_title' => $seo->og_title ?: $fallbacks['og_title'],
            'og_description' => $seo->og_description ?: $fallbacks['og_description'],
            'twitter_card' => $seo->twitter_card ?: 'summary_large_image',
        ])->save();

        return [
            'status' => $wasReplaced ? 'replaced' : 'generated',
            'bytes' => strlen($rendered['bytes']),
            'format' => $rendered['extension'],
            'url' => $media->url(),
        ];
    }

    private function seoFor(Tool $tool): SeoMeta
    {
        return $tool->seo()->firstOrNew([]);
    }

    /** One card per tool, addressed by slug so a re-run replaces rather than piles up. */
    private function pathFor(Tool $tool, string $extension): string
    {
        return "media/og/tools/{$tool->slug}.{$extension}";
    }

    /**
     * Did this command draw the image currently attached?
     *
     * Path, not a flag column: the file this action writes lives at exactly one
     * address, and anything else came from the media library by hand.
     */
    private function isGenerated(Media $media, Tool $tool): bool
    {
        return str_starts_with($media->path, "media/og/tools/{$tool->slug}.");
    }
}
