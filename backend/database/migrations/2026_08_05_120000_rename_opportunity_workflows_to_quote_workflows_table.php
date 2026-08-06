<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0083 (D-6): the "Configuratore Stati Lavorazione" (spec 0047) is
 * renamed to "Configuratore Stati Offerta" and moves from the Opportunity to
 * the Offerta (Quote) — a module named `opportunity-workflows` that governs
 * Quotes is naming drift (CLAUDE.md §1.2). This is a pure rename: no column
 * changes, no data loss. `quote_workflow_criteria`/`quote_workflow_statuses`
 * (each renamed in its own migration, right after this one) keep referencing
 * this table by name — MySQL/SQLite both update a child table's FK
 * definition automatically when the PARENT table is renamed, so no FK needs
 * dropping/recreating here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('opportunity_workflows', 'quote_workflows');
    }

    public function down(): void
    {
        Schema::rename('quote_workflows', 'opportunity_workflows');
    }
};
