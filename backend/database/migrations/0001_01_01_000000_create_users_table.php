<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // The external system's id for a row migrated from it (spec 0013):
            // NULL for native qnet rows, unique among migrated ones.
            $table->unsignedBigInteger('old_id')->nullable()->unique();

            $table->string('name');
            $table->string('email')->unique();

            // BCP-47-ish locale (e.g. "en", "it"). Drives the user's UI language.
            $table->string('locale', 5)->default('en');

            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            // An inactive account keeps its record but is denied login
            // (AuthService::login).
            $table->boolean('is_active')->default(true);

            // Per-user module open mode preference (spec 0042): { mode, overrides }.
            // Null means "no preference yet" — the resource layer serializes the
            // default { mode: 'custom', overrides: {} } instead of null.
            $table->json('module_open_preferences')->nullable();

            // Per-user UI scale (0..100 slider). Null means "no preference yet" —
            // the resource layer serializes the default (40 => 100% size) instead
            // of null. Held in a tinyint (0..255) which comfortably covers 0..100.
            $table->unsignedTinyInteger('ui_scale')->nullable();

            $table->rememberToken();
            $table->timestamps();

            // `name` bounds the for-select search scan (email is already unique);
            // `created_at` is the table's default sort column (UsersTableDefinition).
            $table->index('name');
            $table->index('created_at');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
