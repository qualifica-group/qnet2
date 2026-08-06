<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0084: le "Informazioni aggiuntive" passano dall'Opportunita' all'Offerta.
 *
 * Due colonne, una per lato del meccanismo (spec 0061):
 *
 * - `product_categories.inherits_quote_attributes` e' la barriera di
 *   ereditarieta' del NUOVO contesto `quote`, indipendente da quelle dei
 *   contesti `product`/`opportunity`: una categoria puo' ereditare gli
 *   attributi Offerta dagli antenati e insieme rifiutare quelli Prodotto.
 *   Default `true`, come le due sorelle, cosi' le categorie esistenti
 *   ereditano invece di partire isolate.
 *
 * - `quotes.attribute_values` e' il gemello di `opportunities.attribute_values`
 *   che questa migrazione elimina: JSON, nullable, e deliberatamente FUORI da
 *   `#[Fillable]` sul model — scritta esclusivamente da QuoteAttributeValueWriter
 *   dopo validazione per-`code`, mai per mass assignment.
 *
 * Il drop di `opportunities.attribute_values` e' SENZA travaso (D-2, decisione
 * utente 2026-08-05): i valori raccolti finora sull'opportunita' non hanno un
 * destinatario univoco sull'offerta (un'opportunita' puo' averne N), e il
 * progetto non e' in produzione. La `down()` ricrea la colonna vuota: la
 * struttura torna, il contenuto no — stesso precedente delle migrazioni
 * 2026_08_05_110000/110100 della spec 0083.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->boolean('inherits_quote_attributes')
                ->default(true)
                ->after('inherits_opportunity_attributes');
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->json('attribute_values')->nullable()->after('internal_notes');
        });

        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropColumn('attribute_values');
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->json('attribute_values')->nullable();
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn('attribute_values');
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('inherits_quote_attributes');
        });
    }
};
