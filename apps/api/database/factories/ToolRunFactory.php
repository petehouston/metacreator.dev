<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Tools\Enums\AccessReason;
use App\Domain\Tools\Enums\RunStatus;
use App\Domain\Tools\Models\Tool;
use App\Domain\Tools\Models\ToolRun;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ToolRun>
 */
final class ToolRunFactory extends Factory
{
    /** @var class-string<ToolRun> */
    protected $model = ToolRun::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'ulid' => strtoupper((string) Str::ulid()),
            'tool_id' => Tool::factory(),
            'tool_version' => 1,
            'user_id' => null,
            'visitor_hash' => hash('sha256', fake()->uuid()),
            'status' => RunStatus::Succeeded,
            'access_reason' => AccessReason::Free,
            'input_hash' => hash('sha256', fake()->sentence()),
            'duration_ms' => fake()->numberBetween(10, 2000),
            'cache_hit' => false,
            'finished_at' => now(),
        ];
    }

    public function failed(string $code = 'tool.failed'): self
    {
        return $this->state(fn () => [
            'status' => RunStatus::Failed,
            'error_code' => $code,
            'error_message' => 'Something went wrong.',
        ]);
    }

    public function cached(): self
    {
        return $this->state(fn () => ['cache_hit' => true, 'duration_ms' => 0]);
    }
}
