<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VatRate lookup entity: a full-CRUD module (id, name, rate) used to
 * classify the VAT percentage applied to a Product, mirroring Source (spec
 * 0018).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vat_rates', function (Blueprint $table) {
            $table->id();

            // The external system's id for a row migrated from it (spec 0013):
            // resolves `products.vat_rate_id` on remap. NULL for native qnet
            // rows, unique among migrated ones.
            $table->unsignedBigInteger('old_id')->nullable()->unique();

            $table->string('name');
            $table->decimal('rate', 5, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vat_rates');
    }
};
