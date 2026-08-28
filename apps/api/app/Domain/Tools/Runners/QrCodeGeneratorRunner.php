<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\ResultArtifact;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Domain\Tools\Exceptions\ToolExecutionException;
use App\Support\Http\UrlGuard;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * A QR code in your own colours, at an error-correction level that survives being
 * printed on something.
 *
 * The level is the setting that matters and the one every generator hides. `L`
 * makes the smallest, prettiest code and stops working the moment a corner is
 * scuffed or a logo is dropped on it; `H` tolerates about 30% damage. Anything
 * going on a physical surface should be `Q` or `H`, so that is the default here.
 *
 * Output is SVG: it stays sharp on a billboard, and it is a fraction of the weight
 * of the PNG most tools hand you.
 */
final class QrCodeGeneratorRunner implements Cacheable, ToolRunner
{
    public static function key(): string
    {
        return 'media.qr-code-generator';
    }

    public function cacheTtl(): int
    {
        return 86400;
    }

    public function inputSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'required' => ['content'],
            'additionalProperties' => false,
            'properties' => [
                'content' => [
                    'type' => 'string',
                    'title' => 'Link or text',
                    'description' => 'A URL, a handle, a wifi string — anything a camera should read.',
                    'minLength' => 1,
                    'maxLength' => 1000,
                    'examples' => ['https://metacreator.dev'],
                ],
                'error_correction' => [
                    'type' => 'string',
                    'title' => 'Error correction',
                    'description' => 'L ≈ 7% damage tolerated, M ≈ 15%, Q ≈ 25%, H ≈ 30%. Print needs Q or H.',
                    'enum' => ['L', 'M', 'Q', 'H'],
                    'default' => 'Q',
                ],
                'foreground' => [
                    'type' => 'string',
                    'title' => 'Foreground colour',
                    'description' => 'Hex, e.g. #101828. Dark on light — never the other way round.',
                    'pattern' => '^#?[0-9a-fA-F]{6}$',
                    'maxLength' => 7,
                    'default' => '#101828',
                ],
                'background' => [
                    'type' => 'string',
                    'title' => 'Background colour',
                    'pattern' => '^#?[0-9a-fA-F]{6}$',
                    'maxLength' => 7,
                    'default' => '#FFFFFF',
                ],
                'size' => [
                    'type' => 'integer',
                    'title' => 'Size (px)',
                    'description' => 'SVG scales without loss; this only sets the default drawing size.',
                    'minimum' => 200,
                    'maximum' => 2000,
                    'default' => 600,
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $content = trim($input->string('content'));
        $level = $input->string('error_correction', 'Q');
        $size = $input->int('size', 600);

        $foreground = self::rgb($input->string('foreground', '#101828'));
        $background = self::rgb($input->string('background', '#FFFFFF'));

        // A QR code is a link nobody can read before they follow it, so a code
        // pointing at a private address is checked here. Unresolvable *names* are
        // deliberately allowed: a domain that is not live yet is a normal thing to
        // print a code for, and nothing is ever fetched.
        $host = (string) parse_url($content, PHP_URL_HOST);

        if ($host !== '' && ! self::hostIsSafeToEmit($host)) {
            throw ToolExecutionException::invalidInput(
                'That address is on a private network, so a code pointing at it would not work for anyone else.',
                ['content' => 'Not a public address.'],
            );
        }

        $writer = new Writer(new ImageRenderer(
            new RendererStyle(
                size: $size,
                margin: 2,
                fill: Fill::uniformColor($background, $foreground),
            ),
            new SvgImageBackEnd,
        ));

        $svg = $writer->writeString($content, ecLevel: self::level($level));
        $uri = 'data:image/svg+xml;base64,'.base64_encode($svg);

        $contrast = self::contrast($input->string('foreground', '#101828'), $input->string('background', '#FFFFFF'));

        return ToolResult::media(
            [new ResultArtifact(
                key: 'qr',
                filename: 'qr-code.svg',
                mimeType: 'image/svg+xml',
                size: strlen($svg),
                url: $uri,
                width: $size,
                height: $size,
                label: "QR code — level {$level}, {$size} × {$size}",
                previewUrl: $uri,
            )],
            summary: 'Scannable SVG for '.(mb_strlen($content) > 60 ? mb_substr($content, 0, 60).'…' : $content).'.',
        )->withWarnings(array_values(array_filter([
            $contrast < 4.5
                ? 'Foreground and background are too close in brightness (contrast '.round($contrast, 1)
                .':1). Most phone cameras will not lock onto this. Aim for at least 4.5:1.'
                : null,
            $level === 'L'
                ? 'Level L tolerates about 7% damage. Fine on a screen, unreliable on anything printed, '
                .'curved or covered by a logo.'
                : null,
            mb_strlen($content) > 300
                ? 'Long contents make a dense code that needs to be printed large to scan. Point the code '
                .'at a short URL instead of embedding the whole payload.'
                : null,
            'Test it with the camera app of a phone that is not yours, at the distance people will '
            .'actually stand. A code that scans on your desk can fail on a poster.',
        ])))->withMeta([
            'error_correction' => $level,
            'contrast_ratio' => round($contrast, 2),
        ]);
    }

    /** Literal private addresses only; a name we cannot resolve is still fine to print. */
    private static function hostIsSafeToEmit(string $host): bool
    {
        $host = trim($host, '[]');

        if (in_array(strtolower($host), ['localhost', 'localhost.localdomain'], true)) {
            return false;
        }

        return filter_var($host, FILTER_VALIDATE_IP) === false || UrlGuard::addressIsPublic($host);
    }

    /** BaconQrCode predates native enums, so the levels are static factories. */
    private static function level(string $level): ErrorCorrectionLevel
    {
        return match ($level) {
            'L' => ErrorCorrectionLevel::L(),
            'M' => ErrorCorrectionLevel::M(),
            'H' => ErrorCorrectionLevel::H(),
            default => ErrorCorrectionLevel::Q(),
        };
    }

    private static function rgb(string $hex): Rgb
    {
        $hex = ltrim(trim($hex), '#');

        if (preg_match('/^[0-9a-fA-F]{6}$/', $hex) !== 1) {
            throw ToolExecutionException::invalidInput(
                'Colours must be six-digit hex, like #101828.',
                ['foreground' => 'Use a six-digit hex colour.'],
            );
        }

        return new Rgb(
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        );
    }

    private static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    private static function luminance(string $hex): float
    {
        $hex = ltrim(trim($hex), '#');

        if (preg_match('/^[0-9a-fA-F]{6}$/', $hex) !== 1) {
            return 0.0;
        }

        $channels = array_map(function (string $pair): float {
            $v = hexdec($pair) / 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, str_split($hex, 2));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
