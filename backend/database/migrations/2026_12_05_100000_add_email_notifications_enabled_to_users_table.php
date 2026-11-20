<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Per-user switch for the email copy of in-app notifications. On by
            // default so every existing user keeps receiving emails until they
            // turn it off; the in-app (bell) notification is always delivered.
            $table->boolean('email_notifications_enabled')->default(true)->after('color_preset');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('email_notifications_enabled');
        });
    }
};
