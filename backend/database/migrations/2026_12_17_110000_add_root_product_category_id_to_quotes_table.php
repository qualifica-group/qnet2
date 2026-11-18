<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0411, D-1: the root product category ("categoria madre") an offer
 * created from Gestione Richieste is bound to — one offer per root of the
 * request's product lines. Null = unbound, the behaviour every existing offer
 * keeps (it reads all of its opportunity's product lines).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->foreignId('root_product_category_id')->nullable()->after('opportunity_id')
                ->constrained('product_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('root_product_category_id');
        });
    }
};
