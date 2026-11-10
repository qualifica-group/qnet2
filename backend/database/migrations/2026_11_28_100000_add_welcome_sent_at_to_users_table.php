<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // When the welcome email was last sent (spec 0369). No backfill: the
            // one-shot `users:send-welcome` must reach every active user once.
            $table->timestamp('welcome_sent_at')->nullable()->after('password_changed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('welcome_sent_at');
        });
    }
};
