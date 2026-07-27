<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the place of birth to a personal-data card as a reference to the geo
 * catalogue (cities), not free text: the comune is a lookup value, so it stays
 * consistent with the addresses of the same card and remains queryable.
 *
 * Meaningful only for a natural person (type=individual), so it is left
 * nullable — a company card keeps it null. `nullOnDelete` mirrors the addresses
 * table: removing a city from the catalogue must never delete an identity card.
 * Purely additive: no existing column is changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_data', function (Blueprint $table) {
            $table->foreignId('birth_city_id')
                ->nullable()
                ->after('birth_date')
                ->constrained('cities')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('personal_data', function (Blueprint $table) {
            $table->dropConstrainedForeignId('birth_city_id');
        });
    }
};
