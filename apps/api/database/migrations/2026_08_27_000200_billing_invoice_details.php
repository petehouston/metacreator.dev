<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What an invoice has to be able to answer on its own.
     *
     * The original table was enough for a list: who, how much, paid or not. A
     * *detail* page is a different job — somebody is looking at one row because a
     * customer is disputing it, and the questions they have are "what card was
     * charged", "which transaction at the gateway is this", "why was it refunded"
     * and "what did they actually buy". None of those were answerable without
     * opening the gateway dashboard in another tab.
     *
     * The columns are gateway-neutral on purpose: `transaction_id` is a Stripe
     * payment intent, a PayPal capture id or a Braintree transaction id depending
     * on `gateway`, and `transaction_url` is wherever that provider shows it. A
     * per-provider column set would have made adding PayPal a migration.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('gateway', 30)->default('stripe')->after('user_id');

            // What was bought. Both nullable: a one-off pass has no subscription,
            // and an invoice whose plan was later deleted still has its lines.
            $table->foreignId('subscription_id')->nullable()->after('gateway')
                ->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->after('subscription_id')
                ->constrained()->nullOnDelete();

            // The period the invoice covers, for a subscription renewal.
            $table->timestamp('period_start')->nullable()->after('paid_at');
            $table->timestamp('period_end')->nullable()->after('period_start');

            // The payment itself, at whichever gateway took it.
            $table->string('transaction_id', 120)->nullable()->after('period_end');
            $table->string('transaction_url', 500)->nullable()->after('transaction_id');
            $table->string('payment_method_type', 30)->nullable()->after('transaction_url');
            $table->string('payment_method_brand', 30)->nullable()->after('payment_method_type');
            $table->string('payment_method_last4', 4)->nullable()->after('payment_method_brand');

            // The refund, if there was one. `amount_refunded` already says how much;
            // these say when, why, and which refund record at the gateway.
            $table->timestamp('refunded_at')->nullable()->after('payment_method_last4');
            $table->string('refund_reason', 255)->nullable()->after('refunded_at');
            $table->string('refund_reference', 120)->nullable()->after('refund_reason');

            // Who it was billed to, snapshotted. A user can change their email; an
            // invoice is a financial record of what was true when it was issued.
            $table->string('billing_name', 120)->nullable()->after('refund_reference');
            $table->string('billing_email', 180)->nullable()->after('billing_name');

            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign(['subscription_id']);
            $table->dropForeign(['plan_id']);
            $table->dropIndex(['transaction_id']);

            $table->dropColumn([
                'gateway', 'subscription_id', 'plan_id', 'period_start', 'period_end',
                'transaction_id', 'transaction_url', 'payment_method_type',
                'payment_method_brand', 'payment_method_last4', 'refunded_at',
                'refund_reason', 'refund_reference', 'billing_name', 'billing_email',
            ]);
        });
    }
};
