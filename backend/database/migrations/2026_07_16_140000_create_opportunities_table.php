<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opportunity entity (spec 0040): a commercial deal against an Anagrafica
 * (`registries`), created manually or generated from a Lead (`lead_id`,
 * nullable, UNIQUE — at most one opportunity per lead, D-2). The owner
 * relations are `restrictOnDelete` (BR-3): nothing referenced by an
 * opportunity may be deleted while it exists, mirrored at the app layer by an
 * `abort(409, ...)` guard in each referenced module's Service (Registry/
 * Referent/User/Source/Lead/OpportunityStatus), mirroring the leads
 * migration's discipline. The optional/derived references (sede, Regione,
 * working status) are nullOnDelete instead — see their own comments below.
 *
 * `name`/`registry_id`/`opportunity_status_id` are NOT NULL; every other
 * relation is optional. The business function and product category are NOT
 * columns here: they live one-to-many on `opportunity_product_lines` (spec
 * 0040 amendment rev.3). `estimated_value` mirrors the
 * `projects.total_budget`/decimal(15,2) convention.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->index();
            $table->foreignId('registry_id')->constrained('registries')->restrictOnDelete();
            $table->foreignId('referent_id')->nullable()->constrained('referents')->restrictOnDelete();
            $table->foreignId('commercial_id')->nullable()->constrained('referents')->restrictOnDelete();
            $table->foreignId('reporter_id')->nullable()->constrained('referents')->restrictOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('source_id')->nullable()->constrained('sources')->restrictOnDelete();

            // Sede (spec 0056): an OPTIONAL relation, hence nullOnDelete — a
            // deliberate deviation from this table's other FKs (BR-3): losing the
            // referenced site clears the field rather than blocking its deletion.
            $table->foreignId('operational_site_id')->nullable()->constrained('operational_sites')->nullOnDelete();

            $table->foreignId('lead_id')->nullable()->unique()->constrained('leads')->restrictOnDelete();

            // The sales pipeline status (spec 0043, D-3/BR-2): mandatory.
            $table->foreignId('opportunity_status_id')->constrained('opportunity_statuses')->restrictOnDelete();

            // Regione (spec 0047, D1), inherited from the originating Lead at
            // conversion, and the resolved working state — a dimension DISTINCT
            // from the pipeline status above, always written by
            // OpportunityWorkflowResolver, never user-mass-assignable. Both
            // nullOnDelete: losing the referenced row must not cascade or block
            // the Opportunity — the resolver re-derives it.
            $table->foreignId('state_id')->nullable()->constrained('states')->nullOnDelete();
            $table->foreignId('opportunity_workflow_status_id')->nullable()->constrained('opportunity_workflow_statuses')->nullOnDelete();

            // Dynamic-field values (spec 0049, D-4) keyed by Attribute `code` —
            // the union of the effective Attributes of every product-category on
            // the Opportunity's product lines. Written EXCLUSIVELY by
            // RequestManagementService, never mass-assignable.
            $table->json('attribute_values')->nullable();

            // Next-callback planning (spec 0052, D-1). `next_callback_at` is
            // operator-editable and indexed (the RequestManagement table sorts
            // and filters on it); `next_callback_reminded_at` is a marker column
            // reserved for a future reminder job, exposed by no API. Both stay
            // outside #[Fillable] — written only by RequestManagementService.
            $table->dateTime('next_callback_at')->nullable()->index();
            $table->dateTime('next_callback_reminded_at')->nullable();

            $table->date('start_date')->nullable();
            $table->decimal('estimated_value', 15, 2)->nullable();
            $table->date('expected_close_date')->nullable();
            $table->unsignedTinyInteger('success_probability')->nullable();

            // Free text inherited from the originating Lead's own `notes` at
            // conversion. Deliberately NOT named `notes`: Opportunity already
            // carries a `notes()` morphMany (the collaborative thread, spec 0052)
            // that a column of that name would shadow.
            $table->text('general_notes')->nullable();

            $table->timestamps();

            $table->index('registry_id');
            $table->index('operational_site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
