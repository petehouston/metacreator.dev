<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ranking pages join the real SEO system.
 *
 * They shipped with two columns of their own — `meta_title` and `meta_description`
 * — which covered the search snippet and nothing else. Every other entity on the
 * site keeps its SEO in the polymorphic `seo_meta` row, which also carries the
 * canonical URL, the robots directive, the Open Graph title and description, the
 * share image and the card type. Two half-systems for one job is how a share
 * preview ends up correct on articles and a grey box on rankings.
 *
 * The values are copied before the columns go, so nothing an admin typed is lost.
 */
return new class extends Migration
{
    private const MORPH = 'App\Domain\TopRanking\Models\TopRankingPage';

    public function up(): void
    {
        foreach (DB::table('top_ranking_pages')->get(['id', 'meta_title', 'meta_description']) as $page) {
            if ($page->meta_title === null && $page->meta_description === null) {
                continue;
            }

            DB::table('seo_meta')->updateOrInsert(
                ['seoable_type' => self::MORPH, 'seoable_id' => $page->id],
                [
                    'title' => $page->meta_title,
                    'description' => $page->meta_description,
                    'robots' => 'index,follow',
                    'twitter_card' => 'summary_large_image',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        Schema::table('top_ranking_pages', function (Blueprint $table): void {
            $table->dropColumn(['meta_title', 'meta_description']);
        });
    }

    public function down(): void
    {
        Schema::table('top_ranking_pages', function (Blueprint $table): void {
            $table->string('meta_title', 200)->nullable()->after('intro');
            $table->string('meta_description', 320)->nullable()->after('meta_title');
        });

        foreach (DB::table('seo_meta')->where('seoable_type', self::MORPH)->get() as $row) {
            DB::table('top_ranking_pages')
                ->where('id', $row->seoable_id)
                ->update([
                    'meta_title' => $row->title === null ? null : substr((string) $row->title, 0, 200),
                    'meta_description' => $row->description === null ? null : substr((string) $row->description, 0, 320),
                ]);
        }
    }
};
