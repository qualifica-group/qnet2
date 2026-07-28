<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Referent entity (spec 0016): a contact person/entity that reuses the
 * anagraphic stack of `users` unchanged via `HasPersonalData` (morph
 * `personable`, no schema change on `personal_data`/`contacts`/`addresses`).
 *
 * `name` is denormalized display data derived from the linked personal-data
 * card (mirrors `users.name`), indexed for the SSRM grid sort/search.
 * `referent_type_id` is an optional classification (nullOnDelete: removing a
 * type just clears it on the referent, it never cascades a delete).
 * `contact_scope` (internal|external, ReferentContactScopeEnum) is a plain
 * string column defaulting to 'internal'. `notes` is free text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referents', function (Blueprint $table) {
            $table->id();

            // The external system's id for a row migrated from it (spec 0013):
            // NULL for native qnet rows, unique among migrated ones.
            $table->unsignedBigInteger('old_id')->nullable()->unique();

            $table->string('name')->index();
            $table->foreignId('referent_type_id')->nullable()->constrained('referent_types')->nullOnDelete();
            $table->string('contact_scope')->default('internal');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referents');
    }
};
