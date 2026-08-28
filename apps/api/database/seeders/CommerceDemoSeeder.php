<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\InvoiceLine;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Users\Models\User;
use Illuminate\Database\Seeder;

/**
 * Sample invoices.
 *
 * The billing screen is built and correct, and until Stripe is wired it renders an
 * empty table — which is indistinguishable from a broken screen. These rows make
 * the totals, the status filter and the money formatting reviewable now, and they
 * carry `in_demo_` identifiers so they are recognisable as fixtures rather than as
 * financial records somebody has to reconcile.
 *
 * Never runs in production — see {@see DatabaseSeeder}.
 */
final class CommerceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()
            ->whereIn('email', ['pro@metacreator.dev', 'pass@metacreator.dev', 'free@metacreator.dev'])
            ->get()
            ->keyBy('email');

        $monthly = Plan::query()->where('key', 'pro_monthly')->first();
        $pass = Plan::query()->where('key', 'pass_7d')->first();

        if ($users->isEmpty() || $monthly === null || $pass === null) {
            return;
        }

        $pro = $users->get('pro@metacreator.dev');
        $passHolder = $users->get('pass@metacreator.dev');
        $free = $users->get('free@metacreator.dev');

        // Six paid months for the subscriber, so the invoice list has a history to
        // page through and the "paid" total is a number worth checking.
        if ($pro !== null) {
            $subscription = Subscription::query()
                ->where('user_id', $pro->id)
                ->where('plan_id', $monthly->id)
                ->first();

            foreach (range(1, 6) as $monthsAgo) {
                $issued = now()->subMonths($monthsAgo)->startOfMonth()->addDays(3);

                $this->invoice(
                    reference: 'pro-'.$monthsAgo,
                    user: $pro,
                    description: 'Pro Monthly — '.$issued->format('F Y'),
                    amount: $monthly->amount,
                    status: 'paid',
                    issuedAt: $issued,
                    paidAt: $issued,
                    plan: $monthly,
                    subscription: $subscription,
                    periodStart: $issued,
                    periodEnd: $issued->copy()->addMonth(),
                );
            }

            // One refunded, because a billing screen that has never seen a refund is
            // a billing screen whose refund column has never been looked at — and a
            // refund with no reason on it is the shape support actually complains
            // about, so this one carries the reason too.
            $refundedAt = now()->subMonths(7)->addDays(5);

            $this->invoice(
                reference: 'pro-refunded',
                user: $pro,
                description: 'Pro Monthly — duplicate charge, refunded',
                amount: $monthly->amount,
                status: 'refunded',
                issuedAt: now()->subMonths(7)->addDays(3),
                paidAt: now()->subMonths(7)->addDays(3),
                refunded: $monthly->amount + (int) round($monthly->amount * 0.1),
                plan: $monthly,
                subscription: $subscription,
                refundedAt: $refundedAt,
                refundReason: 'Charged twice in the same billing period — the duplicate was returned in full.',
            );
        }

        if ($passHolder !== null) {
            $this->invoice(
                reference: 'pass-1',
                user: $passHolder,
                description: '7-Day Pass',
                amount: $pass->amount,
                status: 'paid',
                issuedAt: now()->subDays(4),
                paidAt: now()->subDays(4),
                plan: $pass,
                gateway: 'paypal',
                periodStart: now()->subDays(4),
                periodEnd: now()->addDays(3),
            );
        }

        // An unpaid one, so "outstanding" is non-zero and dunning has something to
        // point at once it exists.
        if ($free !== null) {
            $this->invoice(
                reference: 'free-open',
                user: $free,
                description: 'Pro Monthly — payment failed, retrying',
                amount: $monthly->amount,
                status: 'open',
                issuedAt: now()->subDays(2),
                paidAt: null,
                plan: $monthly,
            );
        }
    }

    private function invoice(
        string $reference,
        User $user,
        string $description,
        int $amount,
        string $status,
        \DateTimeInterface $issuedAt,
        ?\DateTimeInterface $paidAt,
        int $refunded = 0,
        ?Plan $plan = null,
        ?Subscription $subscription = null,
        string $gateway = 'stripe',
        ?\DateTimeInterface $periodStart = null,
        ?\DateTimeInterface $periodEnd = null,
        ?\DateTimeInterface $refundedAt = null,
        ?string $refundReason = null,
    ): void {
        $tax = (int) round($amount * 0.1);

        // A plausible transaction reference per gateway, so the detail page's
        // "open this at the provider" link is exercised rather than always empty.
        $transaction = match ($gateway) {
            'paypal' => 'DEMO'.strtoupper(substr(md5($reference), 0, 13)),
            default => 'pi_demo_'.substr(md5($reference), 0, 16),
        };

        $invoice = Invoice::query()->updateOrCreate(
            ['stripe_invoice_id' => 'in_demo_'.$reference],
            [
                'user_id' => $user->id,
                'gateway' => $gateway,
                'subscription_id' => $subscription?->id,
                'plan_id' => $plan?->id,
                'number' => 'MC-'.str_pad((string) (crc32($reference) % 100000), 5, '0', STR_PAD_LEFT),
                'status' => $status,
                'subtotal' => $amount,
                'tax' => $tax,
                'total' => $amount + $tax,
                'amount_refunded' => $refunded,
                'currency' => 'USD',
                'issued_at' => $issuedAt,
                'paid_at' => $paidAt,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                // Only a settled payment has a transaction behind it; an open
                // invoice that shows one would be a lie the screen cannot detect.
                'transaction_id' => $paidAt === null ? null : $transaction,
                'transaction_url' => $paidAt === null ? null : match ($gateway) {
                    'paypal' => 'https://www.sandbox.paypal.com/activity/payment/'.$transaction,
                    default => 'https://dashboard.stripe.com/test/payments/'.$transaction,
                },
                'payment_method_type' => $paidAt === null ? null : ($gateway === 'paypal' ? 'paypal' : 'card'),
                'payment_method_brand' => $paidAt === null || $gateway === 'paypal' ? null : 'visa',
                'payment_method_last4' => $paidAt === null || $gateway === 'paypal' ? null : '4242',
                'refunded_at' => $refunded > 0 ? ($refundedAt ?? $paidAt) : null,
                'refund_reason' => $refunded > 0 ? $refundReason : null,
                'refund_reference' => $refunded > 0 ? 're_demo_'.substr(md5($reference), 0, 14) : null,
                'billing_name' => $user->displayName(),
                'billing_email' => $user->email,
            ],
        );

        InvoiceLine::query()->updateOrCreate(
            ['invoice_id' => $invoice->id, 'description' => $description],
            ['quantity' => 1, 'unit_amount' => $amount, 'amount' => $amount],
        );
    }
}
