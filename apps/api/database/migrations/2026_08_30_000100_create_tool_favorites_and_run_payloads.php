<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two additions that only make sense for signed-in people.
     *
     * `tool_favorites` is the saved list a member builds up. It is deliberately not
     * available to anonymous visitors: a favourites list keyed on a visitor hash
     * would evaporate at midnight when the salt rotates, which is worse than not
     * offering it.
     *
     * The two payload columns hold what a run was given and what it produced, so a
     * member can reopen a result instead of paying for the same run twice. They are
     * only written for authenticated runs (see RunToolAction) — an anonymous run
     * stays the privacy-preserving record it was, with a hash and nothing else.
     */
    public function up(): void
    {
        Schema::create('tool_favorites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tool_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // One row per pair: favouriting twice is idempotent, not a duplicate.
            $table->unique(['user_id', 'tool_id']);
            // The list screen reads "my favourites, newest first".
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('tool_runs', function (Blueprint $table): void {
            $table->json('input_payload')->nullable()->after('input_preview');
            $table->json('result_payload')->nullable()->after('result_view');
        });
    }

    public function down(): void
    {
        Schema::table('tool_runs', function (Blueprint $table): void {
            $table->dropColumn(['input_payload', 'result_payload']);
        });

        Schema::dropIfExists('tool_favorites');
    }
};
