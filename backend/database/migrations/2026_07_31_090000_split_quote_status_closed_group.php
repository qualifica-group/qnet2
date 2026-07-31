<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Splits the terminal `closed` classification of `quote_statuses.group` into
 * its two outcomes, `closed_won` (chiuso positivo) and `closed_lost` (chiuso
 * negativo) — App\Enums\QuoteStatusGroup, which replaces the shared
 * App\Enums\StatusGroup for this module only (pipeline / opportunity statuses
 * keep the 3-value enum). The two system rows land on their own outcome:
 * "Accettata" (`won`) -> closed_won, "Rifiutata" (`lost`) -> closed_lost.
 * Any CUSTOM row previously classified `closed` maps to `closed_lost`: a
 * positive outcome is only ever assumed for the `won` system row, never
 * inferred for a user-created row.
 *
 * The `group` column stays `string(16)` (`closed_lost` is 11 chars), so no
 * schema change is needed — this is a data migration only.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('quote_statuses')
            ->where('system_key', 'won')
            ->update(['group' => 'closed_won']);

        DB::table('quote_statuses')
            ->where('group', 'closed')
            ->update(['group' => 'closed_lost']);
    }

    public function down(): void
    {
        DB::table('quote_statuses')
            ->whereIn('group', ['closed_won', 'closed_lost'])
            ->update(['group' => 'closed']);
    }
};
