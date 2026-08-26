<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use Illuminate\Support\Str;

/**
 * Builds portable block JSON (ADR 0003) for seeded content.
 *
 * The same format the editor produces and the frontend renders, so seeded tool
 * instructions are indistinguishable from content an editor wrote.
 */
final class Blocks
{
    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return array<string, mixed>
     */
    public static function make(array $blocks): array
    {
        return ['version' => 1, 'blocks' => $blocks];
    }

    /** @return array<string, mixed> */
    public static function paragraph(string $html): array
    {
        return self::block('paragraph', ['html' => "<p>{$html}</p>"]);
    }

    /** @return array<string, mixed> */
    public static function heading(string $text, int $level = 2): array
    {
        return self::block('heading', ['level' => $level, 'text' => $text]);
    }

    /**
     * @param  list<string>  $items
     * @return array<string, mixed>
     */
    public static function list(array $items, string $style = 'unordered'): array
    {
        return self::block('list', ['style' => $style, 'items' => $items]);
    }

    /** @return array<string, mixed> */
    public static function quote(string $text, ?string $cite = null): array
    {
        return self::block('quote', ['text' => $text, 'cite' => $cite]);
    }

    /**
     * @param  'info'|'tip'|'warning'|'danger'  $tone
     * @return array<string, mixed>
     */
    public static function callout(string $tone, string $html): array
    {
        return self::block('callout', ['tone' => $tone, 'html' => "<p>{$html}</p>"]);
    }

    /** @return array<string, mixed> */
    public static function code(string $code, string $language = 'text', ?string $filename = null): array
    {
        return self::block('code', ['language' => $language, 'code' => $code, 'filename' => $filename]);
    }

    /** @return array<string, mixed> */
    public static function toolCard(string $slug): array
    {
        return self::block('toolCard', ['toolSlug' => $slug]);
    }

    /** @return array<string, mixed> */
    public static function embed(string $provider, string $url): array
    {
        return self::block('embed', ['provider' => $provider, 'url' => $url, 'aspect' => '16:9']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function block(string $type, array $data): array
    {
        return ['id' => 'b_'.strtoupper((string) Str::ulid()), 'type' => $type, 'data' => $data];
    }
}
