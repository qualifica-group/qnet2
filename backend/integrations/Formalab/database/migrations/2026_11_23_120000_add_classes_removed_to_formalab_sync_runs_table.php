<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0426 D-12: how many classes a run removed because Formalab no longer has them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formalab_sync_runs', function (Blueprint $table): void {
            $table->unsignedInteger('classes_removed')->default(0)->after('work_orders_updated');
        });
    }

    public function down(): void
    {
        Schema::table('formalab_sync_runs', function (Blueprint $table): void {
            $table->dropColumn('classes_removed');
        });
    }
};
