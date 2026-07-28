<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reusable address module.
 *
 * Designed as a drop-in component: addresses are attached to any owning entity
 * through a nullable polymorphic relation (addressable_type / addressable_id),
 * so future models (users, companies, locations, ...) can own one or many
 * addresses without a schema change. The relation is nullable so an address can
 * also exist on its own before being linked.
 *
 * Geo fields reference the existing reference tables (countries / states /
 * cities) and are nullable, plus denormalized latitude / longitude for
 * geolocation. No controller, API or business logic is exposed yet — this only
 * provides the data structure and its model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();

            // Polymorphic owner: any model can own addresses via morphMany/morphOne.
            // Nullable so an address may exist before being attached to an entity.
            $table->nullableMorphs('addressable');

            // Marks the preferred address of an owner. The "at most one primary
            // per owner" invariant is enforced in AddressService (no DB-level
            // constraint is possible on a polymorphic relation); the composite
            // index below keeps the demote-siblings lookup cheap.
            $table->boolean('is_primary')->default(false);

            // Street parts kept separate so callers can format them as needed.
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('postal_code', 20)->nullable();

            // Which kind of location this address represents (legal seat,
            // delivery, billing, operational site — spec 0020). Only the
            // Registries form renders the select (showSiteType opt-in).
            $table->string('site_type')->default('billing');

            // Geo references to the existing lookup tables. Nullable + nullOnDelete:
            // losing a reference row must not delete the address it belongs to.
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->foreignId('province_id')->nullable()->constrained('provinces')->nullOnDelete();
            $table->foreignId('state_id')->nullable()->constrained('states')->nullOnDelete();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();

            // Geolocation. Precision covers the full coordinate ranges
            // (lat -90..90, lng -180..180) down to ~1cm.
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            $table->timestamps();

            $table->index(
                ['addressable_type', 'addressable_id', 'is_primary'],
                'addresses_owner_primary_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
