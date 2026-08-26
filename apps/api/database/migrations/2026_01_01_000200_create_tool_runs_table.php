<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The telemetry spine of the product (see docs/15).
     *
     * Deliberately privacy-preserving: no IP address is stored. `visitor_hash` is an
     * HMAC of IP+UA under a daily-rotating salt, which is enough to count uniques for
     * a day and useless afterwards.
     *
     * Raw runs are pruned at 90 days; the nightly rollup into `tool_run_daily_stats`
     * is what dashboards read.
     */
    public function up(): void
    {
        Schema::create('tool_runs', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();

            $table->foreignId('tool_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('tool_version');

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->char('visitor_hash', 64)->nullable();

            $table->string('status', 20)->default('queued');
            $table->string('access_reason', 30);

            $table->char('input_hash', 64);
            $table->json('input_preview')->nullable();
            $table->string('result_ref', 255)->nullable();
            $table->string('result_view', 40)->nullable();

            $table->unsignedInteger('duration_ms')->nullable();
            $table->boolean('cache_hit')->default(false);
            $table->unsignedSmallInteger('provider_calls')->default(0);

            $table->string('error_code', 60)->nullable();
            $table->text('error_message')->nullable();

            $table->string('referrer_source', 60)->nullable();
            $table->string('client', 20)->default('web');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['tool_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['tool_id', 'status', 'created_at'], 'tool_runs_tool_status_idx');
            $table->index(['status', 'created_at']);
            $table->index(['visitor_hash', 'created_at']);
        });

        Schema::create('tool_run_daily_stats', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->foreignId('tool_id')->constrained()->cascadeOnDelete();
            $table->string('tier', 20);
            $table->string('access_reason', 30);

            $table->unsignedInteger('runs')->default(0);
            $table->unsignedInteger('unique_actors')->default(0);
            $table->unsignedInteger('succeeded')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('cache_hits')->default(0);
            $table->unsignedInteger('p50_duration_ms')->default(0);
            $table->unsignedInteger('p95_duration_ms')->default(0);
            $table->json('error_breakdown')->nullable();

            $table->timestamps();

            $table->unique(['date', 'tool_id', 'tier', 'access_reason'], 'tool_run_daily_unique');
            $table->index(['tool_id', 'date']);
        });

        Schema::create('tool_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 255)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tool_id']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_grants');
        Schema::dropIfExists('tool_run_daily_stats');
        Schema::dropIfExists('tool_runs');
    }
};
