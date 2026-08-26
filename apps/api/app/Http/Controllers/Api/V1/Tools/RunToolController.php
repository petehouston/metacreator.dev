<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tools;

use App\Domain\Tools\Actions\RunToolAction;
use App\Domain\Tools\Enums\RunStatus;
use App\Domain\Tools\Models\Tool;
use App\Domain\Tools\Models\ToolRun;
use App\Domain\Tools\Services\ArtifactStore;
use App\Http\Controllers\Controller;
use App\Http\Middleware\IdentifyVisitor;
use App\Http\Resources\ToolRunResource;
use Illuminate\Http\Request;

/**
 * The single execution endpoint for all 60+ tools.
 *
 * There is deliberately no per-tool controller: access, quota, validation, caching
 * and telemetry are enforced once in {@see RunToolAction}, which is the only way to
 * reach a runner (see ADR 0002).
 */
final class RunToolController extends Controller
{
    public function __invoke(Request $request, string $slug, RunToolAction $action): ToolRunResource
    {
        $tool = Tool::query()->where('slug', $slug)->firstOrFail();

        $run = $action->handle(
            tool: $tool,
            payload: (array) $request->input('input', []),
            user: $request->user(),
            visitorHash: (string) $request->attributes->get(IdentifyVisitor::ATTRIBUTE),
            referrerSource: $request->string('source')->limit(60, '')->toString() ?: null,
        );

        return new ToolRunResource($run);
    }

    /**
     * Poll an asynchronous run.
     *
     * Guests may only read a run they can name — the ULID is unguessable — and only
     * a run that has no owner, so one visitor cannot read another's results.
     */
    public function show(Request $request, string $ulid, ArtifactStore $artifacts): ToolRunResource
    {
        $run = ToolRun::query()
            ->with('tool:id,slug,name,version')
            ->where('ulid', strtoupper($ulid))
            ->firstOrFail();

        abort_unless(
            $run->user_id === null || $run->user_id === $request->user()?->id,
            404,
        );

        if ($run->status === RunStatus::Succeeded && $run->result_ref !== null) {
            $run->setAttribute('stored_result', $artifacts->retrieve($run));
        }

        return new ToolRunResource($run);
    }
}
