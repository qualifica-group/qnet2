<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The segnatempo note is now mirrored as a collaborative note (comment) on its
 * commessa instead of a text block inside `internal_notes` (user decision
 * 2026-09-17). The comment has its own id, so the text snapshot
 * `work_order_note` is replaced by `work_order_note_id`, which
 * WorkOrderNoteSynchronizer uses to rewrite or delete it. Blocks already
 * copied into `internal_notes` are left in place (user decision): they are
 * simply no longer kept in sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropColumn('work_order_note');
        });

        Schema::table('time_entries', function (Blueprint $table) {
            $table->foreignId('work_order_note_id')->nullable()->after('notes')->constrained('notes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_order_note_id');
        });

        Schema::table('time_entries', function (Blueprint $table) {
            $table->text('work_order_note')->nullable()->after('notes');
        });
    }
};
