<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The funnel counters from docs/15, which had a specification and no table.
     *
     * Counters rather than raw events: a tool view and a paywall hit are high volume
     * and worthless individually, so they are folded into a per-tool-per-day row at
     * write time. That keeps the write cheap, the table small, and the analytics
     * screen a single indexed scan.
     *
     * `paywall_hits` in particular had no source at all before this — an access
     * denial threw before anything was recorded, so "which premium tools do free
     * users actually want?" was unanswerable. That question is the whole reason the
     * tiering exists.
     */
    public function up(): void
    {
        Schema::create('tool_funnel_daily', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->foreignId('tool_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('starts')->default(0);
            $table->unsignedInteger('completions')->default(0);
            $table->unsignedInteger('paywall_hits')->default(0);
            $table->unsignedInteger('account_walls')->default(0);
            $table->unsignedInteger('quota_walls')->default(0);
            $table->unsignedInteger('upgrades')->default(0);

            $table->timestamps();

            $table->unique(['date', 'tool_id']);
            $table->index('date');
        });

        // `content_daily_stats.views` is a per-day delta, but the only view counter
        // the product keeps is the running total on `posts`. Without the total the
        // rollup was computed against, a recompute cannot tell a quiet day from a
        // missing one — so the snapshot is stored alongside the delta.
        Schema::table('content_daily_stats', function (Blueprint $table): void {
            $table->unsignedBigInteger('views_cumulative')->default(0)->after('views');
        });
    }

    public function down(): void
    {
        Schema::table('content_daily_stats', function (Blueprint $table): void {
            $table->dropColumn('views_cumulative');
        });

        Schema::dropIfExists('tool_funnel_daily');
    }
};
