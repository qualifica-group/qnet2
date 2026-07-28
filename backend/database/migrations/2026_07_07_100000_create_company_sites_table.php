<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company site entity (spec 0020 — "Società Sedi"): a flexible site
 * anagraphic under a Company. Its contacts + address live on a polymorphic
 * personal-data card (`HasPersonalData`, morph `personable`), NOT as flat
 * columns on this table — mirroring the Registry module. A logo (polymorphic
 * `attachments`, HasAttachments) and an owned bank list (`company_site_banks`,
 * real FK) complete it. `old_id` is additive (spec 0013 external migration),
 * declared inline since this table is greenfield.
 *
 * The preferred bank is a flag on the bank rows themselves
 * (`company_site_banks.is_primary`), not a `default_bank_id` FK here: that
 * keeps the two tables from referencing each other.
 *
 * The former "Altro" section attributes (store, categories, payment statuses,
 * ...) and the client-specific ERP fields (the responsible_* users, the
 * proforma/invoice progressives, the quotation references) are not flat
 * columns: they live as universal custom fields (spec 0021), provisioned by
 * QualificaTemplateSeeder. Only `company_id` (the owning società) remains a
 * real column here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_sites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('old_id')->nullable();
            $table->unique('old_id');

            // Profilo. The site's own display name (NOT derived from the card,
            // unlike Registry). Contacts + address live on the personal-data
            // card (morph `personable`), not here.
            $table->string('name', 191);
            $table->text('notes')->nullable();
            $table->boolean('is_default')->default(false)->index();

            // The owning company (società). The former "Altro"/ERP attributes
            // are now universal custom fields (spec 0021,
            // QualificaTemplateSeeder), not flat columns.
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();

            $table->timestamps();

            // Grid search/sort (spec 0020 contract: searchable name, default
            // sort created_at). Email/vat_number are no longer real columns
            // (they live on the personal-data card / its contacts).
            $table->index('name');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_sites');
    }
};
