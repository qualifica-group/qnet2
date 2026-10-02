<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A product-category node's OWN declaration of the simplified offer-line rule
 * (spec 0188), revising spec 0114 D-1: null = inherit the parent's effective
 * value, true/false = forced on this node and passed down to its subtree
 * (nearest-ancestor semantics, same shape as `is_reportable`). Always null on
 * a root, which declares the rule through `simplified_offer_line` itself.
 *
 * `simplified_offer_line` stays the denormalised EFFECTIVE value on every row,
 * so every reader is untouched and no backfill is needed: all rows start null,
 * i.e. exactly the root-mirrored behaviour of today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->boolean('simplified_offer_line_override')->nullable()->default(null)->after('simplified_offer_line');
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('simplified_offer_line_override');
        });
    }
};
