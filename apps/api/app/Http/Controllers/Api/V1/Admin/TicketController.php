<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Access\Services\AuditLogger;
use App\Domain\Notifications\Notifier;
use App\Domain\Support\Enums\TicketStatus;
use App\Domain\Support\Models\Ticket;
use App\Domain\Support\Models\TicketMessage;
use App\Domain\Users\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminTicketResource;
use App\Http\Resources\ApiCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The support queue.
 *
 * Ordered by what is most likely to burn someone: overdue first, then priority,
 * then how long it has been since anyone touched it. A queue sorted by creation
 * date looks tidy and lets the urgent ticket sit at the bottom.
 */
final class TicketController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly Notifier $notifier,
    ) {}

    /** @return ApiCollection<AdminTicketResource> */
    public function index(Request $request): ApiCollection
    {
        $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:180'],
            'filter.status' => ['sometimes', 'nullable', 'in:open,pending,on_hold,solved,closed,unassigned,mine'],
            'filter.priority' => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);

        $scope = (string) $request->input('filter.status', '');

        $tickets = Ticket::query()
            ->with(['user:id,ulid,email,display_name,name', 'assignee:id,ulid,display_name,name'])
            ->withCount('messages')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($sub) => $sub
                ->where('reference', 'like', '%'.$request->string('q').'%')
                ->orWhere('subject', 'like', '%'.$request->string('q').'%')
                ->orWhereRelation('user', 'email', 'like', '%'.$request->string('q').'%')))
            ->when($scope === 'unassigned', fn ($q) => $q->whereNull('assigned_to')->open())
            ->when($scope === 'mine', fn ($q) => $q->where('assigned_to', $request->user()?->id))
            ->when(
                $scope !== '' && ! in_array($scope, ['unassigned', 'mine'], true),
                fn ($q) => $q->where('status', $scope)
            )
            ->when(
                $request->filled('filter.priority'),
                fn ($q) => $q->where('priority', $request->string('filter.priority'))
            )
            ->orderByRaw('CASE WHEN due_at IS NOT NULL AND due_at < NOW() AND status IN (?, ?) THEN 0 ELSE 1 END', [
                TicketStatus::Open->value, TicketStatus::OnHold->value,
            ])
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")
            ->orderBy('last_activity_at')
            ->paginate(perPage: min(100, $request->integer('per_page', 25)))
            ->withQueryString();

        return (new ApiCollection($tickets, AdminTicketResource::class))->additional([
            'meta' => ['counts' => $this->queueCounts($request)],
        ]);
    }

    public function show(Ticket $ticket): AdminTicketResource
    {
        return new AdminTicketResource($ticket->load([
            'user:id,ulid,email,display_name,name',
            'assignee:id,ulid,display_name,name',
            'messages.author:id,ulid,display_name,name',
        ]));
    }

    public function update(Request $request, Ticket $ticket): AdminTicketResource
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'in:open,pending,on_hold,solved,closed'],
            'priority' => ['sometimes', 'in:low,normal,high,urgent'],
            'assigned_to' => ['sometimes', 'nullable', 'string', 'max:60'],
            'due_at' => ['sometimes', 'nullable', 'date'],
        ]);

        $before = $ticket->only(['status', 'priority', 'assigned_to', 'due_at']);

        if (array_key_exists('assigned_to', $validated)) {
            $assignee = $validated['assigned_to'] === null
                ? null
                : User::findByPublicId($validated['assigned_to']);

            abort_if(
                $validated['assigned_to'] !== null && $assignee === null,
                422,
                'That person could not be found.'
            );

            abort_if(
                $assignee !== null && ! $assignee->can('tickets.view'),
                422,
                'Tickets can only be assigned to someone who can work them.'
            );

            $validated['assigned_to'] = $assignee?->id;
        }

        if (($validated['status'] ?? null) !== null) {
            $status = TicketStatus::from($validated['status']);

            // Stamped once, when it first happens: an SLA report that recomputes
            // resolution time every time a ticket is reopened is a report that
            // flatters itself.
            $validated['resolved_at'] = $status->isResolved()
                ? ($ticket->resolved_at ?? now())
                : null;
        }

        $ticket->fill([...$validated, 'last_activity_at' => now()])->save();

        $this->audit->record('updated', $ticket, $request->user(), before: $before, after: $validated);

        if (($validated['status'] ?? null) !== null && $ticket->status->isResolved() && $ticket->user !== null) {
            $this->notifier->send($ticket->user, 'support.solved', [
                'reference' => $ticket->reference,
                'subject' => $ticket->subject,
            ], actionUrl: config('app.frontend_url')."/dashboard/support/{$ticket->public_id}");
        }

        return $this->show($ticket->refresh());
    }

    /**
     * A reply, or an internal note.
     *
     * The distinction is the single most consequential flag on this screen — an
     * internal note leaking to a customer is a real incident — so it is explicit in
     * the payload and never inferred from who is writing.
     */
    public function reply(Request $request, Ticket $ticket): JsonResource
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:20000'],
            'is_internal_note' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'in:open,pending,on_hold,solved,closed'],
        ]);

        $internal = (bool) ($validated['is_internal_note'] ?? false);

        $message = TicketMessage::query()->create([
            'ticket_id' => $ticket->id,
            'author_id' => $request->user()?->id,
            'author_type' => $internal ? 'note' : 'agent',
            'body' => $validated['body'],
            'is_internal_note' => $internal,
        ]);

        $ticket->forceFill([
            'last_activity_at' => now(),
            // First response is only "first" once, and only a real reply counts —
            // an internal note is not an answer to the customer.
            'first_response_at' => $internal ? $ticket->first_response_at : ($ticket->first_response_at ?? now()),
            'status' => $validated['status'] ?? ($internal ? $ticket->status->value : TicketStatus::Pending->value),
        ])->save();

        $this->audit->record(
            $internal ? 'note_added' : 'replied',
            $ticket,
            $request->user(),
            after: ['message_id' => $message->id],
        );

        if (! $internal && $ticket->user !== null) {
            $this->notifier->send($ticket->user, 'support.staff_replied', [
                'reference' => $ticket->reference,
                'subject' => $ticket->subject,
                'author' => $request->user()?->displayName() ?? 'Our team',
            ], actionUrl: config('app.frontend_url')."/dashboard/support/{$ticket->public_id}");
        }

        return $this->show($ticket->refresh());
    }

    /** @return array<string, int> */
    private function queueCounts(Request $request): array
    {
        return [
            'open' => Ticket::query()->where('status', TicketStatus::Open->value)->count(),
            'pending' => Ticket::query()->where('status', TicketStatus::Pending->value)->count(),
            'on_hold' => Ticket::query()->where('status', TicketStatus::OnHold->value)->count(),
            'unassigned' => Ticket::query()->whereNull('assigned_to')->open()->count(),
            'mine' => Ticket::query()->where('assigned_to', $request->user()?->id)->open()->count(),
            'overdue' => Ticket::query()->overdue()->count(),
        ];
    }
}
