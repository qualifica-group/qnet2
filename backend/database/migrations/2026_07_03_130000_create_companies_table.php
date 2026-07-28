<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company entity (spec 0010 — "Società aziendali"): a denomination, an
 * optional VAT number, and a single address on the polymorphic `addresses`
 * table (via HasAddresses). No status/type column in this slice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();

            // The external system's id for a row migrated from it (spec 0013):
            // NULL for native qnet rows, unique among migrated ones.
            $table->unsignedBigInteger('old_id')->nullable()->unique();

            $table->string('denomination');
            $table->string('vat_number', 50)->nullable();
            $table->timestamps();

            // Grid search/sort on denomination; default sort on created_at.
            $table->index('denomination');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
