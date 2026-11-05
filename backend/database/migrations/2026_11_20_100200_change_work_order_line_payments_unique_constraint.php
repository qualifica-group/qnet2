<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payments per commessa (spec 0358, D-6): the same offer line can sit in
 * several commesse (one per renewal period), each with its own payment row.
 * UNIQUE(quote_line_id) becomes UNIQUE(work_order_id, quote_line_id).
 *
 * MySQL errno 1553: the FK on `quote_line_id` leans on the old UNIQUE, so it
 * gets a dedicated index BEFORE the UNIQUE is dropped. SQLite does not
 * enforce this: the test suite cannot catch a wrong order.
 */
return new class extends Migration
{
    private const string TABLE = 'work_order_line_payments';

    private const string LINE_UNIQUE_INDEX = 'work_order_line_payments_quote_line_id_unique';

    private const string PAIR_UNIQUE_INDEX = 'work_order_line_payments_work_order_id_quote_line_id_unique';

    private const string LINE_FK_INDEX = 'work_order_line_payments_quote_line_id_index';

    public function up(): void
    {
        // Step 1: dedicated index for the FK on `quote_line_id`, plus the new pair UNIQUE
        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->index('quote_line_id', self::LINE_FK_INDEX);
            $table->unique(['work_order_id', 'quote_line_id'], self::PAIR_UNIQUE_INDEX);
        });

        // Step 2: the single-column UNIQUE goes
        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropUnique(self::LINE_UNIQUE_INDEX);
        });
    }

    public function down(): void
    {
        $this->assertNoLineInMultipleWorkOrders();

        // Step 1: restore the single-column UNIQUE (it sustains the FK again)
        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->unique('quote_line_id', self::LINE_UNIQUE_INDEX);
        });

        // Step 2: drop the pair UNIQUE and the dedicated index, now redundant
        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropUnique(self::PAIR_UNIQUE_INDEX);
            $table->dropIndex(self::LINE_FK_INDEX);
        });
    }

    private function assertNoLineInMultipleWorkOrders(): void
    {
        $conflicts = DB::table(self::TABLE)
            ->select('quote_line_id')
            ->groupBy('quote_line_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('quote_line_id');

        if ($conflicts->isEmpty()) {
            return;
        }

        throw new RuntimeException(
            'Cannot restore UNIQUE(quote_line_id) on work_order_line_payments: '.
            'quote_line_id(s) ['.$conflicts->implode(', ').'] have a payment row in more than one work order. '.
            'Remove the extra payment rows (keep exactly one per quote_line_id) before rolling back this migration.'
        );
    }
};
