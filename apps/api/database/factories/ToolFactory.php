<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Tools\Enums\ToolStatus;
use App\Domain\Tools\Enums\ToolTier;
use App\Domain\Tools\Models\Tool;
use App\Domain\Tools\Models\ToolCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tool>
 */
final class ToolFactory extends Factory
{
    /** @var class-string<Tool> */
    protected $model = Tool::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        /** @var list<string> $words */
        $words = fake()->unique()->words(3);
        $name = implode(' ', $words);

        return [
            'ulid' => strtoupper((string) Str::ulid()),
            'slug' => Str::slug($name),
            'key' => 'test.'.Str::slug($name, '_'),
            'category_id' => ToolCategory::factory(),
            'name' => Str::title($name),
            'tagline' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'tier' => ToolTier::Free,
            'status' => ToolStatus::Published,
            'is_visible' => true,
            'version' => 1,
            'platforms' => ['youtube'],
            'input_schema' => [
                'type' => 'object',
                'properties' => ['input' => ['type' => 'string', 'title' => 'Input']],
            ],
            'published_at' => now(),
        ];
    }

    public function tier(ToolTier $tier): self
    {
        return $this->state(fn () => ['tier' => $tier]);
    }

    /** Hidden by an admin: invisible to the public, still runnable by staff. */
    public function hidden(): self
    {
        return $this->state(fn () => ['is_visible' => false, 'status' => ToolStatus::Hidden]);
    }

    public function featured(): self
    {
        return $this->state(fn () => ['featured_at' => now()]);
    }
}
