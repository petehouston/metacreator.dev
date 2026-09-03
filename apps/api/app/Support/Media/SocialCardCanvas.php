<?php

declare(strict_types=1);

namespace App\Support\Media;

use GdImage;
use RuntimeException;

/**
 * A small drawing surface for open-graph cards.
 *
 * GD rather than a headless browser on purpose: a card is a handful of rectangles,
 * three strings and a logo, and paying for Chromium — plus its memory, its startup
 * cost and its habit of breaking on a base-image bump — to lay out that much is a
 * bad trade. Everything here is deterministic and runs in the same PHP process the
 * rest of the queue does.
 *
 * ── The two ideas that make GD output look drawn rather than plotted ─────────
 *
 * 1. **Supersampling.** Every coordinate is in the card's own 1200 × 630 space and
 *    is multiplied by {@see $scale} on the way to GD, which draws into a canvas
 *    that many times larger. The final {@see encode()} resamples back down, so the
 *    corners of a rounded rectangle and the edge of a rotated sticker arrive
 *    antialiased even though GD antialiases neither.
 *
 * 2. **Gradients are drawn small and scaled up.** A per-pixel loop over 3 million
 *    pixels in PHP is slow; the same loop over a 200 × 105 thumbnail is instant,
 *    and a gradient is exactly the kind of image that loses nothing to bicubic
 *    upscaling.
 *
 * Colours are `#rrggbb` throughout, with alpha passed separately as 0..1, because
 * a design lives in hex and GD's 7-bit inverted alpha is nobody's mental model.
 */
final class SocialCardCanvas
{
    private GdImage $im;

    /**
     * Cached per (font, size, run) so a 40-character headline is not 40 file reads.
     *
     * @var array<string, float>
     */
    private array $fonts = [];

    public function __construct(
        private readonly int $width,
        private readonly int $height,
        private readonly string $fontDir,
        private readonly int $scale = 2,
    ) {
        $im = imagecreatetruecolor(max(1, $width * $scale), max(1, $height * $scale));

        if ($im === false) {
            throw new RuntimeException('GD could not allocate the card canvas.');
        }

        $this->im = $im;
        imagealphablending($this->im, true);
        imagesavealpha($this->im, false);
    }

    public function __destruct()
    {
        imagedestroy($this->im);
    }

    // ── Ground ───────────────────────────────────────────────────────────────

