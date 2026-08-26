<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Tools\Models\ToolCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ToolCategory>
 */
final class ToolCategoryFactory extends Factory
{
    /** @var class-string<ToolCategory> */
    protected $model = ToolCategory::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'slug' => Str::slug($name),
            'name' => Str::title($name),
            'tagline' => fake()->sentence(),
            'sort_order' => 0,
            'is_visible' => true,
        ];
    }
}
