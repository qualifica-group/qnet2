<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0267, D-6: a proforma request is raised for ONE offer, since a
 * multi-offer commessa has no single offer of its own. Existing rows are
 * backfilled from their work order (AC-012), then the column becomes NOT NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Step 1: colonna nullable con FK e indice
        Schema::table('proforma_requests', function (Blueprint $table): void {
            $table->foreignId('quote_id')->nullable()->after('work_order_id')
                ->constrained('quotes')->restrictOnDelete();
        });

        // Step 2: backfill dalla commessa (tutte le richieste esistenti sono mono-offerta)
        DB::table('proforma_requests')->update([
            'quote_id' => DB::table('work_orders')
                ->select('quote_id')
                ->whereColumn('work_orders.id', 'proforma_requests.work_order_id'),
        ]);

        // Step 3: NOT NULL
        Schema::table('proforma_requests', function (Blueprint $table): void {
            $table->foreignId('quote_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('proforma_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('quote_id');
        });
    }
};
