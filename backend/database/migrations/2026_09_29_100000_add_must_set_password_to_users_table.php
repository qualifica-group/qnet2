<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // First-access flag (spec 0177): true only for users created through
            // POST /api/users (or whose password an admin reset), until they choose
            // their own password. No backfill: existing, imported and seeded users
            // stay false and are never forced.
            $table->boolean('must_set_password')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('must_set_password');
        });
    }
};
