<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `work_order_note` is the exact text this segnatempo last copied into its
 * commessa's `internal_notes`. `internal_notes` is free text the user can
 * also edit by hand, so the copy cannot be addressed by id: this snapshot is
 * what WorkOrderNoteSynchronizer searches for to replace or remove it when
 * the note changes, the commessa changes or the segnatempo is deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->text('work_order_note')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropColumn('work_order_note');
        });
    }
};
