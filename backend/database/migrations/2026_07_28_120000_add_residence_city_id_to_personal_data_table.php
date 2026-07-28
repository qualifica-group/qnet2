<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comune of residence on the identity card, the twin of `birth_city_id`.
 *
 * It is a reference to the geo catalogue rather than an address row: the card
 * already owns full addresses (HasAddresses) for where post is delivered, while
 * residence is a single administrative fact about a natural person — the one
 * asked for on Italian forms next to the place of birth.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_data', function (Blueprint $table) {
            // nullOnDelete mirrors `birth_city_id`: removing a comune from the
            // catalogue must never delete an identity card.
            $table->foreignId('residence_city_id')
                ->nullable()
                ->after('birth_city_id')
                ->constrained('cities')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('personal_data', function (Blueprint $table) {
            $table->dropConstrainedForeignId('residence_city_id');
        });
    }
};
