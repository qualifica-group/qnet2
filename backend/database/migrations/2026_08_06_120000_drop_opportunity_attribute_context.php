<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rimuove il contesto `opportunity` di App\Enums\AttributeContext.
 *
 * La spec 0084 (D-1) aveva gia' spostato le "Informazioni aggiuntive"
 * dall'Opportunita' all'Offerta: da allora nessun resolver di produzione
 * chiedeva piu' il contesto `opportunity` (App\Products\ProductAttributeResolver
 * usa Product, App\Quotes\QuoteAttributeResolver usa Quote), ma la superficie
 * di CONFIGURAZIONE era rimasta — assegnazioni salvabili, layout disegnabili,
 * barriera di ereditarieta' persistita, tutto senza un lettore. Questa
 * migrazione chiude il cerchio eliminando i dati orfani e la colonna.
 *
 * Tre effetti, tutti allineati alla stessa decisione (utente 2026-08-06):
 *
 * - le righe `attribute_category` e `attribute_layouts` con
 *   `context = 'opportunity'` sono CANCELLATE: dopo il drop del case enum,
 *   rileggerle farebbe fallire AttributeContext::from().
 *
 * - `attribute_category.context` perde il default `'opportunity'`. Non e'
 *   sostituito da un altro contesto: il default esisteva solo per la
 *   retro-compatibilita' col modello mono-contesto pre-0061, e ogni scrittura
 *   (ProductCategoryService::syncAttributes) nomina gia' il contesto in modo
 *   esplicito.
 *
 * - `product_categories.inherits_opportunity_attributes` e' droppata:
 *   restano le due barriere dei contesti vivi.
 *
 * La `down()` ricrea struttura e default, NON i dati: le assegnazioni e i
 * layout `opportunity` cancellati non sono ripristinabili. Stesso precedente
 * della 2026_08_06_100000 (drop di `opportunities.attribute_values` senza
 * travaso).
 */
return new class extends Migration
{
    private const string DROPPED_CONTEXT = 'opportunity';

    public function up(): void
    {
        DB::table('attribute_category')->where('context', self::DROPPED_CONTEXT)->delete();
        DB::table('attribute_layouts')->where('context', self::DROPPED_CONTEXT)->delete();

        Schema::table('attribute_category', function (Blueprint $table) {
            $table->string('context')->change();
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('inherits_opportunity_attributes');
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->boolean('inherits_opportunity_attributes')
                ->default(true)
                ->after('inherits_product_attributes');
        });

        Schema::table('attribute_category', function (Blueprint $table) {
            $table->string('context')->default(self::DROPPED_CONTEXT)->change();
        });
    }
};
