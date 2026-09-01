<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A release is a dated batch of changes. Two tables rather than one row with
        // a JSON blob of entries, because the public page filters by change type —
        // "show me every fix since March" is a WHERE clause on a real column, not a
        // scan of every JSON document on the table.
        Schema::create('changelog_releases', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('slug', 160)->unique();

            // The human version label ("2.4.0", "August 2026"). Deliberately a free
            // string and not semver-validated: a product that ships continuously
            // dates its releases, and one that ships in versions numbers them.
            $table->string('version', 60)->nullable();
            $table->string('title', 200);
            $table->text('summary')->nullable();

            $table->string('status', 20)->default('draft');

            // The date the change reached users, which is not `created_at` — a
            // release is usually written up after it ships, and back-dating it has
            // to stay possible or the timeline lies.
            $table->timestamp('released_at')->nullable();

            // Lifts a release out of the timeline with a wider card. Reserved for
            // the two or three a year anyone would call an announcement.
            $table->boolean('is_major')->default(false);

            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // The public listing's only query: published, newest first.
            $table->index(['status', 'released_at']);
        });

        Schema::create('changelog_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('release_id')
                ->constrained('changelog_releases')
                ->cascadeOnDelete();

            $table->string('type', 20);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['release_id', 'sort_order']);
            // Powers the "every security change ever" filter without touching the
            // parent table.
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('changelog_items');
        Schema::dropIfExists('changelog_releases');
    }
};
