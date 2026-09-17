<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * User directive 2026-09-17: the "Regione" field is removed from the Lead,
 * completing the 2026-09-01 removal from the Opportunity and the Product
 * (`2026_09_01_120000_drop_state_id_from_opportunities_and_products_tables`).
 * Nothing consumed it any more: no grid column, no workflow criterion, no
 * inheritance on conversion. Model, FormRequest, Resource, DTO and form drop
 * with it.
 *
 * Any leftover `role_field_permissions` row on `leads.state_id` is cleaned in
 * the same pass: no FieldDefinition backs it.
 *
 * DESTRUCTIVE: the per-lead Regione is not recoverable. `down()` restores the
 * STRUCTURE only, the same precedent as the migration cited above.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('role_field_permissions')
            ->where('resource', 'leads')
            ->where('field', 'state_id')
            ->delete();

        Schema::table('leads', function (Blueprint $table): void {
            $table->dropForeign(['state_id']);
            $table->dropColumn('state_id');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->foreignId('state_id')->nullable()->after('operational_site_id')->constrained('states')->nullOnDelete();
        });
    }
};
