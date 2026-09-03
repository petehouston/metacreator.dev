<?php

declare(strict_types=1);

use App\Support\Concerns\PreservesAdminEdits;
use Database\Seeders\ProductionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which columns of a seeded row an admin has taken ownership of.
 *
 * Every table here is written by {@see ProductionSeeder} on each
 * deploy *and* editable from the admin console. Without this list the second half
 * of that sentence does not survive the first: the deploy reassigns every column
 * and an edit made in the console is gone. See
 * {@see PreservesAdminEdits}.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = ['tools', 'tool_categories', 'plans', 'seo_meta'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->json('locked_fields')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('locked_fields');
            });
        }
    }
};
