<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reusable personal-data module.
 *
 * A PersonalData card is the identity sheet of an owning entity (a user, a
 * supplier, a customer, ...). It is attached through a nullable polymorphic
 * relation (personable_type / personable_id), so any model can own exactly one
 * card via morphOne (HasPersonalData) without a schema change — and a card can
 * also exist standalone before being linked.
 *
 * It covers both natural persons (type=individual: first/last name,
 * birth date, tax code) and legal entities (type=company: company name, VAT
 * number). `full_name` and `ceo` are PHP accessors on the model, not stored
 * columns. No controller/API is exposed yet — data layer + service only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_data', function (Blueprint $table) {
            $table->id();

            // Polymorphic owner: any model can own one card via morphOne.
            // Nullable so a card may exist before being attached to an entity.
            $table->nullableMorphs('personable');

            // individual | company (PersonalDataTypeEnum).
            $table->string('type');

            // Natural-person fields (type=individual).
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();

            // Legal-entity field (type=company).
            $table->string('company_name')->nullable();

            // Fiscal identifiers. Indexed because they are the natural lookup
            // keys when searching/deduplicating people and companies.
            $table->string('tax_code')->nullable()->index();
            $table->string('vat_number')->nullable()->index();

            // The SDI recipient code (Codice Destinatario), the routing address
            // of the Italian e-invoicing system. Meaningful only for a legal
            // entity; neither a sensitive identifier nor a dedup key, so
            // un-indexed.
            $table->string('sdi_code')->nullable();

            $table->date('birth_date')->nullable();

            // Place of birth as a reference to the geo catalogue, not free text.
            // nullOnDelete mirrors `addresses`: removing a city from the
            // catalogue must never delete an identity card.
            $table->foreignId('birth_city_id')->nullable()->constrained('cities')->nullOnDelete();

            // Biological sex (GenderEnum). Meaningful only for a natural person,
            // so nullable — a company card keeps it null.
            $table->string('gender')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_data');
    }
};
