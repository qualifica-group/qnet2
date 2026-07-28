<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Referent type lookup entity (spec 0016): a full-CRUD module (id, name) that
 * feeds the "Referent type" select of the `referents` module via the
 * standard for-select endpoint. No other columns: classification only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referent_types', function (Blueprint $table) {
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
        Schema::dropIfExists('referent_types');
    }
};
