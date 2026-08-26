<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('name', 120);
            $table->string('tagline', 200)->nullable();
            $table->text('description')->nullable();
            $table->string('icon', 60)->nullable();
            $table->string('accent_color', 20)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index(['is_visible', 'sort_order']);
        });

        Schema::create('tools', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('slug', 140)->unique();

            // Binds the row to its runner class. Immutable once published.
            $table->string('key', 140)->unique();

            $table->foreignId('category_id')->constrained('tool_categories')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name', 160);
            $table->string('tagline', 220)->nullable();
            $table->text('description')->nullable();

            $table->string('tier', 20)->default('free');
            $table->string('status', 20)->default('draft');
            $table->boolean('is_visible')->default(true);

            // Bumping this invalidates every cached result for the tool (see docs/08).
            $table->unsignedInteger('version')->default(1);

            $table->json('platforms')->nullable();
            $table->json('input_schema')->nullable();
            $table->json('config')->nullable();
            $table->json('instructions')->nullable();
            $table->json('example')->nullable();
            $table->json('faq')->nullable();
            $table->json('pinned_related')->nullable();

            $table->unsignedBigInteger('run_count')->default(0);
            $table->unsignedInteger('avg_duration_ms')->default(0);
            $table->decimal('success_rate', 5, 2)->default(100);

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('featured_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('successor_id')->nullable()->constrained('tools')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'is_visible', 'category_id'], 'tools_public_listing_idx');
            $table->index(['tier', 'status']);
            $table->index(['featured_at', 'sort_order']);
            $table->fullText(['name', 'tagline', 'description'], 'tools_search_ft');
        });

        // Denormalised for fast platform filtering; JSON columns cannot be indexed usefully here.
        Schema::create('tool_platform', function (Blueprint $table): void {
            $table->foreignId('tool_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 40);

            $table->primary(['tool_id', 'platform']);
            $table->index('platform');
        });

        Schema::create('tool_related', function (Blueprint $table): void {
            $table->foreignId('tool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_tool_id')->constrained('tools')->cascadeOnDelete();
            $table->decimal('score', 6, 4)->default(0);
            $table->boolean('is_pinned')->default(false);

            $table->primary(['tool_id', 'related_tool_id']);
            $table->index(['tool_id', 'is_pinned', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_related');
        Schema::dropIfExists('tool_platform');
        Schema::dropIfExists('tools');
        Schema::dropIfExists('tool_categories');
    }
};
