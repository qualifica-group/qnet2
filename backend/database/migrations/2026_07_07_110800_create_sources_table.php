<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Source lookup entity (spec 0018): a full-CRUD module (id, name) used to
 * classify the provenance of registry records ("Anagrafiche"). No other
 * columns: classification only, mirroring ReferentType (spec 0016).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->id();

            // The external system's id for a row migrated from it (spec 0013):
            // NULL for native qnet rows, unique among migrated ones.
            $table->unsignedBigInteger('old_id')->nullable()->unique();

            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
