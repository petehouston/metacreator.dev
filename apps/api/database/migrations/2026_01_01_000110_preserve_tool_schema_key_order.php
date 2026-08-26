<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MySQL's native JSON type normalises objects by sorting their keys, which is
     * fine for data but wrong for a schema whose property order *is* the form's
     * field order. Storing the schema as text preserves it byte-for-byte.
     *
     * Only `input_schema` is affected: JSON arrays keep their order, so the block
     * documents in `instructions`, `example` and `faq` are safe as JSON.
     */
    public function up(): void
    {
        Schema::table('tools', function (Blueprint $table): void {
            $table->longText('input_schema')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tools', function (Blueprint $table): void {
            $table->json('input_schema')->nullable()->change();
        });
    }
};
