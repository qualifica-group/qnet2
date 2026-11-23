<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0410: the kind of anagrafica (PersonalDataTypeEnum) the new-client card
 * of the Gestione Richieste create form starts on for a product-category
 * branch. OWNED BY THE ROOT and denormalised onto every descendant by
 * RequestRegistryTypeInheritance, same shape as `usage_area`. Defaults to
 * `individual`, the card's behaviour until now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->string('request_registry_type', 20)->default('individual')->after('multiple_open_opportunities');
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('request_registry_type');
        });
    }
};
