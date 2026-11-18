<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0414: when true, Gestione Richieste hides the offer section for a
 * product-category branch. OWNED BY THE ROOT and denormalised onto every
 * descendant by RequestOfferHiddenInheritance, same shape as
 * `multiple_open_opportunities`. Defaults to false (offer visible), the
 * behaviour until now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->boolean('request_offer_hidden')->default(false)->after('request_registry_type');
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('request_offer_hidden');
        });
    }
};
