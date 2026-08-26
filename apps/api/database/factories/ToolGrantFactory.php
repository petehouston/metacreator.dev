<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Tools\Models\Tool;
use App\Domain\Tools\Models\ToolGrant;
use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ToolGrant>
 */
final class ToolGrantFactory extends Factory
{
    /** @var class-string<ToolGrant> */
    protected $model = ToolGrant::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tool_id' => Tool::factory(),
            'reason' => 'Comped by support',
            'expires_at' => now()->addMonth(),
        ];
    }

    public function permanent(): self
    {
        return $this->state(fn () => ['expires_at' => null]);
    }

    public function expired(): self
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }
}
