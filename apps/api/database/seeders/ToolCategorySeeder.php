<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Tools\Models\ToolCategory;
use Illuminate\Database\Seeder;

final class ToolCategorySeeder extends Seeder
{
    /** Mirrors the grouping in docs/07-tool-catalog.md. */
    private const CATEGORIES = [
        ['slug' => 'youtube', 'name' => 'YouTube', 'icon' => 'youtube', 'accent_color' => '#FF0033',
            'tagline' => 'Rank videos, audit channels and design thumbnails that earn the click.'],
        ['slug' => 'instagram', 'name' => 'Instagram', 'icon' => 'instagram', 'accent_color' => '#E1306C',
            'tagline' => 'Plan the grid, size every asset and price your collaborations properly.'],
        ['slug' => 'tiktok', 'name' => 'TikTok', 'icon' => 'music', 'accent_color' => '#00F2EA',
            'tagline' => 'Sharpen hooks, find rising sounds and understand what actually retains.'],
        ['slug' => 'x', 'name' => 'X / Twitter', 'icon' => 'twitter', 'accent_color' => '#1D9BF0',
            'tagline' => 'Write threads that read well and posts that fit the first time.'],
        ['slug' => 'facebook', 'name' => 'Facebook', 'icon' => 'facebook', 'accent_color' => '#1877F2',
            'tagline' => 'Preview posts and ads exactly as the feed will render them.'],
        ['slug' => 'linkedin', 'name' => 'LinkedIn', 'icon' => 'linkedin', 'accent_color' => '#0A66C2',
            'tagline' => 'Get above the fold, and turn documents into carousels that perform.'],
        ['slug' => 'content', 'name' => 'Content & writing', 'icon' => 'pen-line', 'accent_color' => '#7C5CFF',
            'tagline' => 'Ideas, hooks, headlines and captions — the words that do the work.'],
        ['slug' => 'media', 'name' => 'Images & video', 'icon' => 'image', 'accent_color' => '#F59E0B',
            'tagline' => 'Resize, compress, convert and check assets for every platform at once.'],
        ['slug' => 'analytics', 'name' => 'Analytics & growth', 'icon' => 'trending-up', 'accent_color' => '#22C55E',
            'tagline' => 'Benchmarks, tracking and the numbers brands ask for.'],
        ['slug' => 'utility', 'name' => 'Utilities', 'icon' => 'wrench', 'accent_color' => '#64748B',
            'tagline' => 'The small, sharp tools you reach for constantly.'],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $index => $category) {
            ToolCategory::query()->updateOrCreate(
                ['slug' => $category['slug']],
                [...$category, 'sort_order' => $index, 'is_visible' => true],
            );
        }
    }
}
