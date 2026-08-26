<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 120)->unique();
            $table->json('value')->nullable();
            $table->string('type', 20)->default('string');
            $table->string('group', 40)->default('general');

            // Only public settings are exposed by GET /site/settings.
            $table->boolean('is_public')->default(false);
            $table->boolean('is_encrypted')->default(false);
            $table->string('description', 255)->nullable();
            $table->timestamps();

            $table->index(['group', 'key']);
        });

        Schema::create('newsletter_subscribers', function (Blueprint $table): void {
            $table->id();
            $table->string('email', 255)->unique();
            $table->string('name', 120)->nullable();
            $table->string('status', 20)->default('pending'); // pending | subscribed | unsubscribed | bounced
            $table->string('source', 60)->nullable();
            $table->string('source_url', 500)->nullable();
            $table->json('tags')->nullable();

            // Consent record — what was shown, when, and from where (see docs/14).
            $table->string('consent_text', 500)->nullable();
            $table->char('consent_ip_hash', 64)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();

            $table->char('confirm_token_hash', 64)->nullable()->unique();
            $table->string('provider', 40)->nullable();
            $table->string('provider_subscriber_id', 120)->nullable();
            $table->string('sync_status', 20)->default('pending');
            $table->timestamp('synced_at')->nullable();
            $table->text('sync_error')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['sync_status', 'updated_at']);
        });

        Schema::create('contact_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 255);
            $table->string('subject', 255)->nullable();
            $table->text('message');
            $table->string('topic', 40)->default('general');
            $table->char('ip_hash', 64)->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['handled_at', 'created_at']);
        });

        Schema::create('content_daily_stats', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('reads')->default(0);
            $table->unsignedInteger('tool_clicks')->default(0);
            $table->unsignedInteger('newsletter_signups')->default(0);
            $table->timestamps();

            $table->unique(['date', 'post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_daily_stats');
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('newsletter_subscribers');
        Schema::dropIfExists('settings');
    }
};
