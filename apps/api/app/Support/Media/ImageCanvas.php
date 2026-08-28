<?php

declare(strict_types=1);

namespace App\Support\Media;

use App\Domain\Tools\Data\ResultArtifact;
use App\Domain\Tools\Exceptions\ToolExecutionException;
use App\Support\Http\SafeHttpClient;
use GdImage;

/**
 * The one place tools touch pixels.
 *
 * Image tools were blocked on an upload pipeline that does not exist yet, so they
 * take an image **URL** instead: the source is fetched once through
 * {@see SafeHttpClient} — same SSRF guard as every other URL a tool accepts — and
 * everything after that is GD. When no URL is given the canvas is generated, so a
 * visitor can see what the tool does before they have an asset to feed it.
 *
 * Results come back as data URIs rather than signed Spaces links. That keeps the
 * whole path synchronous and storage-free; it also caps how big an output may be,
 * which is why every tool here works at web-delivery sizes rather than print ones.
 */
final class ImageCanvas
{
    /** Anything larger than this is a video frame dump, not a social image. */
    private const MAX_SOURCE_BYTES = 8_000_000;

    /** A data URI travels inside a JSON response, so outputs stay small on purpose. */
    private const MAX_OUTPUT_PIXELS = 4_000_000;

    private function __construct(
        public readonly GdImage $image,
        public readonly int $width,
        public readonly int $height,
        public readonly bool $isPlaceholder,
        public readonly int $sourceBytes = 0,
        public readonly string $sourceFormat = 'png',
    ) {}

    /**
     * Load the user's image, or draw a stand-in when they have not supplied one.
     *
     * `$seed` makes the placeholder deterministic, which matters because runners
     * must be pure: the same input has to produce the same bytes or caching lies.
     */
    public static function open(?string $url, string $seed = 'metacreator', int $width = 1600, int $height = 1600): self
    {
        $url = trim((string) $url);

        if ($url === '') {
            return self::placeholder($seed, $width, $height);
        }

        $url = str_contains($url, '://') ? $url : "https://{$url}";
        $bytes = SafeHttpClient::get($url, timeout: 8.0)->body();

        if (strlen($bytes) > self::MAX_SOURCE_BYTES) {
            throw ToolExecutionException::invalidInput(
                'That image is larger than 8 MB. Export a web-sized version and try again.',
                ['image_url' => 'Image too large.'],
            );
        }

        $info = @getimagesizefromstring($bytes);
        $image = $info === false ? false : @imagecreatefromstring($bytes);

        if ($image === false) {
            throw ToolExecutionException::invalidInput(
                'That URL did not return an image we can read. PNG, JPEG, WebP and GIF work.',
                ['image_url' => 'Not a readable image.'],
            );
        }

        imagepalettetotruecolor($image);

        return new self(
            $image,
            imagesx($image),
            imagesy($image),
            isPlaceholder: false,
            sourceBytes: strlen($bytes),
            sourceFormat: match ($info[2]) {
                IMAGETYPE_JPEG => 'jpeg',
                IMAGETYPE_WEBP => 'webp',
                IMAGETYPE_GIF => 'gif',
                default => 'png',
            },
        );
    }

    /**
     * A stand-in canvas: a deterministic gradient with a soft grid over it.
     *
     * It is not decoration. Crops, splits and safe-zone overlays are only legible
     * against something with structure — a flat colour would make every panel of a
     * carousel split look identical, which is precisely the thing being checked.
     */
    public static function placeholder(string $seed, int $width, int $height): self
    {
        $width = max(1, $width);
        $height = max(1, $height);

        $canvas = imagecreatetruecolor($width, $height);
        $hash = crc32($seed);
        $hue = $hash % 360;

        for ($y = 0; $y < $height; $y++) {
            $mix = $y / max(1, $height - 1);
            [$r, $g, $b] = self::hsl(fmod($hue + $mix * 60, 360), 0.55, 0.35 + $mix * 0.3);
            imagefilledrectangle($canvas, 0, $y, $width, $y, self::colour($canvas, $r, $g, $b));
        }

        $grid = self::colour($canvas, 255, 255, 255, 105);
        $step = (int) max(40, round(min($width, $height) / 12));

        for ($x = $step; $x < $width; $x += $step) {
            imagefilledrectangle($canvas, $x, 0, $x + 1, $height, $grid);
        }

        for ($y = $step; $y < $height; $y += $step) {
            imagefilledrectangle($canvas, 0, $y, $width, $y + 1, $grid);
        }

        // A ring off-centre gives every crop a recognisable landmark.
        $ring = self::colour($canvas, 255, 255, 255, 70);
        $radius = (int) (min($width, $height) * 0.42);
        imagesetthickness($canvas, max(3, (int) ($radius / 24)));
        imageellipse($canvas, (int) ($width / 2), (int) ($height / 2), $radius, $radius, $ring);

        return new self($canvas, $width, $height, isPlaceholder: true);
    }

