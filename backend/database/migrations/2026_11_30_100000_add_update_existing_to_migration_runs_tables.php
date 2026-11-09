<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Update already imported records" choice (spec 0372): off by default, so an
        // existing run and every run started without the flag keep today's behaviour.
        Schema::table('mass_migration_runs', function (Blueprint $table): void {
            $table->boolean('update_existing')->default(false);
        });

        Schema::table('migration_runs', function (Blueprint $table): void {
            $table->boolean('update_existing')->default(false);
            $table->unsignedInteger('updated_rows')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('migration_runs', function (Blueprint $table): void {
            $table->dropColumn(['update_existing', 'updated_rows']);
        });

        Schema::table('mass_migration_runs', function (Blueprint $table): void {
            $table->dropColumn('update_existing');
        });
    }
};
