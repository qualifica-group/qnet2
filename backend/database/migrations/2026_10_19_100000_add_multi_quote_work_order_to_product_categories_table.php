<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Work orders of this branch may span several quotes" policy for the
 * product-category tree (spec 0267, D-1): when true, revenue lines of the
 * branch coming from different contracts can be scheduled into one single
 * work order.
 *
 * OWNED BY THE ROOT of each branch and denormalised onto every descendant,
 * same shape as `generates_contract` — MultiQuoteWorkOrderInheritance is the
 * single writer that keeps the subtree aligned. Defaults to false: today a
 * work order always belongs to exactly one quote.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->boolean('multi_quote_work_order')->default(false)->after('generates_contract');
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('multi_quote_work_order');
        });
    }
};
