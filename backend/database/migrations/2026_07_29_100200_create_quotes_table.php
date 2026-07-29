<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quote entity (spec 0065): a preventivo belongs to exactly one Opportunity
 * (`opportunity_id`, restrictOnDelete — an Opportunity with at least one
 * Quote is not deletable, D-27/AC-027) and carries its own manual/sequential
 * `code` (`QUO-{seq:4}`, D-13), identical pattern to the product code (D-1b).
 * `code` stays outside `Quote::$fillable`: the service assigns it after
 * mass-assignment.
 *
 * `commercial_id`/`reporter_id`/`supervisor_id` are a deliberate SNAPSHOT
 * (D-3), not a live read of the Opportunity: they are copied at creation time
 * and may then diverge from the Opportunity's own values, so they restrict on
 * delete exactly like their Opportunity counterparts rather than cascading or
 * nulling — a referenced Referent/User cannot be removed while any Quote
 * still snapshots it.
 *
 * `quote_status_id` is mandatory and restricts on delete (a status still
 * referenced by a Quote cannot be removed, mirrored at the app layer as on
 * `opportunity_statuses`).
 *
 * The five aggregate columns (`revenue_net`, `revenue_vat`, `cost_net`,
 * `cost_vat`, `margin_net`) are PERSISTED, not computed on read (D-9): the
 * server recalculates and writes them on every line write inside the same
 * transaction, so a query listing quotes never needs to join+sum
 * `quote_lines` to sort/filter on revenue or margin — the AG Grid SSRM table
 * (`TableDefinition`) reads them directly. The gross total (net + vat) stays
 * derived at read time, never stored, since it adds no independent
 * information.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('title', 191)->index();
            $table->foreignId('opportunity_id')->constrained('opportunities')->restrictOnDelete();
            $table->foreignId('quote_status_id')->constrained('quote_statuses')->restrictOnDelete();
            $table->foreignId('commercial_id')->nullable()->constrained('referents')->restrictOnDelete();
            $table->foreignId('reporter_id')->nullable()->constrained('referents')->restrictOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('internal_notes')->nullable();
            $table->decimal('revenue_net', 15, 2)->default(0);
            $table->decimal('revenue_vat', 15, 2)->default(0);
            $table->decimal('cost_net', 15, 2)->default(0);
            $table->decimal('cost_vat', 15, 2)->default(0);
            $table->decimal('margin_net', 15, 2)->default(0);
            $table->timestamps();

            $table->index('opportunity_id');
            $table->index('quote_status_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
