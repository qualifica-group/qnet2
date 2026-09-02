<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0098: gli Attributi Flessibili arrivano sulla Commessa, terzo
 * contesto indipendente `work_order` (D-2).
 *
 * Due colonne, gemelle di quelle aggiunte da 2026_08_06_100000 per il
 * contesto `quote`:
 *
 * - `product_categories.inherits_work_order_attributes` e' la barriera di
 *   ereditarieta' del NUOVO contesto, indipendente da `inherits_product_
 *   attributes`/`inherits_quote_attributes`: una categoria puo' ereditare gli
 *   attributi Commessa dagli antenati e insieme rifiutare quelli
 *   Prodotto/Offerta. Default `true`, come le due sorelle, cosi' le
 *   categorie esistenti ereditano invece di partire isolate.
 *
 * - `work_orders.attribute_values` e' JSON nullable, FUORI da `#[Fillable]`
 *   sul model (D-5) — scritta esclusivamente da WorkOrderAttributeValueWriter
 *   dopo validazione per-`code`, mai per mass assignment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->boolean('inherits_work_order_attributes')
                ->default(true)
                ->after('inherits_quote_attributes');
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->json('attribute_values')->nullable()->after('internal_notes');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn('attribute_values');
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('inherits_work_order_attributes');
        });
    }
};
