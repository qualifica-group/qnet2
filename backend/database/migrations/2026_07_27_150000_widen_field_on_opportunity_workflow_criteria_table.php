<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widens `opportunity_workflow_criteria.field` from varchar(64) to
 * varchar(191) (spec 0047 amendment 2026-07-27, D6): a custom-field
 * criterion's key is namespaced (App\CustomFields\CustomFieldProvider::
 * KEY_PREFIX `custom.` + the definition's own `key`, itself validated
 * max:64 — see StoreCustomFieldRequest), so `custom.<key>` can reach 71
 * chars, past the original column's 64-char ceiling.
 *
 * `->change()` only tightens the column's declared length; the composite
 * unique index `ow_criteria_workflow_field_unique` is untouched (MySQL:
 * MODIFY COLUMN never drops a separate index object; SQLite: Laravel
 * rebuilds the table from its current schema state, re-emitting every
 * existing index on the rebuilt table — same guarantee already relied on
 * for foreign keys by 2026_07_17_200001_add_opportunity_status_id_to_
 * opportunities_table's own `->change()`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity_workflow_criteria', function (Blueprint $table) {
            $table->string('field', 191)->change();
        });
    }

    public function down(): void
    {
        Schema::table('opportunity_workflow_criteria', function (Blueprint $table) {
            $table->string('field', 64)->change();
        });
    }
};
