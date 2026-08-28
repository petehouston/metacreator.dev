<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Access\Services\AuditLogger;
use App\Domain\Analytics\Data\Period;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\BillingReport;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\InvoiceDetailResource;
use App\Http\Resources\Admin\InvoiceResource;
use App\Http\Resources\Admin\PlanResource;
use App\Http\Resources\Admin\SubscriptionResource;
use App\Http\Resources\ApiCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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

    /**
     * One plan.
     *
     * The list already carries every field, but the edit screen is its own page now
     * rather than a drawer over the list — and a page that can only be reached by
     * first loading the list is a page that breaks on refresh and cannot be linked
     * to. This is what makes `/admin/billing/plans/{id}` an address.
     */
    public function showPlan(Plan $plan): PlanResource
    {
        return new PlanResource(
            // Same count the list computes, so `active_subscriptions` means the
            // same thing on both screens — and the editor's price lock agrees
            // with the badge the card showed a click earlier.
            $plan->loadCount([
                'subscriptions' => fn ($q) => $q->whereIn('stripe_status', ['active', 'trialing']),
            ])
        );
    }

    /**
     * Create a plan.
     *
     * A plan is ours, not the gateway's: it exists here first, and the identifier
     * the gateway knows it by is recorded per provider in `gateway_ids`. That is
     * what makes "switch from Stripe to PayPal" a settings change rather than a
     * re-modelling exercise.
     */
    public function storePlan(Request $request): PlanResource
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/', 'unique:plans,key'],
            'name' => ['required', 'string', 'max:120'],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:200'],
            'billing_mode' => ['required', 'in:subscription,one_time'],
            'interval' => ['nullable', 'required_if:billing_mode,subscription', 'in:day,week,month,year'],
            'interval_count' => ['sometimes', 'integer', 'min:1', 'max:36'],
            'amount' => ['required', 'integer', 'min:0', 'max:10000000'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'duration_days' => ['nullable', 'required_if:billing_mode,one_time', 'integer', 'min:1', 'max:3650'],
            'features' => ['sometimes', 'array', 'max:20'],
            'features.*' => ['string', 'max:200'],
            'limits' => ['sometimes', 'array'],
            'gateway_ids' => ['sometimes', 'array'],
            'gateway_ids.*' => ['nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
            'is_highlighted' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999'],
        ]);

        $plan = Plan::query()->create([
            'currency' => 'USD',
            'is_active' => true,
            ...$validated,
        ]);

        $this->audit->record('created', $plan, $request->user(), after: $validated);

        return new PlanResource($plan->loadCount('subscriptions'));
    }

    /**
     * Edit a plan.
     *
     * Price, interval and billing mode are the contract with everyone already on the
     * plan, so they are only editable while nobody is: re-pricing live subscribers
     * from an admin form is a chargeback, not a feature. Once a plan has subscribers
     * the way to change its price is a new plan — which is now one button away.
     */
    public function updatePlan(Request $request, Plan $plan): PlanResource
    {
        $locked = $plan->subscriptions()
            ->whereIn('stripe_status', ['active', 'trialing', 'past_due'])
            ->exists();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:200'],
            'features' => ['sometimes', 'array', 'max:20'],
            'features.*' => ['string', 'max:200'],
            'limits' => ['sometimes', 'array'],
            'gateway_ids' => ['sometimes', 'array'],
            'gateway_ids.*' => ['nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
            'is_highlighted' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999'],

            'amount' => [$locked ? 'prohibited' : 'sometimes', 'integer', 'min:0', 'max:10000000'],
            'interval' => [$locked ? 'prohibited' : 'sometimes', 'nullable', 'in:day,week,month,year'],
            'billing_mode' => [$locked ? 'prohibited' : 'sometimes', 'in:subscription,one_time'],
            'duration_days' => [$locked ? 'prohibited' : 'sometimes', 'nullable', 'integer', 'min:1', 'max:3650'],

            // The key identifies the plan in every record already written against it.
            'key' => ['prohibited'],
        ], [
            'amount.prohibited' => 'This plan has live subscribers, so its price is fixed. Create a new plan instead — the old one can be hidden so nobody new can buy it.',
            'interval.prohibited' => 'This plan has live subscribers, so its billing interval is fixed.',
            'billing_mode.prohibited' => 'This plan has live subscribers, so its billing mode is fixed.',
            'duration_days.prohibited' => 'This plan has live subscribers, so its duration is fixed.',
        ]);

        $before = $plan->only(array_keys($validated));

        $plan->fill($validated)->save();

        $this->audit->record('updated', $plan, $request->user(), before: $before, after: $validated);

        return new PlanResource($plan->loadCount('subscriptions'));
    }

    /**
     * Remove a plan.
     *
     * Only one nobody has ever bought. A plan with history is deactivated, not
     * deleted — every invoice and subscription that references it would otherwise
     * lose the only record of what was sold.
     */
    public function destroyPlan(Request $request, Plan $plan): JsonResponse
    {
        abort_if(
            $plan->subscriptions()->exists(),
            422,
            'This plan has subscriptions against it. Turn it off instead — deleting it would erase what those customers bought.',
        );

        $this->audit->record('deleted', $plan, $request->user(), before: ['key' => $plan->key, 'name' => $plan->name]);

        $plan->delete();

        return response()->json(status: 204);
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
     * One invoice, with everything needed to answer a customer about it.
     *
     * The lines, the plan, the subscription it renewed, the card it was taken from,
     * the transaction at the gateway and the refund — the set of things somebody
     * opens a support ticket about. Loading them all in one request is deliberate:
     * this screen is opened *because* there is a question, and a page that fills in
     * over four round trips is a page somebody screenshots half-loaded.
     */
    public function invoice(Invoice $invoice): InvoiceDetailResource
    {
        return new InvoiceDetailResource(
            $invoice->load([
                'user:id,ulid,email,display_name,name',
                'lines',
                'subscription:id,stripe_status,current_period_end,cancellation_reason',
                'plan:id,key,name,amount,currency,interval,billing_mode',
            ])
        );
    }

    /**
     * The billing report: revenue, subscriptions, and the breakdowns behind them.
     *
     * Gated on `invoices.view_any` rather than `analytics.view`: this is money, and
     * the marketing analyst who can read the funnel has no business reading the
     * customer revenue table.
     */
    public function report(Request $request, BillingReport $report): JsonResource
    {
        return new JsonResource($report->build(Period::fromRequest($request->query('period'))));
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
