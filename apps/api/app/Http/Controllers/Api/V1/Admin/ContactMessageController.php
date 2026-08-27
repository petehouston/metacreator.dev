<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Support\Models\ContactMessage;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ContactMessageResource;
use App\Http\Resources\ApiCollection;
use Illuminate\Http\Request;

/** The public contact form's inbox — triage, not ticketing. */
final class ContactMessageController extends Controller
{
    /** @return ApiCollection<ContactMessageResource> */
    public function index(Request $request): ApiCollection
    {
        $request->validate([
            'filter.state' => ['sometimes', 'nullable', 'in:unhandled,handled'],
            'q' => ['sometimes', 'nullable', 'string', 'max:180'],
        ]);

        $messages = ContactMessage::query()
            ->with('handler:id,display_name,name,email')
            ->when($request->input('filter.state') === 'unhandled', fn ($q) => $q->unhandled())
            ->when($request->input('filter.state') === 'handled', fn ($q) => $q->whereNotNull('handled_at'))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($sub) => $sub
                ->where('email', 'like', '%'.$request->string('q').'%')
                ->orWhere('subject', 'like', '%'.$request->string('q').'%')))
            ->latest('id')
            ->paginate(perPage: min(100, $request->integer('per_page', 25)))
            ->withQueryString();

        return (new ApiCollection($messages, ContactMessageResource::class))->additional([
            'meta' => ['counts' => ['unhandled' => ContactMessage::query()->unhandled()->count()]],
        ]);
    }

    /** Toggle, not a one-way flag: triage mistakes are normal and reversible. */
    public function handled(Request $request, ContactMessage $message): ContactMessageResource
    {
        $message->forceFill([
            'handled_at' => $message->handled_at === null ? now() : null,
            'handled_by' => $message->handled_at === null ? $request->user()?->id : null,
        ])->save();

        return new ContactMessageResource($message->load('handler'));
    }
}
