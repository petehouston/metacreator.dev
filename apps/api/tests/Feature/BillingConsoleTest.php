<?php

declare(strict_types=1);

use App\Domain\Access\Actions\SyncRolesAndPermissions;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\InvoiceLine;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Settings\Setting;
use App\Domain\Users\Models\User;
use Database\Seeders\SettingsSeeder;

/**
 * The billing console: the invoice detail page and the report behind it.
 *
 * What is worth proving here is not that the endpoints respond, but the two things
 * that would quietly mislead an accountant if they were wrong: that revenue is net
 * of refunds rather than gross, and that the gateway references on an invoice are
 * gated on the permission that guards every other bearer link to a financial
 * document.
 */
function invoiceFixture(User $user, Plan $plan, array $attributes = []): Invoice
{
    $invoice = Invoice::query()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'gateway' => 'stripe',
        'stripe_invoice_id' => 'in_test_'.uniqid(),
        'number' => 'MC-'.random_int(10000, 99999),
        'status' => 'paid',
        'subtotal' => 1000,
        'tax' => 100,
        'total' => 1100,
        'amount_refunded' => 0,
        'currency' => 'USD',
        'issued_at' => now()->subDays(2),
        'paid_at' => now()->subDays(2),
        'transaction_id' => 'pi_test_'.uniqid(),
        'transaction_url' => 'https://dashboard.stripe.com/test/payments/pi_test',
        'payment_method_type' => 'card',
        'payment_method_brand' => 'visa',
        'payment_method_last4' => '4242',
        ...$attributes,
    ]);

    InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'description' => 'Pro Monthly',
        'quantity' => 1,
        'unit_amount' => 1000,
        'amount' => 1000,
    ]);

    return $invoice;
}

function planFixture(array $attributes = []): Plan
{
    return Plan::query()->create([
        'key' => 'plan_'.uniqid(),
        'name' => 'Pro Monthly',
        'billing_mode' => 'subscription',
        'interval' => 'month',
        'interval_count' => 1,
        'amount' => 1000,
        'currency' => 'USD',
        'is_active' => true,
        ...$attributes,
    ]);
}