    /**
     * Scale and centre-crop to fill the target box exactly.
     *
     * `$focusX`/`$focusY` are 0–1 positions of the point that must survive the crop,
     * which is the whole question a cover crop asks: something is being thrown away,
     * and you get to say what it is not.
     */
    public function cover(int $targetWidth, int $targetHeight, float $focusX = 0.5, float $focusY = 0.5): GdImage
    {
        $scale = max($targetWidth / $this->width, $targetHeight / $this->height);
        $sourceWidth = (int) round($targetWidth / $scale);
        $sourceHeight = (int) round($targetHeight / $scale);

        $sourceX = (int) round(($this->width - $sourceWidth) * $focusX);
        $sourceY = (int) round(($this->height - $sourceHeight) * $focusY);

        return $this->region($sourceX, $sourceY, $sourceWidth, $sourceHeight, $targetWidth, $targetHeight);
    }

    /** Resample an arbitrary source rectangle into a target box. */
    public function region(
        int $sourceX,
        int $sourceY,
        int $sourceWidth,
        int $sourceHeight,
        int $targetWidth,
        int $targetHeight,
    ): GdImage {
        [$targetWidth, $targetHeight] = self::clamp($targetWidth, $targetHeight);

        $out = imagecreatetruecolor(max(1, $targetWidth), max(1, $targetHeight));
        imagealphablending($out, false);
        imagesavealpha($out, true);

        imagecopyresampled(
            $out, $this->image,
            0, 0, $sourceX, $sourceY,
            $targetWidth, $targetHeight, max(1, $sourceWidth), max(1, $sourceHeight),
        );

        return $out;
    }

    /** Scale to fit inside the box without cropping; never enlarges past the source. */
    public function fit(int $maxWidth, int $maxHeight): GdImage
    {
        $scale = min($maxWidth / $this->width, $maxHeight / $this->height, 1.0);

        return $this->region(
            0, 0, $this->width, $this->height,
            (int) max(1, round($this->width * $scale)),
            (int) max(1, round($this->height * $scale)),
        );
    }

    /**
     * Encode to a data URI artifact.
     *
     * `$format` is one of jpeg, png, webp, gif — the four GD is built with here.
     */
    public static function artifact(
        GdImage $image,
        string $filename,
        string $format = 'jpeg',
        int $quality = 82,
        ?string $label = null,
    ): ResultArtifact {
        $bytes = self::encode($image, $format, $quality);
        $uri = 'data:'.self::mime($format).';base64,'.base64_encode($bytes);

        return new ResultArtifact(
            key: pathinfo($filename, PATHINFO_FILENAME),
            filename: $filename,
            mimeType: self::mime($format),
            size: strlen($bytes),
            url: $uri,
            width: imagesx($image),
            height: imagesy($image),
            label: $label,
            previewUrl: $uri,
        );
    }

    /** Raw encoded bytes, for tools that measure a file rather than show it. */
    public static function encode(GdImage $image, string $format, int $quality = 82): string
    {
        ob_start();

        match ($format) {
            'png' => imagepng($image, null, (int) round((100 - $quality) / 11.2)),
            'webp' => imagewebp($image, null, $quality),
            'gif' => imagegif($image),
            default => imagejpeg(self::flattened($image), null, $quality),
        };

        return (string) ob_get_clean();
    }

