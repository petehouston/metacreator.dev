<?php

declare(strict_types=1);

namespace App\Domain\Tools;

use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Exceptions\RunnerNotRegistered;
use App\Domain\Tools\Models\Tool;
use Illuminate\Contracts\Container\Container;

/**
 * Maps a catalog row's `key` to the class that executes it.
 *
 * Runners are registered by class name and resolved lazily through the container,
 * so booting the app never instantiates 60 runners to serve one request.
 */
final class ToolRegistry
{
    /** @var array<string, class-string<ToolRunner>> */
    private array $runners = [];

    public function __construct(private readonly Container $container) {}

    /**
     * @param  class-string<ToolRunner>  $runner
     */
    public function register(string $runner): void
    {
        $this->runners[$runner::key()] = $runner;
    }

    /**
     * @param  list<class-string<ToolRunner>>  $runners
     */
    public function registerMany(array $runners): void
    {
        foreach ($runners as $runner) {
            $this->register($runner);
        }
    }

    public function has(string $key): bool
    {
        return isset($this->runners[$key]);
    }

    /**
     * @throws RunnerNotRegistered
     */
    public function resolve(string $key): ToolRunner
    {
        $class = $this->runners[$key] ?? throw new RunnerNotRegistered($key);

        return $this->container->make($class);
    }

    public function for(Tool $tool): ToolRunner
    {
        return $this->resolve($tool->key);
    }

    /** @return list<string> Every registered key, sorted. */
    public function keys(): array
    {
        $keys = array_keys($this->runners);
        sort($keys);

        return $keys;
    }

    /**
     * Catalog rows whose runner is missing, and runners with no catalog row.
     *
     * Surfaced on the admin tools screen and asserted in a test, because either
     * direction of drift is a silent 500 waiting to happen.
     *
     * @return array{orphaned_rows: list<string>, unregistered_runners: list<string>}
     */
    public function drift(): array
    {
        $catalogKeys = Tool::query()->pluck('key')->all();
        $runnerKeys = $this->keys();

        return [
            'orphaned_rows' => array_values(array_diff($catalogKeys, $runnerKeys)),
            'unregistered_runners' => array_values(array_diff($runnerKeys, $catalogKeys)),
        ];
    }
}
