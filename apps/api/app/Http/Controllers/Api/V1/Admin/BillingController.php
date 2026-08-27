<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Access\Services\AuditLogger;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\InvoiceResource;
use App\Http\Resources\Admin\PlanResource;
use App\Http\Resources\Admin\SubscriptionResource;
use App\Http\Resources\ApiCollection;
use Illuminate\Http\Request;

/**
 * The accountant's surface: plans, subscriptions, invoices.
 *
 * Everything Stripe owns is read-only here. Editing a subscription's period or an
 * invoice's total in our database would only make the two systems disagree — the
 * writes that matter go to Stripe and come back through the webhook. What an
 * accountant *can* change is the plan definition: price, features, visibility.
 */
final class BillingController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @return ApiCollection<PlanResource> */
    public function plans(): ApiCollection
    {
        $plans = Plan::query()
            ->withCount(['subscriptions' => fn ($q) => $q->whereIn('stripe_status', ['active', 'trialing'])])
            ->orderBy('sort_order')
            ->get();

        return new ApiCollection($plans, PlanResource::class);
    }

    public function updatePlan(Request $request, Plan $plan): PlanResource
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:200'],
            'amount' => ['sometimes', 'integer', 'min:0', 'max:10000000'],
            'features' => ['sometimes', 'array', 'max:20'],
            'features.*' => ['string', 'max:200'],
            'limits' => ['sometimes', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'is_highlighted' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999'],

            // The price id, the interval and the key are the contract with Stripe and
            // with every subscription already on the plan. Changing them here would
            // silently re-price existing customers.
            'key' => ['prohibited'],
            'interval' => ['prohibited'],
            'stripe_price_id' => ['prohibited'],
        ]);

        $before = $plan->only(array_keys($validated));

        $plan->fill($validated)->save();

        $this->audit->record('updated', $plan, $request->user(), before: $before, after: $validated);

        return new PlanResource($plan->loadCount('subscriptions'));
    }

    /** @return ApiCollection<SubscriptionResource> */
    public function subscriptions(Request $request): ApiCollection
    {
        $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:180'],
            'filter.status' => ['sometimes', 'nullable', 'string', 'max:30'],
        ]);

        $subscriptions = Subscription::query()
            ->with(['user:id,ulid,email,display_name,name', 'plan:id,key,name,amount,currency,interval'])
            ->when($request->filled('q'), fn ($q) => $q->whereRelation(
                'user', 'email', 'like', '%'.$request->string('q').'%'
            ))
            ->when(
                $request->filled('filter.status'),
                fn ($q) => $q->where('stripe_status', $request->string('filter.status'))
            )
            ->latest('id')
            ->paginate(perPage: min(100, $request->integer('per_page', 25)))
            ->withQueryString();

        return new ApiCollection($subscriptions, SubscriptionResource::class);
    }

    /** @return ApiCollection<InvoiceResource> */
    public function invoices(Request $request): ApiCollection
    {
        $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:180'],
            'filter.status' => ['sometimes', 'nullable', 'string', 'max:30'],
        ]);

        $invoices = Invoice::query()
            ->with('user:id,ulid,email,display_name,name')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($sub) => $sub
                ->where('number', 'like', '%'.$request->string('q').'%')
                ->orWhereRelation('user', 'email', 'like', '%'.$request->string('q').'%')))
            ->when(
                $request->filled('filter.status'),
                fn ($q) => $q->where('status', $request->string('filter.status'))
            )
            ->latest('issued_at')
            ->paginate(perPage: min(100, $request->integer('per_page', 25)))
            ->withQueryString();

        return (new ApiCollection($invoices, InvoiceResource::class))->additional([
            'meta' => ['totals' => $this->invoiceTotals($request)],
        ]);
    }

    /**
     * Money on the page has to add up to something. Totals are for the *filtered*
     * set, not the current page — a sum of twenty-five rows out of four hundred is
     * a number that means nothing and looks like it means everything.
     *
     * @return array<string, int|string>
     */
    private function invoiceTotals(Request $request): array
    {
        $query = Invoice::query()
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($sub) => $sub
                ->where('number', 'like', '%'.$request->string('q').'%')
                ->orWhereRelation('user', 'email', 'like', '%'.$request->string('q').'%')))
            ->when(
                $request->filled('filter.status'),
                fn ($q) => $q->where('status', $request->string('filter.status'))
            );

        return [
            'count' => (clone $query)->count(),
            'paid' => (int) (clone $query)->whereNotNull('paid_at')->sum('total'),
            'refunded' => (int) (clone $query)->sum('amount_refunded'),
            'outstanding' => (int) (clone $query)->whereNull('paid_at')->sum('total'),
            'currency' => (string) ((clone $query)->value('currency') ?? 'USD'),
        ];
    }
}
