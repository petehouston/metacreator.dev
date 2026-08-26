<?php

declare(strict_types=1);

namespace App\Domain\Tools\Contracts;

use App\Domain\Tools\Actions\RunToolAction;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Domain\Tools\Exceptions\ToolExecutionException;

/**
 * The one thing every tool must implement.
 *
 * A runner is deliberately powerless: it cannot check access, consume quota or
 * record telemetry, because the only path to `run()` is through
 * {@see RunToolAction}, which does all three first.
 * That is what stops the 60th tool from forgetting a check the 1st tool made.
 *
 * Runners must be **pure with respect to their input**: the same input at the same
 * tool version must produce the same output, because that assumption is what makes
 * result caching safe.
 */
interface ToolRunner
{
    /**
     * Stable registry key. Matches `tools.key` and never changes once published —
     * changing it orphans the catalog row.
     */
    public static function key(): string;

    /**
     * JSON Schema (draft 2020-12) describing the accepted input.
     *
     * This single definition drives server-side validation *and* the generated
     * frontend form, so the two cannot drift apart. Use `title`, `description`,
     * `examples` and `default` generously — the form generator reads them.
     *
     * @return array<string, mixed>
     */
    public function inputSchema(): array;

    /**
     * Execute the tool.
     *
     * Throw {@see ToolExecutionException} for expected
     * failures (bad upstream data, unreachable URL). Anything else is treated as a
     * bug, reported to Sentry, and surfaced as a generic error.
     */
    public function run(ToolInput $input, RunContext $context): ToolResult;
}
