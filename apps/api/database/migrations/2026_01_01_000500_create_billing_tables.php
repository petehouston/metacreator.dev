<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * These tables are a *projection* of Stripe, not a source of truth (ADR 0004).
     * They exist so an entitlement check is a local index lookup and so a Stripe
     * outage cannot lock out paying customers.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('name', 120);
            $table->string('tagline', 200)->nullable();
            $table->string('stripe_price_id', 120)->nullable()->unique();
            $table->string('billing_mode', 20)->default('subscription'); // subscription | one_time
            $table->string('interval', 20)->nullable();                  // day | month | year
            $table->unsignedSmallInteger('interval_count')->default(1);
            $table->unsignedInteger('amount');                           // minor units
            $table->char('currency', 3)->default('USD');
            $table->unsignedInteger('duration_days')->nullable();        // for one-time passes
            $table->json('features')->nullable();
            $table->json('limits')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_highlighted')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('stripe_id', 120)->nullable()->unique();
            $table->string('stripe_status', 30)->default('incomplete');
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('cancel_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('cancellation_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'stripe_status']);
            $table->index('current_period_end');
        });

        // Time-boxed access from a one-off purchase (the 7-day pass) or an admin comp.
        Schema::create('access_passes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 30)->default('purchase');
            $table->string('stripe_payment_intent', 120)->nullable()->unique();
            $table->timestamp('starts_at');
            $table->timestamp('expires_at');
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('stripe_invoice_id', 120)->unique();
            $table->string('number', 60)->nullable();
            $table->string('status', 30);
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('tax')->default(0);
            $table->unsignedInteger('total');
            $table->unsignedInteger('amount_refunded')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->string('hosted_url', 500)->nullable();
            $table->string('pdf_url', 500)->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'issued_at']);
            $table->index(['status', 'issued_at']);
        });

        Schema::create('invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description', 255);
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('unit_amount');
            $table->unsignedInteger('amount');
            $table->timestamps();
        });

        // Makes webhook handling replay-safe: an event id is processed at most once.
        Schema::create('stripe_events', function (Blueprint $table): void {
            $table->id();
            $table->string('stripe_event_id', 120)->unique();
            $table->string('type', 80);
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamps();

            $table->index(['type', 'created_at']);
        });

        Schema::create('billing_daily_stats', function (Blueprint $table): void {
            $table->id();
            $table->date('date')->unique();
            $table->unsignedBigInteger('mrr')->default(0);
            $table->unsignedBigInteger('arr')->default(0);
            $table->json('active_by_plan')->nullable();
            $table->unsignedInteger('new_subscriptions')->default(0);
            $table->unsignedInteger('cancellations')->default(0);
            $table->unsignedInteger('passes_sold')->default(0);
            $table->unsignedInteger('pass_upgrades')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_daily_stats');
        Schema::dropIfExists('stripe_events');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('access_passes');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
