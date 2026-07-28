<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Options for an ENUM-typed attribute (spec 0017). Deleting the owning
 * attribute cascades to its options; `value` is unique per attribute so a
 * product's typed value can reference it unambiguously.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('value', 191);
            $table->string('label', 191);

            // Presentation columns, aligned with `custom_field_options`
            // (spec 0021).
            $table->string('color', 32)->nullable();
            $table->string('icon', 191)->nullable();
            $table->boolean('is_default')->default(false);

            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['attribute_id', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_options');
    }
};
