<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * User directive 2026-09-25: the "Evidenze" field is removed from the Task,
 * reverting spec 0154 D-3
 * (`2026_09_24_100200_add_privacy_evidence_and_lead_to_tasks_table`). Model,
 * FormRequests, DTOs, Resource, writer and form drop with it.
 *
 * Any leftover `role_field_permissions` row on `tasks.evidence` is cleaned in
 * the same pass: no FieldDefinition backs it.
 *
 * DESTRUCTIVE: the per-task Evidenze are not recoverable. `down()` restores
 * the STRUCTURE only, the same precedent as
 * `2026_09_17_140000_drop_state_id_from_leads_table`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('role_field_permissions')
            ->where('resource', 'tasks')
            ->where('field', 'evidence')
            ->delete();

        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropColumn('evidence');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->text('evidence')->nullable()->after('description');
        });
    }
};
