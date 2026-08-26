<?php

declare(strict_types=1);

namespace App\Domain\Tools\Data;

/**
 * A file produced by a tool run.
 *
 * Artifacts live in a private bucket and are served through short-lived signed URLs;
 * they are never listable and never appear in the shared media library.
 */
final readonly class ResultArtifact
{
    public function __construct(
        public string $key,
        public string $filename,
        public string $mimeType,
        public int $size,
        public ?string $url = null,
        public ?int $width = null,
        public ?int $height = null,
        public ?string $label = null,
        public ?string $previewUrl = null,
    ) {}

    public function withUrl(string $url, ?string $previewUrl = null): self
    {
        return new self(
            $this->key, $this->filename, $this->mimeType, $this->size,
            $url, $this->width, $this->height, $this->label, $previewUrl ?? $this->previewUrl,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'filename' => $this->filename,
            'mime_type' => $this->mimeType,
            'size' => $this->size,
            'url' => $this->url,
            'preview_url' => $this->previewUrl,
            'width' => $this->width,
            'height' => $this->height,
            'label' => $this->label,
        ];
    }
}
