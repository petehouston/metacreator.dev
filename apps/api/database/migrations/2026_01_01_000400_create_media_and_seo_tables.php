<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_folders', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->timestamps();
        });

        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('disk', 40)->default('spaces');
            $table->string('path', 400);
            $table->string('filename', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            // Dedupes re-uploads of an identical file.
            $table->char('checksum', 64)->index();

            $table->string('alt_text', 255)->nullable();
            $table->string('title', 255)->nullable();
            $table->text('caption')->nullable();
            $table->string('credit', 255)->nullable();
            $table->boolean('is_decorative')->default(false);
            $table->string('blur_hash', 64)->nullable();

            $table->foreignId('folder_id')->nullable()->constrained('media_folders')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('usage_count')->default(0);
            $table->string('variant_status', 20)->default('pending');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['mime_type', 'created_at']);
            $table->fullText(['filename', 'title', 'alt_text', 'caption'], 'media_search_ft');
        });

        Schema::create('media_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_id')->constrained()->cascadeOnDelete();
            $table->string('label', 30);
            $table->string('format', 10);
            $table->string('path', 400);
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedBigInteger('size');
            $table->timestamps();

            $table->unique(['media_id', 'label', 'format']);
        });

        // Records every place a media item is used, so deletion can show exactly what breaks.
        Schema::create('media_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_id')->constrained()->cascadeOnDelete();
            $table->string('usable_type', 120);
            $table->unsignedBigInteger('usable_id');
            $table->string('context', 60)->default('content');
            $table->timestamps();

            $table->unique(['media_id', 'usable_type', 'usable_id', 'context'], 'media_usage_unique');
            $table->index(['usable_type', 'usable_id']);
        });

        Schema::create('seo_meta', function (Blueprint $table): void {
            $table->id();
            $table->string('seoable_type', 120);
            $table->unsignedBigInteger('seoable_id');

            $table->string('title', 255)->nullable();
            $table->string('description', 500)->nullable();
            $table->string('canonical_url', 500)->nullable();
            $table->string('robots', 60)->default('index,follow');
            $table->string('focus_keyword', 120)->nullable();

            $table->string('og_title', 255)->nullable();
            $table->string('og_description', 500)->nullable();
            $table->foreignId('og_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('twitter_card', 30)->default('summary_large_image');

            $table->string('schema_type', 60)->nullable();
            $table->json('schema_overrides')->nullable();

            $table->timestamps();

            $table->unique(['seoable_type', 'seoable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_meta');
        Schema::dropIfExists('media_usages');
        Schema::dropIfExists('media_variants');
        Schema::dropIfExists('media');
        Schema::dropIfExists('media_folders');
    }
};
