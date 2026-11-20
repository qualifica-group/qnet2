<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0404, C-D1: a category whose offers must be transferred to another
 * Sede ("Trasferisci contatto") before they can be won into a contract.
 * A plain PER-NODE flag (like `is_selectable`): the example of use,
 * "Orientamento specialistico", is a child of a branch whose siblings do not
 * carry the obligation, so it cannot be root-owned. Defaults to false: the
 * existing catalogue keeps behaving as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->boolean('requires_contact_transfer_before_contract')->default(false)->after('generates_contract');
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('requires_contact_transfer_before_contract');
        });
    }
};
