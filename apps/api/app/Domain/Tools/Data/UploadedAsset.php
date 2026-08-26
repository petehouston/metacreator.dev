<?php

declare(strict_types=1);

namespace App\Domain\Tools\Data;

/**
 * A file that has already passed byte-sniffing, size and allow-list checks, and has
 * been written to temporary storage. Runners never see a raw upload.
 */
final readonly class UploadedAsset
{
    public function __construct(
        public string $path,
        public string $originalName,
        public string $mimeType,
        public int $size,
        public string $checksum,
        public ?int $width = null,
        public ?int $height = null,
        public ?int $durationMs = null,
    ) {}

    public function extension(): string
    {
        return strtolower(pathinfo($this->originalName, PATHINFO_EXTENSION));
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mimeType, 'image/');
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->mimeType, 'video/');
    }
}
