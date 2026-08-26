<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('reference', 20)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject', 255);
            $table->string('category', 40)->default('general');
            $table->string('priority', 20)->default('normal');
            $table->string('status', 20)->default('open');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            // Context captured automatically when a ticket is opened from a tool.
            $table->foreignId('tool_id')->nullable()->constrained()->nullOnDelete();
            $table->char('tool_run_ulid', 26)->nullable();

            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->unsignedTinyInteger('satisfaction_rating')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority', 'last_activity_at'], 'tickets_queue_idx');
            $table->index(['assigned_to', 'status']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('ticket_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_type', 20)->default('user');
            $table->longText('body');
            $table->boolean('is_internal_note')->default(false);
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
        });

        Schema::create('ticket_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_message_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 40)->default('private');
            $table->string('path', 400);
            $table->string('filename', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });

        Schema::create('canned_responses', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 160);
            $table->string('shortcut', 40)->nullable()->unique();
            $table->longText('body');
            $table->string('category', 40)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('use_count')->default(0);
            $table->timestamps();
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 80);
            $table->boolean('email')->default(true);
            $table->boolean('in_app')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'event_key']);
        });

        Schema::create('email_events', function (Blueprint $table): void {
            $table->id();
            $table->string('message_id', 190)->index();
            $table->string('recipient', 255);
            $table->string('template', 80)->nullable();
            $table->string('event', 30);
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['event', 'occurred_at']);
            $table->index(['recipient', 'occurred_at']);
        });

        // Checked before every send: a bounced or complained address is never mailed again.
        Schema::create('email_suppressions', function (Blueprint $table): void {
            $table->id();
            $table->string('email', 255)->unique();
            $table->string('reason', 40);
            $table->timestamp('suppressed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_suppressions');
        Schema::dropIfExists('email_events');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('canned_responses');
        Schema::dropIfExists('ticket_attachments');
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('tickets');
    }
};
