<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A ranking page is "the top N accounts on one network, by one metric".
        //
        // The page is a row rather than a constant in code because an admin owns
        // the presentation — title, blurb, how many rows to show, whether it is
        // published at all — and because the *source* has to be editable too: a
        // Wikipedia list that gets renamed or restructured is a Tuesday, not a
        // deploy. `source_page` and `source_table` are what the sync reads.
        Schema::create('top_ranking_pages', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('slug', 160)->unique();

            $table->string('platform', 24);
            $table->string('title', 200);

            // What the metric column is called on this page — "Subscribers",
            // "Followers", "Views". Per page rather than per platform: YouTube has
            // two pages here and they do not measure the same thing.
            $table->string('metric_label', 40)->default('Followers');

            // The unit the source publishes in, so the number can be stored as it
            // was written and rendered as "515M" without guessing a magnitude.
            $table->string('metric_unit', 16)->default('millions');

            $table->string('secondary_metric_label', 40)->nullable();
            $table->string('secondary_metric_unit', 16)->nullable();

            $table->text('intro')->nullable();
            $table->string('meta_title', 200)->nullable();
            $table->string('meta_description', 320)->nullable();

            // ── The source contract ──────────────────────────────────────────
            // The English Wikipedia article title, exactly as the API takes it.
            $table->string('source_page', 200);

            // Which `wikitable` on that article holds the ranking. Most articles
            // carry two or three — the ranking, a "progression of the record"
            // table, and sometimes a key — and index 0 is right for most but not
            // all of them, so it is data rather than an assumption.
            $table->unsignedTinyInteger('source_table')->default(0);

            // How many rows to keep. The source frequently publishes more than the
            // page wants to show (the YouTube article runs past 100).
            $table->unsignedSmallInteger('row_limit')->default(50);

            $table->boolean('is_published')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            // ── What the last sync did ───────────────────────────────────────
            // Kept on the page rather than in a log table: the only question ever
            // asked is "is this page current, and if not why not", which is one
            // row's worth of answer. The audit log records who pressed the button.
            $table->timestamp('synced_at')->nullable();
            $table->string('sync_status', 16)->default('never');
            $table->string('sync_message', 500)->nullable();
            $table->timestamp('avatars_synced_at')->nullable();

            $table->timestamps();

            // The public menu's only query: published, in the admin's order.
            $table->index(['is_published', 'sort_order']);
            $table->index('platform');
        });

        Schema::create('top_ranking_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')
                ->constrained('top_ranking_pages')
                ->cascadeOnDelete();

            // The position the page renders. Separate from any rank the source
            // published, because an admin may reorder — and once they have, the
            // source's opinion is history, not truth.
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->string('name', 200);

            // The @handle, without the @. Null for the lists that identify a row by
            // page name only (Facebook publishes no stable handle for every page).
            $table->string('handle', 120)->nullable();
            $table->string('owner', 200)->nullable();
            $table->string('profile_url', 500)->nullable();

            // Stored as a decimal in the page's own unit, not normalised to a raw
            // count. The source publishes "515" against a header that says
            // "(millions)", and converting on write would invent precision the
            // source never claimed — 515,000,000 reads as a measurement when it is
            // a rounding.
            $table->decimal('metric_value', 12, 3)->nullable();
            $table->decimal('secondary_metric_value', 12, 3)->nullable();

            $table->string('country', 120)->nullable();
            $table->string('category', 120)->nullable();
            $table->string('language', 120)->nullable();
            $table->string('description', 400)->nullable();

            // ── Avatar ───────────────────────────────────────────────────────
            // A direct link to the account's own picture, not a copy. See
            // AvatarResolver for why that is the right trade and where it hurts.
            $table->string('avatar_url', 1000)->nullable();
            $table->string('avatar_status', 16)->default('pending');
            $table->string('avatar_source', 24)->nullable();
            $table->timestamp('avatar_checked_at')->nullable();

            // Meta and TikTok sign their CDN URLs with an expiry that is readable
            // from the URL itself. Storing it means the page can show a monogram
            // instead of a broken image the moment the link dies, rather than
            // waiting for a reader to notice.
            $table->timestamp('avatar_expires_at')->nullable();

            // ── Provenance ───────────────────────────────────────────────────
            // Where the row came from, which decides what a sync may do to it.
            $table->string('source', 16)->default('wikipedia');

            // "The sync must not move or remove this row." The escape hatch that
            // makes an automated weekly refresh safe to leave running on a page an
            // editor has curated by hand.
            $table->boolean('is_pinned')->default(false);

            // The normalised key the sync reconciles on, so a refresh updates the
            // row for @mrbeast rather than deleting and re-inserting it — which
            // would throw away a resolved avatar every week.
            $table->string('match_key', 190);

            $table->timestamps();

            $table->index(['page_id', 'sort_order']);
            $table->unique(['page_id', 'match_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('top_ranking_entries');
        Schema::dropIfExists('top_ranking_pages');
    }
};
