<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Newsletter\Models\NewsletterSubscriber;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\NewsletterSubscriberResource;
use App\Http\Resources\ApiCollection;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The list, and the state of its sync with whichever provider is configured.
 *
 * `sync_status` is surfaced prominently because a provider integration that has
 * been quietly failing for a week is indistinguishable from one that is working,
 * right up until a campaign goes out to a third of the list.
 */
final class NewsletterController extends Controller
{
    /** @return ApiCollection<NewsletterSubscriberResource> */
    public function index(Request $request): ApiCollection
    {
        $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:180'],
            'filter.status' => ['sometimes', 'nullable', 'in:pending,subscribed,unsubscribed,bounced'],
            'filter.sync' => ['sometimes', 'nullable', 'in:pending,synced,failed'],
        ]);

        $subscribers = NewsletterSubscriber::query()
            ->when($request->filled('q'), fn ($q) => $q->search((string) $request->string('q')))
            ->when(
                $request->filled('filter.status'),
                fn ($q) => $q->where('status', $request->string('filter.status'))
            )
            ->when(
                $request->filled('filter.sync'),
                fn ($q) => $q->where('sync_status', $request->string('filter.sync'))
            )
            ->latest('id')
            ->paginate(perPage: min(100, $request->integer('per_page', 30)))
            ->withQueryString();

        return (new ApiCollection($subscribers, NewsletterSubscriberResource::class))->additional([
            'meta' => ['counts' => $this->counts()],
        ]);
    }

    /**
     * Streamed rather than built in memory: a list of a hundred thousand addresses
     * is a perfectly ordinary thing to export and a perfectly ordinary way to
     * exhaust `memory_limit`.
     */
    public function export(Request $request): StreamedResponse
    {
        $filename = 'subscribers-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['email', 'name', 'status', 'source', 'confirmed_at', 'created_at']);

            NewsletterSubscriber::query()
                ->orderBy('id')
                ->chunk(1000, function ($chunk) use ($handle): void {
                    foreach ($chunk as $subscriber) {
                        fputcsv($handle, [
                            $subscriber->email,
                            $subscriber->name,
                            $subscriber->status,
                            $subscriber->source,
                            $subscriber->confirmed_at?->toIso8601String(),
                            $subscriber->created_at?->toIso8601String(),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        return [
            'subscribed' => NewsletterSubscriber::query()->where('status', 'subscribed')->count(),
            'pending' => NewsletterSubscriber::query()->where('status', 'pending')->count(),
            'unsubscribed' => NewsletterSubscriber::query()->where('status', 'unsubscribed')->count(),
            'bounced' => NewsletterSubscriber::query()->where('status', 'bounced')->count(),
            'sync_failed' => NewsletterSubscriber::query()->where('sync_status', 'failed')->count(),
        ];
    }
}
