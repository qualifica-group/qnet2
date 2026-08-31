<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `quotes.operator_id` (spec 0087, D-3): a DENORMALIZED projection of the
 * `quote_user` pivot row at `App\Support\ManagerPositions::OPERATOR`
 * (position 2) — the GA2 "Operatore", now the Offerta's own, replacing the
 * former `quotes.supervisor_id` as the Gestione Richieste ownership column
 * (D-9). nullOnDelete, coherent with the pivot's own cascadeOnDelete: a
 * deleted user drops its `quote_user` row AND clears this projection in the
 * same stroke, so the two never drift apart on that path.
 *
 * OBLIGATION OF COHERENCE (D-3): this column is written by exactly ONE
 * point, `App\Services\Quotes\QuoteManagerWriter` — no other code path
 * touches it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->foreignId('operator_id')->nullable()
                ->after('supervisor_id')->constrained('users')->nullOnDelete();
            $table->index('operator_id');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropForeign(['operator_id']);
            $table->dropIndex(['operator_id']);
            $table->dropColumn('operator_id');
        });
    }
};
