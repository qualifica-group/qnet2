<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Simplified offer line" policy for the product-category tree (spec 0114):
 * when true, a request-management offer line of the branch drops the
 * quantity/unit-price/VAT-rate controls — the operator only picks the
 * product, and the server freezes those three values from it.
 *
 * OWNED BY THE ROOT of each branch, identical shape to `requires_quote`,
 * `management_mode`, `single_quote_per_opportunity` and `generates_contract`:
 * only a category without a parent authors it, every descendant carries the
 * root's value verbatim, and the column is denormalised onto every row
 * rather than resolved by a walk at read time —
 * SimplifiedOfferLineInheritance is the single writer that keeps the subtree
 * aligned.
 *
 * Defaults to FALSE: the pre-existing behaviour is a fully manual offer
 * line, so false is what leaves an existing catalogue untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->boolean('simplified_offer_line')->default(false)->after('generates_contract');
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('simplified_offer_line');
        });
    }
};
