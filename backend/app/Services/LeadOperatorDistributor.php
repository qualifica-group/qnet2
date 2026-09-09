<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Business-rule br-balanced (spec 0048): "Smistamento equo" — load-balanced
 * distribution of Lead/import-row targets across a Sede's operators. Shared
 * by LeadAssignmentService (real leads, POST /leads/assign-operators) and
 * ImportService::bulkAssign (staged import rows) so the SAME algorithm and
 * the SAME "current load" definition (count of REAL leads per operator)
 * back both endpoints.
 *
 * `distribute()` is the pure, DB-free core (operator ids + their initial
 * loads + an ordered target list in, a target-id => operator-id map out) —
 * unit-testable in isolation. `operatorIdsForSite()`/`currentLoads()` are the
 * two small DB queries every caller otherwise duplicates.
 */
class LeadOperatorDistributor
{
    /**
     * Operators of a Sede: any user whose employment profile holds that Sede
     * on the `employment_profile_operational_site` pivot, PHYSICAL or REMOTE
     * (spec 0103, D-1 — a remote membership is operative exactly like the
     * physical one), ordered by id ascending (br-balanced step 1). No
     * role/permission filter (user directive 2026-07-21): any user employed
     * at that Sede qualifies.
     *
     * @return array<int, int>
     */
    public function operatorIdsForSite(int $operationalSiteId): array
    {
        return User::query()
            ->whereHas('employment.operationalSites', function (Builder $sitesQuery) use ($operationalSiteId): void {
                $sitesQuery->where('operational_sites.id', $operationalSiteId);
            })
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    /**
     * Current load per operator (br-balanced step 2): the count of REAL
     * leads already assigned to each of $operatorIds. An operator with zero
     * leads is simply absent from the map (the caller defaults it to 0).
     *
     * @param  array<int, int>  $operatorIds
     * @return array<int, int> operatorId => load
     */
    public function currentLoads(array $operatorIds): array
    {
        if ($operatorIds === []) {
            return [];
        }

        return Lead::query()
            ->whereIn('operator_id', $operatorIds)
            ->selectRaw('operator_id, COUNT(*) as aggregate')
            ->groupBy('operator_id')
            ->pluck('aggregate', 'operator_id')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * Greedily assign each of $targetIds (in the given order — br-balanced
     * step 3 requires an ascending, deterministic order) to the
     * least-loaded operator, ties broken by the LOWEST operator id, then
     * increment that operator's load before moving to the next target. Pure
     * function: no query, no side effect, fully testable in isolation.
     *
     * @param  array<int, int>  $operatorIds  ordered ascending, non-empty
     * @param  array<int, int>  $initialLoads  operatorId => load (missing = 0)
     * @param  array<int, int>  $targetIds  ordered ascending
     * @return array<int, int> targetId => operatorId
     */
    public function distribute(array $operatorIds, array $initialLoads, array $targetIds): array
    {
        if ($operatorIds === []) {
            throw new InvalidArgumentException('At least one operator is required to distribute targets.');
        }

        return $this->distributeAmong(array_fill_keys($targetIds, $operatorIds), $initialLoads);
    }

    /**
     * distribute() when every target has its OWN candidate pool (spec 0110):
     * the competence filter narrows the Sede's operators record by record, so
     * the greedy must pick the least-loaded operator AMONG THAT RECORD'S
     * candidates while the load map stays shared across the whole batch —
     * otherwise two records with disjoint pools would each rebalance in
     * isolation. Same algorithm, same tie-break, one implementation.
     *
     * A target whose candidate list is EMPTY (nobody is competent for it) is
     * SKIPPED: it is absent from the result, which is how the caller counts
     * it as `skipped` instead of forcing it onto an incompetent operator.
     *
     * @param  array<int, array<int, int>>  $candidatesByTarget  targetId => candidate operator ids
     * @param  array<int, int>  $initialLoads  operatorId => load (missing = 0)
     * @return array<int, int> targetId => operatorId
     */
    public function distributeAmong(array $candidatesByTarget, array $initialLoads): array
    {
        // Step 1: one shared load map over the union of every pool, keyed in
        // ascending operator id so leastLoadedOperatorId()'s insertion-order
        // tie-break resolves to the LOWEST id (br-balanced step 3).
        $loads = [];
        foreach ($this->unionOfCandidates($candidatesByTarget) as $operatorId) {
            $loads[$operatorId] = $initialLoads[$operatorId] ?? 0;
        }

        // Step 2: walk the targets in ascending order — the distribution must
        // not depend on the order the caller happened to collect them in.
        ksort($candidatesByTarget);

        $assignments = [];
        foreach ($candidatesByTarget as $targetId => $candidateIds) {
            if ($candidateIds === []) {
                continue;
            }

            $operatorId = $this->leastLoadedOperatorId(array_intersect_key($loads, array_flip($candidateIds)));
            $assignments[$targetId] = $operatorId;
            $loads[$operatorId]++;
        }

        return $assignments;
    }

    /**
     * Invert a target_id => operator_id map into operator_id => [target_ids],
     * so a caller can issue ONE mass UPDATE per operator instead of one per
     * target — shared by LeadAssignmentService and ImportService::bulkAssign.
     *
     * @param  array<int, int>  $assignments
     * @return array<int, array<int, int>>
     */
    public function groupByOperator(array $assignments): array
    {
        $grouped = [];
        foreach ($assignments as $targetId => $operatorId) {
            $grouped[$operatorId][] = $targetId;
        }

        return $grouped;
    }

    /**
     * Every operator appearing in at least one pool, ascending.
     *
     * @param  array<int, array<int, int>>  $candidatesByTarget
     * @return array<int, int>
     */
    private function unionOfCandidates(array $candidatesByTarget): array
    {
        $union = array_unique(array_merge(...array_values($candidatesByTarget) ?: [[]]));

        sort($union);

        return $union;
    }

    /**
     * The operator with the lowest current load; ties resolved by the
     * lowest operator id ($loads is built in ascending-id order and PHP
     * arrays preserve insertion order, so a strict `<` comparison keeps the
     * FIRST — lowest-id — operator on a tie).
     *
     * @param  array<int, int>  $loads  operatorId => load, non-empty
     */
    private function leastLoadedOperatorId(array $loads): int
    {
        $bestOperatorId = null;
        $bestLoad = null;

        foreach ($loads as $operatorId => $load) {
            if ($bestLoad === null || $load < $bestLoad) {
                $bestLoad = $load;
                $bestOperatorId = $operatorId;
            }
        }

        /** @var int $bestOperatorId */
        return $bestOperatorId;
    }
}
