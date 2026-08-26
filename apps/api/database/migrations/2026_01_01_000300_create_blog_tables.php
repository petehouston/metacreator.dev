<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 140)->unique();
            $table->string('name', 140);
            $table->text('description')->nullable();
            $table->string('accent_color', 20)->nullable();
            $table->foreignId('featured_media_id')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedInteger('post_count')->default(0);
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 140)->unique();
            $table->string('name', 140);
            $table->text('description')->nullable();
            $table->unsignedInteger('post_count')->default(0);
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('slug', 200)->unique();
            $table->string('title', 255);
            $table->text('excerpt')->nullable();

            // Canonical content. `content_html` and `content_text` are regenerable caches
            // kept for rendering speed and full-text search respectively (see ADR 0003).
            $table->json('blocks');
            $table->longText('content_html')->nullable();
            $table->longText('content_text')->nullable();

            $table->foreignId('featured_media_id')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('post_categories')->nullOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();

            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_for')->nullable();

            $table->unsignedSmallInteger('reading_minutes')->default(1);
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedBigInteger('view_count')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('allow_comments')->default(false);

            // Optimistic concurrency: PATCH without a matching version gets a 409.
            $table->unsignedInteger('version')->default(1);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['category_id', 'status', 'published_at'], 'posts_category_listing_idx');
            $table->index(['status', 'scheduled_for']);
            $table->index(['is_featured', 'published_at']);
            $table->fullText(['title', 'excerpt', 'content_text'], 'posts_search_ft');
        });

        Schema::create('post_tag', function (Blueprint $table): void {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['post_id', 'tag_id']);
            $table->index('tag_id');
        });

        Schema::create('post_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 255);
            $table->json('blocks');
            $table->string('note', 255)->nullable();
            $table->boolean('is_autosave')->default(true);
            $table->timestamp('created_at')->nullable();

            $table->index(['post_id', 'created_at']);
        });

        // Slugs never change without leaving a 301 behind (see docs/16).
        Schema::create('redirects', function (Blueprint $table): void {
            $table->id();
            $table->string('from_path', 255)->unique();
            $table->string('to_path', 255);
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->unsignedBigInteger('hit_count')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
        Schema::dropIfExists('post_revisions');
        Schema::dropIfExists('post_tag');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('post_categories');
    }
};
