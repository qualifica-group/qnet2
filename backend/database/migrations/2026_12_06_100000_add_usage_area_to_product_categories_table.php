<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which modules a product-category branch is used in (spec 0389, D-1):
 * `all`, `requests` (Gestione Richieste / Iscritti) or `opportunities`
 * (Opportunita' / Offerte). OWNED BY THE ROOT and denormalised onto every
 * descendant by CategoryUsageAreaInheritance, same shape as
 * `management_mode`. Defaults to `all`: no category changes at deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->string('usage_area', 20)->default('all')->after('multi_quote_work_order');
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('usage_area');
        });
    }
};