    public static function mime(string $format): string
    {
        return 'image/'.($format === 'jpeg' ? 'jpeg' : $format);
    }

    /**
     * The dominant colours in the image, most common first.
     *
     * Colours are bucketed on a coarse grid before counting: a photograph has tens
     * of thousands of unique values and almost none of them repeat, so counting raw
     * pixels returns noise rather than a palette.
     *
     * @return list<array{hex: string, rgb: array{int, int, int}, share: float}>
     */
    public function palette(int $count = 6): array
    {
        $sample = $this->fit(160, 160);
        $width = imagesx($sample);
        $height = imagesy($sample);

        $buckets = [];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($sample, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                $key = (intdiv($r, 24) << 8) | (intdiv($g, 24) << 4) | intdiv($b, 24);

                $buckets[$key] ??= ['n' => 0, 'r' => 0, 'g' => 0, 'b' => 0];
                $buckets[$key]['n']++;
                $buckets[$key]['r'] += $r;
                $buckets[$key]['g'] += $g;
                $buckets[$key]['b'] += $b;
            }
        }

        // Ties are broken on the bucket key so the palette is stable run to run.
        uksort($buckets, fn (int $a, int $b) => [$buckets[$b]['n'], $a] <=> [$buckets[$a]['n'], $b]);

        $total = $width * $height;
        $palette = [];

        foreach (array_slice($buckets, 0, $count, true) as $bucket) {
            $r = (int) round($bucket['r'] / $bucket['n']);
            $g = (int) round($bucket['g'] / $bucket['n']);
            $b = (int) round($bucket['b'] / $bucket['n']);

            $palette[] = [
                'hex' => sprintf('#%02X%02X%02X', $r, $g, $b),
                'rgb' => [$r, $g, $b],
                'share' => round($bucket['n'] / $total * 100, 1),
            ];
        }

        return $palette;
    }

    /** JPEG has no alpha, so transparency is composited onto white before encoding. */
    private static function flattened(GdImage $image): GdImage
    {
        $flat = imagecreatetruecolor(max(1, imagesx($image)), max(1, imagesy($image)));
        imagefilledrectangle($flat, 0, 0, imagesx($image), imagesy($image), self::colour($flat, 255, 255, 255));
        imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        return $flat;
    }

    /**
     * Allocate a colour, optionally with alpha.
     *
     * GD returns `false` when a palette is full — impossible on a truecolor canvas,
     * but the signature says otherwise, so the fallback is opaque black.
     */
    public static function colour(GdImage $image, int $red, int $green, int $blue, ?int $alpha = null): int
    {
        $clamp = static fn (int $value): int => max(0, min(255, $value));

        $index = $alpha === null
            ? imagecolorallocate($image, $clamp($red), $clamp($green), $clamp($blue))
            : imagecolorallocatealpha($image, $clamp($red), $clamp($green), $clamp($blue), max(0, min(127, $alpha)));

        return $index === false ? 0 : $index;
    }

    /** @return array{int, int} */
    private static function clamp(int $width, int $height): array
    {
        $width = max(1, $width);
        $height = max(1, $height);

        if ($width * $height <= self::MAX_OUTPUT_PIXELS) {
            return [$width, $height];
        }

        $scale = sqrt(self::MAX_OUTPUT_PIXELS / ($width * $height));

        return [(int) max(1, round($width * $scale)), (int) max(1, round($height * $scale))];
    }

    /** @return array{int, int, int} */
    private static function hsl(float $h, float $s, float $l): array
    {
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        [$r, $g, $b] = match (true) {
            $h < 60 => [$c, $x, 0.0],
            $h < 120 => [$x, $c, 0.0],
            $h < 180 => [0.0, $c, $x],
            $h < 240 => [0.0, $x, $c],
            $h < 300 => [$x, 0.0, $c],
            default => [$c, 0.0, $x],
        };

        return [
            (int) round(($r + $m) * 255),
            (int) round(($g + $m) * 255),
            (int) round(($b + $m) * 255),
        ];
    }
}
