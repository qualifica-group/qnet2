<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Una riga, una commessa PER PERIODO" (spec 0358, D-3): the pivot gets the
 * renewal period of the line and the UNIQUE(quote_line_id) of spec 0095
 * becomes UNIQUE(quote_line_id, renewal_number). Existing rows stay in the
 * period 0 (column default): no backfill.
 *
 * MySQL errno 1553: the FK on `quote_line_id` leans on the old UNIQUE. The
 * new composite starts with `quote_line_id`, so it sustains the FK and is
 * created BEFORE the old one is dropped. SQLite does not enforce this, so the
 * test suite cannot catch a wrong order: keep the steps as they are.
 */
return new class extends Migration
{
    private const string TABLE = 'quote_line_work_order';

    private const string LINE_UNIQUE_INDEX = 'quote_line_work_order_quote_line_id_unique';

    private const string PERIOD_UNIQUE_INDEX = 'quote_line_work_order_quote_line_id_renewal_number_unique';

    private const string LINE_FK_INDEX = 'quote_line_work_order_quote_line_id_foreign';

    public function up(): void
    {
        // Step 1: the period column and the composite UNIQUE (still alongside the old one)
        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->unsignedSmallInteger('renewal_number')->default(0);
            $table->unique(['quote_line_id', 'renewal_number'], self::PERIOD_UNIQUE_INDEX);
        });

        // Step 2: the old single-column UNIQUE is now redundant
        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropUnique(self::LINE_UNIQUE_INDEX);
        });
    }

    public function down(): void
    {
        $this->assertNoLineInMultiplePeriods();

        // Step 1: restore the single-column UNIQUE, with a dedicated FK index first (errno 1553)
        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->index('quote_line_id', self::LINE_FK_INDEX);
        });

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropUnique(self::PERIOD_UNIQUE_INDEX);
            $table->unique('quote_line_id', self::LINE_UNIQUE_INDEX);
        });

        // Step 2: the UNIQUE sustains the FK again, the dedicated index and the column go
        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropIndex(self::LINE_FK_INDEX);
            $table->dropColumn('renewal_number');
        });
    }

    /**
     * Raises a readable exception instead of an opaque integrity error when a
     * line is attached in more than one period.
     */
    private function assertNoLineInMultiplePeriods(): void
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
            'Cannot restore UNIQUE(quote_line_id) on quote_line_work_order: '.
            'quote_line_id(s) ['.$conflicts->implode(', ').'] are attached in more than one renewal period. '.
            'Remove the renewal-period rows (keep exactly one per quote_line_id) before rolling back this migration.'
        );
    }
};
