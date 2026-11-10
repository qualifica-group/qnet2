<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Last successful credential login (spec 0370). No backfill: no login was
            // recorded before, so existing users stay null until they sign in again.
            // Indexed because the users grid sorts and filters on it.
            $table->timestamp('last_login_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['last_login_at']);
            $table->dropColumn('last_login_at');
        });
    }
};
