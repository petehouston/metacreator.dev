<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Tools\Models\ToolCategory;
use Illuminate\Database\Seeder;

/**
 * Categories describe what a tool *does*, never which platform it is for.
 *
 * Platform is already a first-class facet: every tool carries `platforms[]` and the
 * catalog filters on it. Naming categories after platforms too meant one tool lived
 * in "Instagram" while another that also serves Instagram lived in "Content", and
 * the same filter appeared twice in the UI under two different names. So the
 * platform axis stays on `platforms`, and this axis answers "what kind of job is
 * this" — which is the question a category is good at.
 */
final class ToolCategorySeeder extends Seeder
{
    /** Mirrors the grouping in docs/07-tool-catalog.md. */
    private const CATEGORIES = [
        ['slug' => 'previews', 'name' => 'Previews', 'icon' => 'eye', 'accent_color' => '#0EA5E9',
            'tagline' => 'See the post, profile, link card or Pin exactly as the feed will draw it.'],
        ['slug' => 'content', 'name' => 'Content', 'icon' => 'pen-line', 'accent_color' => '#7C5CFF',
            'tagline' => 'Ideas, hooks, headlines and captions — the words that do the work.'],
        ['slug' => 'media', 'name' => 'Images & video', 'icon' => 'image', 'accent_color' => '#F59E0B',
            'tagline' => 'Resize, compress, convert and check assets for every platform at once.'],
        ['slug' => 'analytics', 'name' => 'Analytics & growth', 'icon' => 'trending-up', 'accent_color' => '#22C55E',
            'tagline' => 'Benchmarks, earnings estimates and the numbers brands ask for.'],
        ['slug' => 'utility', 'name' => 'Utilities', 'icon' => 'wrench', 'accent_color' => '#64748B',
            'tagline' => 'The small, sharp tools you reach for constantly.'],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $index => $category) {
            // `seedRow` rather than `updateOrCreate`: a name or accent colour
            // changed in the admin console belongs to whoever changed it, and a
            // deploy re-running this file must not undo it.
            ToolCategory::seedRow(
                ['slug' => $category['slug']],
                [...$category, 'sort_order' => $index, 'is_visible' => true],
            );
        }
    }

    /** @return list<string> */
    public static function slugs(): array
    {
        return array_column(self::CATEGORIES, 'slug');
    }
}
