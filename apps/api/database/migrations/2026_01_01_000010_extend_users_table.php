<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->char('ulid', 26)->nullable()->unique()->after('id');
            $table->string('display_name', 60)->nullable()->after('name');
            $table->string('avatar_path', 255)->nullable()->after('display_name');
            $table->string('locale', 10)->default('en')->after('avatar_path');
            $table->string('timezone', 64)->default('UTC')->after('locale');
            $table->string('status', 20)->default('active')->after('timezone');
            $table->boolean('marketing_opt_in')->default(false)->after('status');
            $table->string('google_id', 64)->nullable()->unique()->after('marketing_opt_in');
            $table->timestamp('last_seen_at')->nullable()->after('google_id');
            $table->timestamp('deletion_requested_at')->nullable()->after('last_seen_at');
            $table->softDeletes();

            $table->index(['status', 'last_seen_at']);
        });

        // Password may be null for accounts created via Google or magic link only.
        Schema::table('users', function (Blueprint $table): void {
            $table->string('password')->nullable()->change();
        });

        Schema::create('magic_links', function (Blueprint $table): void {
            $table->id();
            $table->string('email', 255)->index();
            $table->char('token_hash', 64)->unique();
            $table->string('intent', 20)->default('login');
            $table->string('redirect_to', 255)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['email', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('magic_links');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['status', 'last_seen_at']);
            $table->dropColumn([
                'ulid', 'display_name', 'avatar_path', 'locale', 'timezone', 'status',
                'marketing_opt_in', 'google_id', 'last_seen_at', 'deletion_requested_at', 'deleted_at',
            ]);
        });
    }
};