    /**
     * A linear gradient across the whole card.
     *
     * @param  list<string>  $stops  Evenly spaced hex colours, first at the start.
     * @param  float  $angle  Degrees clockwise from "left to right".
     */
    public function gradient(array $stops, float $angle = 135.0): void
    {
        $w = 220;
        $h = (int) round($w * $this->height / $this->width);
        $small = $this->blank($w, $h);

        $rad = deg2rad($angle);
        $ux = cos($rad);
        $uy = sin($rad);
        // Longest projection of the box onto the gradient axis, so t spans 0..1
        // regardless of the angle.
        $span = abs($ux) * $w + abs($uy) * $h;
        $ox = $ux < 0 ? $w : 0;
        $oy = $uy < 0 ? $h : 0;

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $t = (($x - $ox) * $ux + ($y - $oy) * $uy) / max($span, 1);
                [$r, $g, $b] = $this->rampAt($stops, max(0.0, min(1.0, $t)));
                imagesetpixel($small, $x, $y, (int) imagecolorallocate($small, $r, $g, $b));
            }
        }

        imagecopyresampled(
            $this->im, $small,
            0, 0, 0, 0,
            $this->width * $this->scale, $this->height * $this->scale, $w, $h,
        );
        imagedestroy($small);
    }

    /**
     * A soft radial glow, used to tint a card with the platform's own colour
     * without letting that colour anywhere near the type.
     */
    public function glow(float $cx, float $cy, float $rx, float $ry, string $hex, float $alpha): void
    {
        $w = 200;
        $h = (int) round($w * $this->height / $this->width);
        $layer = $this->blank($w, $h, transparent: true);

        [$r, $g, $b] = $this->rgb($hex);
        $px = $cx / $this->width * $w;
        $py = $cy / $this->height * $h;
        $srx = max($rx / $this->width * $w, 0.001);
        $sry = max($ry / $this->height * $h, 0.001);

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $d = sqrt((($x - $px) / $srx) ** 2 + (($y - $py) / $sry) ** 2);

                if ($d >= 1.0) {
                    continue;
                }

                // Smooth falloff: squaring keeps the centre bright and the edge
                // invisible, which is what stops a wash from reading as a disc.
                $a = (1 - $d) ** 2 * $alpha;
                imagesetpixel($layer, $x, $y, (int) imagecolorallocatealpha($layer, $r, $g, $b, $this->opacity($a)));
            }
        }

        imagecopyresampled(
            $this->im, $layer,
            0, 0, 0, 0,
            $this->width * $this->scale, $this->height * $this->scale, $w, $h,
        );
        imagedestroy($layer);
    }

    // ── Shapes ───────────────────────────────────────────────────────────────

    public function rect(float $x, float $y, float $w, float $h, string $hex, float $alpha = 1.0): void
    {
        imagefilledrectangle(
            $this->im,
            $this->px($x), $this->px($y),
            $this->px($x + $w) - 1, $this->px($y + $h) - 1,
            $this->colour($hex, $alpha),
        );
    }

    public function roundedRect(float $x, float $y, float $w, float $h, float $radius, string $hex, float $alpha = 1.0): void
    {
        $this->stamp($x, $y, $w, $h, function (GdImage $layer, int $lw, int $lh) use ($radius, $hex, $alpha): void {
            $this->roundedOn($layer, 0, 0, $lw, $lh, (int) round($radius * $this->scale), $this->on($layer, $hex, $alpha));
        });
    }

    /**
     * An outlined rounded rectangle: the stroke, with the fill punched inside it.
     *
     * Both are drawn into one stamped layer with blending *off*, so a translucent
     * fill inside a translucent stroke replaces those pixels rather than adding to
     * them. Drawn straight onto the card, the overlap would show up as a darker
     * ring — the tell-tale of alpha applied twice.
     */
    public function roundedOutline(
        float $x, float $y, float $w, float $h, float $radius,
        string $strokeHex, float $strokeWidth, ?string $fillHex = null, float $fillAlpha = 1.0,
        float $strokeAlpha = 1.0,
    ): void {
        $this->stamp($x, $y, $w, $h, function (GdImage $layer, int $lw, int $lh) use (
            $radius, $strokeHex, $strokeWidth, $fillHex, $fillAlpha, $strokeAlpha
        ): void {
            $r = (int) round($radius * $this->scale);
            $sw = (int) round($strokeWidth * $this->scale);

            $this->roundedOn($layer, 0, 0, $lw, $lh, $r, $this->on($layer, $strokeHex, $strokeAlpha));

            if ($fillHex !== null) {
                $this->roundedOn(
                    $layer, $sw, $sw, $lw - $sw * 2, $lh - $sw * 2,
                    max($r - $sw, 0), $this->on($layer, $fillHex, $fillAlpha),
                );
            }
        });
    }

    public function circle(float $cx, float $cy, float $diameter, string $hex, float $alpha = 1.0): void
    {
        $this->stamp($cx - $diameter / 2, $cy - $diameter / 2, $diameter, $diameter,
            function (GdImage $layer, int $lw, int $lh) use ($hex, $alpha): void {
                imagefilledellipse($layer, (int) ($lw / 2), (int) ($lh / 2), $lw, $lh, $this->on($layer, $hex, $alpha));
            });
    }

    /** A right-pointing triangle — the play glyph, and nothing else so far. */
    public function triangle(float $x, float $y, float $w, float $h, string $hex, float $alpha = 1.0): void
    {
        $this->stamp($x, $y, $w, $h, function (GdImage $layer, int $lw, int $lh) use ($hex, $alpha): void {
            imagefilledpolygon($layer, [0, 0, $lw, (int) ($lh / 2), 0, $lh], $this->on($layer, $hex, $alpha));
        });
    }

    // ── Type ─────────────────────────────────────────────────────────────────

    /**
     * Draw a string. `$x` is the left edge, or the centre when `$align` is
     * `center`; `$y` is the text baseline's *cap top*, not GD's baseline, because
     * laying out from the top of the letters is what a design does.
     *
     * @param  float  $tracking  Extra space between characters, in card pixels.
     */
    public function text(
        string $text, float $x, float $y, float $size, string $font, string $hex,
        string $align = 'left', float $alpha = 1.0, float $tracking = 0.0,
    ): void {
        $file = $this->font($font);
        $s = $this->points($size);
        $colour = $this->colour($hex, $alpha);
        $width = $this->textWidth($text, $size, $font, $tracking);
        $left = match ($align) {
            'center' => $x - $width / 2,
            'right' => $x - $width,
            default => $x,
        };

        // imagettftext measures from the baseline; the cap height for these faces
        // sits about 0.72 em above it, which is close enough that a design's
        // "top of the text" and the rendered top agree.
        $baseline = $this->px($y + $size * 0.78);

        if ($tracking === 0.0) {
            imagettftext($this->im, $s, 0, $this->px($left), $baseline, $colour, $file, $text);

            return;
        }

        $cursor = $left;

        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            imagettftext($this->im, $s, 0, $this->px($cursor), $baseline, $colour, $file, $char);
            $cursor += $this->advance($char, $size, $font) + $tracking;
        }
    }

    /** How wide a sticker will be, so a row of them can be centred before drawing. */
    public function stickerWidth(string $label, float $size, string $font, float $padX): float
    {
        return $this->textWidth($label, $size, $font) + $padX * 2;
    }

    public function textWidth(string $text, float $size, string $font, float $tracking = 0.0): float
    {
        if ($tracking === 0.0) {
            return $this->advance($text, $size, $font);
        }

        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $width = 0.0;

        foreach ($chars as $char) {
            $width += $this->advance($char, $size, $font) + $tracking;
        }

        return $width - $tracking;
    }

    /**
     * The largest size at or below `$preferred` that keeps `$text` on one line.
     *
     * Single-line is the rule for these cards: a tool name that wraps changes the
     * height of everything under it, and a set of 90-odd cards where the logo sits
     * at a different height on every third one stops reading as a set.
     */
    public function fitSize(string $text, float $maxWidth, float $preferred, float $minimum, string $font): float
    {
        for ($size = $preferred; $size > $minimum; $size -= 0.5) {
            if ($this->textWidth($text, $size, $font) <= $maxWidth) {
                return $size;
            }
        }

        return $minimum;
    }

    // ── Composites ───────────────────────────────────────────────────────────

    /**
     * A sticker: a rounded label, rotated a few degrees, pasted onto the card.
     *
     * Drawn into its own layer and rotated as a bitmap rather than by rotating the
     * text, because GD can rotate a string but not a rounded rectangle, and a
     * straight box behind a tilted word looks like a mistake.
     */
    public function sticker(
        string $label, float $centreX, float $centreY, float $angle,
        string $font, float $size, float $padX, float $padY, float $radius,
        ?string $fillHex, string $textHex, ?string $strokeHex = null, float $strokeWidth = 0.0,
    ): void {
        $textW = $this->textWidth($label, $size, $font);
        $w = $textW + $padX * 2;
        $h = $size * 1.32 + $padY * 2;

        $layer = new self((int) ceil($w), (int) ceil($h), $this->fontDir, $this->scale);
        imagealphablending($layer->im, false);
        imagefilledrectangle($layer->im, 0, 0, imagesx($layer->im), imagesy($layer->im), (int) imagecolorallocatealpha($layer->im, 0, 0, 0, 127));
        imagealphablending($layer->im, true);
        imagesavealpha($layer->im, true);

        if ($strokeHex !== null && $strokeWidth > 0) {
            $layer->roundedOutline(0, 0, $w, $h, $radius, $strokeHex, $strokeWidth, $fillHex);
        } elseif ($fillHex !== null) {
            $layer->roundedRect(0, 0, $w, $h, $radius, $fillHex);
        }

        $layer->text($label, $w / 2, $padY + $size * 0.11, $size, $font, $textHex, align: 'center');

        $rotated = imagerotate($layer->im, -$angle, (int) imagecolorallocatealpha($layer->im, 0, 0, 0, 127));

        if ($rotated === false) {
            return; // The layer's own destructor frees it.
        }

        imagealphablending($rotated, true);
        imagesavealpha($rotated, true);

        imagecopy(
            $this->im, $rotated,
            $this->px($centreX) - (int) (imagesx($rotated) / 2),
            $this->px($centreY) - (int) (imagesy($rotated) / 2),
            0, 0, imagesx($rotated), imagesy($rotated),
        );

        imagedestroy($rotated);
    }

    /**
     * The MetaCreator mark — the tapered wand and its four sparks.
     *
     * The geometry is the same maths as `apps/web/scripts/brand-mark.mjs`: quadratic
     * curves only, sampled into polygons here because GD has no curve primitive.
     * Drawn at four times its final size and resampled down, since a 44px logo made
     * of aliased polygons looks broken in a way a rectangle does not.
     */
    public function mark(float $x, float $y, float $size): void
    {
        $ss = 4;
        $box = (int) round($size * $this->scale * $ss);
        $unit = $box / 32;

        $layer = $this->blank($box, $box, transparent: true);
        imagealphablending($layer, false);
        imagesavealpha($layer, true);

        $rod = $this->rodPolygon(3.75, 27.6, 17.15, 14.2, 4.6, 1.1, $unit);
        $sparks = [];

        foreach ([[20.05, 11.1, 7.0], [26.05, 18.6, 3.8], [24.05, 5.6, 2.8], [13.35, 7.1, 2.4]] as [$cx, $cy, $r]) {
            $sparks[] = $this->sparkPolygon($cx, $cy, $r, $unit);
        }

        // Two gradients, exactly as the SVG defines them: the rod runs cobalt →
        // emerald along its own axis, the sparks run pale → deep across the burst.
        $this->gradientPolygon($layer, $rod, [3.75 * $unit, 27.6 * $unit], [17.15 * $unit, 14.2 * $unit], '#3d80f7', '#50e2a8');

        foreach ($sparks as $spark) {
            $this->gradientPolygon($layer, $spark, [13 * $unit, 4 * $unit], [30 * $unit, 22 * $unit], '#8df3c6', '#13c990');
        }

        imagealphablending($this->im, true);
        imagecopyresampled(
            $this->im, $layer,
            $this->px($x), $this->px($y),
            0, 0,
            (int) round($size * $this->scale), (int) round($size * $this->scale),
            $box, $box,
        );
        imagedestroy($layer);
    }

    // ── Output ───────────────────────────────────────────────────────────────

    /**
     * Resample to the real card size and encode.
     *
     * PNG first: the artwork is flat colour, gradients and type, which is what PNG
     * is good at and what JPEG turns to mush around letter edges. JPEG only wins
     * when the file would otherwise be heavy enough to slow a scraper down, so the
     * caller compares both and keeps the smaller — see {@see ToolSocialCard}.
     */
    public function encode(string $format = 'png', int $quality = 88): string
    {
        $flat = imagecreatetruecolor(max(1, $this->width), max(1, $this->height));

        if ($flat === false) {
            throw new RuntimeException('GD could not allocate the output canvas.');
        }

        imagealphablending($flat, true);
        imagecopyresampled(
            $flat, $this->im,
            0, 0, 0, 0,
            $this->width, $this->height,
            $this->width * $this->scale, $this->height * $this->scale,
        );

        ob_start();

        if ($format === 'jpeg') {
            imagejpeg($flat, null, $quality);
        } else {
            // Level 9 with the default filter: the slowest zlib setting still costs
            // milliseconds on an image this size, and every kilobyte saved is one a
            // crawler does not download on first share.
            imagepng($flat, null, 9);
        }

        $bytes = (string) ob_get_clean();
        imagedestroy($flat);

        return $bytes;
    }

    // ── Internals ────────────────────────────────────────────────────────────

    /**
     * Draw into a transparent layer of the given box, then composite it once.
     *
     * One composite is the whole point: a rounded rectangle is a rectangle plus
     * four ellipses, and drawing those directly with a translucent colour stacks
     * the alpha wherever they overlap — visible as bright corner blobs.
     */
    private function stamp(float $x, float $y, float $w, float $h, callable $draw): void
    {
        $lw = max(1, $this->px($w));
        $lh = max(1, $this->px($h));

        $layer = $this->blank($lw, $lh, transparent: true);
        imagealphablending($layer, false);

        $draw($layer, $lw, $lh);

        imagealphablending($this->im, true);
        imagecopy($this->im, $layer, $this->px($x), $this->px($y), 0, 0, $lw, $lh);
        imagedestroy($layer);
    }

    /** A rounded rectangle in device pixels, on an arbitrary image. */
    private function roundedOn(GdImage $im, int $x, int $y, int $w, int $h, int $r, int $colour): void
    {
        $r = (int) max(0, min($r, min($w, $h) / 2));
        $x2 = $x + $w - 1;
        $y2 = $y + $h - 1;

        imagefilledrectangle($im, $x + $r, $y, $x2 - $r, $y2, $colour);
        imagefilledrectangle($im, $x, $y + $r, $x2, $y2 - $r, $colour);

        if ($r > 0) {
            $d = $r * 2;

            foreach ([[$x + $r, $y + $r], [$x2 - $r, $y + $r], [$x + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) {
                imagefilledellipse($im, $cx, $cy, $d, $d, $colour);
            }
        }
    }

    /** Allocate a colour on an arbitrary image rather than the card. */
    private function on(GdImage $im, string $hex, float $alpha = 1.0): int
    {
        return $this->allocate($im, $hex, min($alpha, 0.999));
    }

    private function px(float $value): int
    {
        return (int) round($value * $this->scale);
    }

    /**
     * GD sizes type in points at 96 dpi, so a "54" it is handed comes out 72 pixels
     * tall. Everything in this file is stated in CSS pixels — the same numbers the
     * HTML design uses — and converted here, once, at the only place that matters.
     */
    private function points(float $sizeInPixels): float
    {
        return $sizeInPixels * $this->scale * 0.75;
    }

    private function colour(string $hex, float $alpha = 1.0): int
    {
        return $this->allocate($this->im, $hex, $alpha);
    }

    /** @return array{int<0, 255>, int<0, 255>, int<0, 255>} */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [
            $this->byte((float) hexdec(substr($hex, 0, 2))),
            $this->byte((float) hexdec(substr($hex, 2, 2))),
            $this->byte((float) hexdec(substr($hex, 4, 2))),
        ];
    }

    /** @return int<0, 255> */
    private function byte(float $value): int
    {
        return max(0, min(255, (int) round($value)));
    }

    /**
     * GD's alpha runs backwards — 0 is opaque, 127 is invisible.
     *
     * @return int<0, 127>
     */
    private function opacity(float $alpha): int
    {
        return max(0, min(127, (int) round(127 * (1 - max(0.0, min(1.0, $alpha))))));
    }

    /**
     * Allocate a colour, falling back to index 0 if GD refuses.
     *
     * Truecolor images never run out of colours, so the fallback is unreachable in
     * practice; it exists because the signature says `int|false` and swallowing that
     * at every call site would bury the twenty places it is used.
     */
    private function allocate(GdImage $im, string $hex, float $alpha): int
    {
        [$r, $g, $b] = $this->rgb($hex);

        $colour = $alpha >= 1.0
            ? imagecolorallocate($im, $r, $g, $b)
            : imagecolorallocatealpha($im, $r, $g, $b, $this->opacity($alpha));

        return $colour === false ? 0 : $colour;
    }

    /**
     * @param  list<string>  $stops
     * @return array{int<0, 255>, int<0, 255>, int<0, 255>}
     */
    private function rampAt(array $stops, float $t): array
    {
        $last = count($stops) - 1;

        if ($last <= 0) {
            return $this->rgb($stops[0]);
        }

        $pos = $t * $last;
        $i = min((int) floor($pos), $last - 1);
        $f = $pos - $i;
        $a = $this->rgb($stops[$i]);
        $b = $this->rgb($stops[$i + 1]);

        return [
            $this->byte($a[0] + ($b[0] - $a[0]) * $f),
            $this->byte($a[1] + ($b[1] - $a[1]) * $f),
            $this->byte($a[2] + ($b[2] - $a[2]) * $f),
        ];
    }

    private function blank(int $w, int $h, bool $transparent = false): GdImage
    {
        $im = imagecreatetruecolor(max(1, $w), max(1, $h));

        if ($im === false) {
            throw new RuntimeException('GD could not allocate a working image.');
        }

        if ($transparent) {
            imagealphablending($im, false);
            imagesavealpha($im, true);
            imagefilledrectangle($im, 0, 0, $w, $h, (int) imagecolorallocatealpha($im, 0, 0, 0, 127));
            imagealphablending($im, true);
        }

        return $im;
    }

    private function font(string $name): string
    {
        $file = $this->fontDir.'/'.$name.'.ttf';

        if (! is_file($file)) {
            throw new RuntimeException("Missing font: {$file}");
        }

        return $file;
    }

    /** Advance width of a run, cached because measuring dominates the draw cost. */
    private function advance(string $text, float $size, string $font): float
    {
        $key = $font.'|'.$size.'|'.$text;

        if (isset($this->fonts[$key])) {
            return $this->fonts[$key];
        }

        $box = imagettfbbox($this->points($size), 0, $this->font($font), $text);

        return $this->fonts[$key] = $box === false
            ? 0.0
            : ($box[2] - $box[0]) / $this->scale;
    }

    /**
     * Fill a polygon on `$layer` with a linear gradient between two points.
     *
     * The polygon is stencilled into a mask first and the colour written per pixel,
     * because GD fills with one colour and the mark's whole character is that the
     * wand changes hue along its length.
     *
     * @param  list<array{float, float}>  $points
     * @param  array{float, float}  $from
     * @param  array{float, float}  $to
     */
    private function gradientPolygon(GdImage $layer, array $points, array $from, array $to, string $startHex, string $endHex): void
    {
        $w = imagesx($layer);
        $h = imagesy($layer);

        $mask = $this->blank($w, $h);
        imagefilledrectangle($mask, 0, 0, $w, $h, (int) imagecolorallocate($mask, 0, 0, 0));

        $flat = [];

        foreach ($points as [$px, $py]) {
            $flat[] = (int) round($px);
            $flat[] = (int) round($py);
        }

        imagefilledpolygon($mask, $flat, (int) imagecolorallocate($mask, 255, 255, 255));

        $dx = $to[0] - $from[0];
        $dy = $to[1] - $from[1];
        $len2 = max($dx * $dx + $dy * $dy, 0.0001);

        // Only the polygon's own bounding box is walked; the mark is a small part
        // of a big layer and scanning all of it would be wasted work.
        $xs = array_column($points, 0);
        $ys = array_column($points, 1);

        if ($xs === [] || $ys === []) {
            imagedestroy($mask);

            return;
        }

        $x0 = max(0, (int) floor(min($xs)));
        $x1 = min($w - 1, (int) ceil(max($xs)));
        $y0 = max(0, (int) floor(min($ys)));
        $y1 = min($h - 1, (int) ceil(max($ys)));

        for ($y = $y0; $y <= $y1; $y++) {
            for ($x = $x0; $x <= $x1; $x++) {
                if ((imagecolorat($mask, $x, $y) & 0xFF) === 0) {
                    continue;
                }

                $t = max(0.0, min(1.0, (($x - $from[0]) * $dx + ($y - $from[1]) * $dy) / $len2));
                [$r, $g, $b] = $this->rampAt([$startHex, $endHex], $t);
                imagesetpixel($layer, $x, $y, (int) imagecolorallocate($layer, $r, $g, $b));
            }
        }

        imagedestroy($mask);
    }

    /**
     * Four-pointed spark. `$q` is the waist — control points at 0.2 × radius give
     * the pinched arms that read as a spark rather than a diamond.
     *
     * @return list<array{float, float}>
     */
    private function sparkPolygon(float $cx, float $cy, float $r, float $unit, float $q = 0.2): array
    {
        $pts = [];
        $ctl = [];

        for ($i = 0; $i < 4; $i++) {
            $a = deg2rad(-90 + $i * 90);
            $b = deg2rad(-90 + ($i + 0.5) * 90);
            $pts[] = [$cx + cos($a) * $r, $cy + sin($a) * $r];
            $ctl[] = [$cx + cos($b) * $r * $q, $cy + sin($b) * $r * $q];
        }

        $out = [];

        for ($i = 0; $i < 4; $i++) {
            $out = array_merge($out, $this->quad($pts[$i], $ctl[$i], $pts[($i + 1) % 4], $unit));
        }

        return $out;
    }

    /**
     * Tapered rod from a wide handle to a fine tip, with rounded caps.
     *
     * @return list<array{float, float}>
     */
    private function rodPolygon(float $x1, float $y1, float $x2, float $y2, float $w1, float $w2, float $unit): array
    {
        $dx = $x2 - $x1;
        $dy = $y2 - $y1;
        $len = sqrt($dx * $dx + $dy * $dy);
        $ux = $dx / $len;
        $uy = $dy / $len;
        $nx = -$uy;
        $ny = $ux;
        $r1 = $w1 / 2;
        $r2 = $w2 / 2;

        $a = [$x1 + $nx * $r1, $y1 + $ny * $r1];
        $b = [$x2 + $nx * $r2, $y2 + $ny * $r2];
        $c = [$x2 - $nx * $r2, $y2 - $ny * $r2];
        $d = [$x1 - $nx * $r1, $y1 - $ny * $r1];
        $tip = [$x2 + $ux * $r2 * 1.34, $y2 + $uy * $r2 * 1.34];
        $end = [$x1 - $ux * $r1 * 1.34, $y1 - $uy * $r1 * 1.34];

        return array_merge(
            [[$a[0] * $unit, $a[1] * $unit], [$b[0] * $unit, $b[1] * $unit]],
            $this->quad($b, $tip, $c, $unit),
            [[$d[0] * $unit, $d[1] * $unit]],
            $this->quad($d, $end, $a, $unit),
        );
    }

    /**
     * Sample a quadratic Bézier into points, in mark units scaled by `$unit`.
     *
     * @param  array{float, float}  $p0
     * @param  array{float, float}  $c
     * @param  array{float, float}  $p1
     * @return list<array{float, float}>
     */
    private function quad(array $p0, array $c, array $p1, float $unit, int $steps = 12): array
    {
        $points = [];

        for ($i = 1; $i <= $steps; $i++) {
            $t = $i / $steps;
            $mt = 1 - $t;
            $points[] = [
                ($mt * $mt * $p0[0] + 2 * $mt * $t * $c[0] + $t * $t * $p1[0]) * $unit,
                ($mt * $mt * $p0[1] + 2 * $mt * $t * $c[1] + $t * $t * $p1[1]) * $unit,
            ];
        }

        return $points;
    }
}
