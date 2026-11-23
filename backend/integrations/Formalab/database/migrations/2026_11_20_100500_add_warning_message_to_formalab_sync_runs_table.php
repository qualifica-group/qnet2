<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A successful run can still carry a warning, e.g. course fields whose
 * attribute code no longer exists (they stop updating without an error).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formalab_sync_runs', function (Blueprint $table): void {
            $table->text('warning_message')->nullable()->after('error_message');
        });
    }

    public function down(): void
    {
        Schema::table('formalab_sync_runs', function (Blueprint $table): void {
            $table->dropColumn('warning_message');
        });
    }
};
