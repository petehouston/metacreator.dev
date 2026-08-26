<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs two things docs/06 asks for: the "new device" security notification, and the
 * list of active sessions a user can revoke from their security settings.
 *
 * The session id is stored so revoking a device can actually kill its session, rather
 * than only hiding the row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // SHA-256 of the user agent plus the client platform hints. Deliberately
            // not the IP: people move between networks constantly, and alerting on
            // that trains users to ignore the alert.
            $table->char('fingerprint', 64);

            $table->string('label', 120);
            $table->string('user_agent', 500)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('location', 120)->nullable();
            $table->string('session_id', 190)->nullable()->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'fingerprint']);
            $table->index(['user_id', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
