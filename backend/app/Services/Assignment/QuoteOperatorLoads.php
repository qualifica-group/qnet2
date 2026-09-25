<?php

declare(strict_types=1);

namespace App\Services\Assignment;

use Illuminate\Support\Facades\DB;

/**
 * Current load per operator on the Quote-backed domains (Gestione
 * richieste/Iscritti, spec 0130 D-9): the count of OFFERS
 * (`quotes.operator_id`) each operator already operates (AC-033) — the
 * exact initial load `RequestAssignmentService::distributeBalanced()` feeds
 * `LeadOperatorDistributor::distributeAmong()`. Extracted here (spec 0168)
 * so `selection-scope`'s `balanced_groups` answers the SAME number instead
 * of a second, divergent implementation of the query.
 */
final class QuoteOperatorLoads
{
    /**
     * @param  array<int, int>  $operatorIds
     * @return array<int, int> operatorId => load
     */
    public function currentLoads(array $operatorIds): array
    {
        if ($operatorIds === []) {
            return [];
        }

        return DB::table('quotes')
            ->whereIn('operator_id', $operatorIds)
            ->selectRaw('operator_id, COUNT(*) as aggregate')
            ->groupBy('operator_id')
            ->pluck('aggregate', 'operator_id')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();
    }
}