it('serves one invoice with its lines, plan and payment', function () {
    $customer = User::factory()->create();
    $plan = planFixture();

    $subscription = Subscription::query()->create([
        'user_id' => $customer->id,
        'plan_id' => $plan->id,
        'stripe_id' => 'sub_'.uniqid(),
        'stripe_status' => 'active',
    ]);

    $invoice = invoiceFixture($customer, $plan, [
        'subscription_id' => $subscription->id,
        'period_start' => now()->subMonth(),
        'period_end' => now(),
    ]);

    $this->actingAs(staff('super-admin'))
        ->getJson("/api/v1/admin/invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonPath('data.number', $invoice->number)
        ->assertJsonPath('data.plan.name', 'Pro Monthly')
        ->assertJsonPath('data.subscription.id', $subscription->id)
        ->assertJsonPath('data.payment_method.last4', '4242')
        ->assertJsonPath('data.lines.0.description', 'Pro Monthly')
        ->assertJsonPath('data.transaction_id', $invoice->transaction_id)
        ->assertJsonPath('data.refund', null);
});

it('reports a refund with its reason, and nets it off the total', function () {
    $customer = User::factory()->create();

    $invoice = invoiceFixture($customer, planFixture(), [
        'status' => 'refunded',
        'amount_refunded' => 400,
        'refunded_at' => now()->subDay(),
        'refund_reason' => 'Charged twice in the same period.',
        'refund_reference' => 're_test_1',
    ]);

    $this->actingAs(staff('super-admin'))
        ->getJson("/api/v1/admin/invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonPath('data.refund.amount', 400)
        // 400 of 1100 came back, so the screen must not call this a full refund.
        ->assertJsonPath('data.refund.is_partial', true)
        ->assertJsonPath('data.refund.reason', 'Charged twice in the same period.')
        ->assertJsonPath('data.refund.reference', 're_test_1')
        ->assertJsonPath('data.net_total', 700);
});

it('withholds gateway references from an actor who may only list invoices', function () {
    $invoice = invoiceFixture(User::factory()->create(), planFixture(), [
        'hosted_url' => 'https://invoice.stripe.com/test',
    ]);

    // Composed rather than borrowed from a stock role: the point is precisely the
    // gap between "may see the list" and "may see what identifies the payment",
    // and no shipped role sits in it.
    app(SyncRolesAndPermissions::class)->handle();

    $actor = tap(User::factory()->create(), fn (User $user) => $user->givePermissionTo('invoices.view_any'));

    expect($actor->can('invoices.view_any'))->toBeTrue()
        ->and($actor->can('invoices.view'))->toBeFalse();

    $this->actingAs($actor)
        ->getJson('/api/v1/admin/invoices')
        ->assertOk()
        ->assertJsonMissingPath('data.0.hosted_url');

    // And the detail page itself is out of reach — it is nothing but references.
    $this->actingAs($actor)
        ->getJson("/api/v1/admin/invoices/{$invoice->id}")
        ->assertForbidden();
});

it('reports revenue net of refunds, not gross', function () {
    $plan = planFixture();

    invoiceFixture(User::factory()->create(), $plan);
    invoiceFixture(User::factory()->create(), $plan, [
        'status' => 'refunded',
        'amount_refunded' => 1100,
        'refunded_at' => now()->subDay(),
    ]);

    // One invoice collected 1100 and one was refunded in full, so the honest answer
    // is 1100 — not the 2200 a gross sum would report.
    $this->actingAs(staff('super-admin'))
        ->getJson('/api/v1/admin/invoices/report?period=30')
        ->assertOk()
        ->assertJsonPath('data.metrics.0.key', 'net_revenue')
        ->assertJsonPath('data.metrics.0.value', 1100)
        ->assertJsonPath('data.by_plan.0.revenue', 1100);
});

it('normalises a yearly plan to a twelfth of MRR', function () {
    $yearly = planFixture(['interval' => 'year', 'amount' => 12000]);

    Subscription::query()->create([
        'user_id' => User::factory()->create()->id,
        'plan_id' => $yearly->id,
        'stripe_id' => 'sub_'.uniqid(),
        'stripe_status' => 'active',
    ]);

    $response = $this->actingAs(staff('super-admin'))
        ->getJson('/api/v1/admin/invoices/report?period=30')
        ->assertOk();

    $metrics = collect($response->json('data.metrics'))->keyBy('key');

    // 12000 a year is 1000 a month, not 12000 of this month's recurring revenue.
    expect((float) $metrics['mrr']['value'])->toBe(1000.0)
        ->and((float) $metrics['arr']['value'])->toBe(12000.0)
        ->and((float) $metrics['active_subscriptions']['value'])->toBe(1.0);
});

it('serves one plan for its own edit page', function () {
    $plan = planFixture(['name' => 'Team Monthly']);

    $this->actingAs(staff('super-admin'))
        ->getJson("/api/v1/admin/plans/{$plan->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Team Monthly')
        ->assertJsonPath('data.active_subscriptions', 0);
});

it('publishes the blog display settings without a session, and no secrets', function () {
    $this->seed(SettingsSeeder::class);

    // Keys contain dots, so the map is read whole rather than through dot-paths.
    $settings = $this->getJson('/api/v1/settings')->assertOk()->json('data');

    expect($settings['blog.show_author'])->toBeTrue()
        ->and($settings['blog.posts_per_page'])->toBe(12)
        // Encrypted values are secrets regardless of how `is_public` is set.
        ->and($settings)->not->toHaveKey('payments.stripe.secret_key')
        ->and($settings)->not->toHaveKey('newsletter.api_key');
});

it('lets an admin turn the author byline off', function () {
    $this->seed(SettingsSeeder::class);

    $this->actingAs(staff('admin'))
        ->putJson('/api/v1/admin/settings', [
            'settings' => [['key' => 'blog.show_author', 'value' => false]],
        ])
        ->assertOk()
        ->assertJsonPath('data.updated.0', 'blog.show_author');

    expect(Setting::query()->where('key', 'blog.show_author')->sole()->typedValue())->toBeFalse();

    expect($this->getJson('/api/v1/settings')->assertOk()->json('data')['blog.show_author'])
        ->toBeFalse();
});
