<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registry entity (spec 0020, "Anagrafiche"): a client/supplier record that
 * reuses the anagraphic stack of `users`/`referents` unchanged via
 * `HasPersonalData` (morph `personable`, no schema change on
 * `personal_data`/`contacts`/`addresses`).
 *
 * `name` is denormalized display data derived from the linked personal-data
 * card (mirrors `referents.name`/`users.name`), indexed for the SSRM grid
 * sort/search. `source_id`/`supervisor_id`/`commercial_id`/`reporter_id` are
 * nullable, nullOnDelete (losing the referenced row just clears the field, it
 * never cascades a delete of the registry). The supervisor of the relationship
 * is an INTERNAL user (like the `managers` pivot), not an external referent —
 * commercial/reporter stay on referents. `is_supplier`/
 * `is_qualified_supplier` default false (the latter is server-normalized to
 * false whenever the former is false — see RegistryService).
 * `agreement_status`/`size_class` are plain nullable string columns (enum
 * cast on the model). `employee_count` is a nullable unsigned integer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registries', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->foreignId('source_id')->nullable()->constrained('sources')->nullOnDelete();
            $table->string('vat_group')->nullable();
            $table->boolean('is_supplier')->default(false);
            $table->boolean('is_qualified_supplier')->default(false);
            $table->string('agreement_status')->nullable();
            $table->text('agreement_notes')->nullable();
            $table->string('size_class')->nullable();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('commercial_id')->nullable()->constrained('referents')->nullOnDelete();
            $table->foreignId('reporter_id')->nullable()->constrained('referents')->nullOnDelete();
            $table->unsignedInteger('employee_count')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registries');
    }
};
