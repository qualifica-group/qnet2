<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-off DATA backfill for spec 0087's `quote_user`/`quotes.operator_id`
 * (D-12). Direttiva utente 2026-08-31: the database carries no production
 * data, so this is deliberately best-effort and minimal, not a
 * behaviour-preservation contract:
 *   - every existing Offerta's Opportunita' managers are copied onto it, at
 *     the SAME positions (the everyday case, no `quotes.supervisor_id`);
 *   - where `quotes.supervisor_id` IS set, that user becomes the OPERATOR
 *     slot (position `App\Support\ManagerPositions::OPERATOR` = 2 — a plain
 *     literal here, migrations never import App\ classes), displacing
 *     whoever the opportunity copy put there and dropped from any other
 *     position it may already hold, so both `quote_user` unique constraints
 *     (`[quote_id,user_id]`/`[quote_id,position]`) stay satisfied;
 *   - `quotes.operator_id` is set to whichever user ends up at that slot
 *     (possibly none), never left to independently drift from it (D-3).
 *
 * `down()` reverses the DATA this migration wrote — empties `quote_user` and
 * nulls `quotes.operator_id` — it does NOT touch the SCHEMA: the
 * `quote_user` table and the `operator_id` column are owned (created AND
 * dropped) by their own migrations, not this one.
 */
return new class extends Migration
{
    private const int OPERATOR_POSITION = 2;

    public function up(): void
    {
        $quotes = DB::table('quotes')->select('id', 'opportunity_id', 'supervisor_id')->get();

        if ($quotes->isEmpty()) {
            return;
        }

        $managersByOpportunity = DB::table('opportunity_user')
            ->select('opportunity_id', 'user_id', 'position')
            ->get()
            ->groupBy('opportunity_id');

        foreach ($quotes as $quote) {
            $rows = ($managersByOpportunity->get($quote->opportunity_id) ?? collect())
                ->keyBy('user_id')
                ->map(fn ($manager): array => [
                    'quote_id' => $quote->id,
                    'user_id' => $manager->user_id,
                    'position' => $manager->position,
                ]);

            if ($quote->supervisor_id !== null) {
                $rows = $rows->reject(fn (array $row): bool => $row['user_id'] === $quote->supervisor_id
                    || $row['position'] === self::OPERATOR_POSITION);
                $rows->put($quote->supervisor_id, [
                    'quote_id' => $quote->id,
                    'user_id' => $quote->supervisor_id,
                    'position' => self::OPERATOR_POSITION,
                ]);
            }

            if ($rows->isNotEmpty()) {
                DB::table('quote_user')->insert($rows->values()->all());
            }

            $operatorRow = $rows->firstWhere('position', self::OPERATOR_POSITION);

            DB::table('quotes')->where('id', $quote->id)->update([
                'operator_id' => $operatorRow['user_id'] ?? null,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('quote_user')->delete();
        DB::table('quotes')->update(['operator_id' => null]);
    }
};
