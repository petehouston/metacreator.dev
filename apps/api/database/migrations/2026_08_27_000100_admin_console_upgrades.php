<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two additions the admin console needs.
     *
     * `post_post_category` gives a post *secondary* categories the WordPress way,
     * without disturbing `posts.category_id` — which stays the primary one, and is
     * what the URL, the breadcrumb and every existing query already read. A pivot
     * that replaced the column would have meant "which of these is the canonical
     * one?" becoming a runtime question on every public page.
     *
     * `plans.gateway_ids` holds the price/plan identifier at each payment provider,
     * keyed by provider. One column rather than one per gateway: adding PayPal must
     * not be a migration.
     */
    public function up(): void
    {
        Schema::create('post_post_category', function (Blueprint $table): void {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_category_id')->constrained('post_categories')->cascadeOnDelete();

            $table->primary(['post_id', 'post_category_id']);
        });

        Schema::table('plans', function (Blueprint $table): void {
            $table->json('gateway_ids')->nullable()->after('stripe_price_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_post_category');

        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn('gateway_ids');
        });
    }
};
