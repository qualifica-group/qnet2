<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global, reusable attribute catalogue (spec 0017): a typed, dynamic field
 * assignable to any number of product categories via the `attribute_category`
 * pivot (next migration). `type` holds an App\CustomFields\FieldTypeRegistry
 * key, and the presentation columns (description/help_text/placeholder/icon/
 * config/relation_target) mirror `custom_field_definitions` (spec 0021), so a
 * catalogue attribute and a custom field render through the same shape.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();

            // The external system's id for a row migrated from it (spec 0013):
            // NULL for native qnet rows, unique among migrated ones.
            $table->unsignedBigInteger('old_id')->nullable()->unique();

            $table->string('code', 64)->unique();
            $table->string('name', 191);
            $table->string('type');
            $table->text('description')->nullable();
            $table->text('help_text')->nullable();
            $table->string('placeholder', 191)->nullable();
            $table->string('icon', 191)->nullable();
            $table->json('config')->nullable();
            $table->json('relation_target')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};
