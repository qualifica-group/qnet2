<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Spec 0368 — welcome tour. No backfill on purpose (D-1): every
            // existing user must see the tour once, so null means "never seen".
            $table->unsignedSmallInteger('onboarding_seen_version')->nullable();
            $table->timestamp('onboarding_seen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['onboarding_seen_version', 'onboarding_seen_at']);
        });
    }
};
