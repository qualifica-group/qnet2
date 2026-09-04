<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Prossimo richiamo" passa dall'Opportunita' all'Offerta (direttiva utente
 * 2026-09-04): il richiamo pianificato e' per-OFFERTA, non per-trattativa —
 * due Offerte sorelle della stessa Opportunita' pianificano il proprio,
 * mentre finora condividevano la stessa cella. Stesso movimento gia' fatto
 * dalla spec 0084 per `attribute_values`.
 *
 * `next_callback_reminded_at` (il marker del promemoria, spec 0052 D-4)
 * viaggia con essa: fuori dall'istante che marca non significa nulla.
 *
 * Il travaso e' 1 -> N e quindi NON ambiguo (ogni Offerta eredita il valore
 * della propria Opportunita'): nessun richiamo pianificato va perso.
 * La `down()` invece ricrea le colonne VUOTE — la direzione inversa e' N -> 1
 * e non ha un vincitore univoco: stesso precedente della migrazione
 * 2026_08_06_100000 (D-2, decisione utente 2026-08-05).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->dateTime('next_callback_at')->nullable()->after('attribute_values');
            $table->dateTime('next_callback_reminded_at')->nullable()->after('next_callback_at');
            $table->index('next_callback_at');
        });

        DB::table('quotes')->update([
            'next_callback_at' => DB::raw('(select opportunities.next_callback_at from opportunities where opportunities.id = quotes.opportunity_id)'),
            'next_callback_reminded_at' => DB::raw('(select opportunities.next_callback_reminded_at from opportunities where opportunities.id = quotes.opportunity_id)'),
        ]);

        Schema::table('opportunities', function (Blueprint $table): void {
            $table->dropIndex(['next_callback_at']);
            $table->dropColumn(['next_callback_at', 'next_callback_reminded_at']);
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->dateTime('next_callback_at')->nullable();
            $table->dateTime('next_callback_reminded_at')->nullable();
            $table->index('next_callback_at');
        });

        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropIndex(['next_callback_at']);
            $table->dropColumn(['next_callback_at', 'next_callback_reminded_at']);
        });
    }
};
