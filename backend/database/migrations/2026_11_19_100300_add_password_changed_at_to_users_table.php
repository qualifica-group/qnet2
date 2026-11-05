<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // When the user last chose their own password (spec 0356). No backfill:
            // nobody is known to have chosen one, so the production seed may force
            // every active user once; re-runs skip whoever changed it since.
            $table->timestamp('password_changed_at')->nullable()->after('must_set_password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('password_changed_at');
        });
    }
};
