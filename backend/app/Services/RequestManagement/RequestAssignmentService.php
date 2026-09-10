<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\DataObjects\Assignment\AssignmentOutcome;
use App\Enums\LeadAssignmentMode;
use App\Models\Quote;
use App\Models\User;
use App\Services\Assignment\AssignmentCandidates;
use App\Services\Assignment\AssignmentSiteResolver;
use App\Services\Assignment\QuoteCompetence;
use App\Services\LeadOperatorDistributor;
use App\Services\Notifications\AssignmentNotifier;
use App\Support\ManagerPositions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the module's two BULK attribution endpoints — POST
 * /api/request-management/assign-operators (user directive 2026-07-23, "come
 * nei lead"; migrated onto the Quote by spec 0086), which assigns the GA2
 * "Operatore" of many offers at once, and POST
 * /api/request-management/assign-manager-ga1 (spec 0104), which moves the GA1
 * slot alone. Distinct from RequestManagementService
 * (the per-record work panel): both are bulk, cross-record writes, kept in
 * their own Service (SRP), and both reach the pivot through the SAME
 * RequestOperatorWriter the per-row channels use.
 *
 * Two rules are NOT inherited from the leads flow:
 *  - SCOPE (spec 0087, D-9): an offer the actor neither operates nor may see
 *    through `request-management.viewAll` is silently skipped, never
 *    written. The endpoint takes ids from a client and the module's whole
 *    contract is that an out-of-scope row does not exist for that actor.
 *  - The "current load" of `mode=balanced` counts OFFERS the operator
 *    already operates (`quotes.operator_id`, AC-033), not leads and not
 *    opportunities-via-pivot: weighting by another module's workload would
 *    distribute against the wrong signal. Only the distribution algorithm
 *    itself (br-balanced) is reused, from LeadOperatorDistributor.
 *
 * A third divergence since spec 0113 (D-4): this action writes NO Sede. An
 * offer's Sede IS `quotes.operational_site_id`, so it is READ to scope each
 * offer's candidates and never rewritten — unlike the other two surfaces,
 * which persist the Sede they resolve from the campaign.
 */
final class RequestAssignmentService
{
    public function __construct(
        private readonly RequestOperatorWriter $operatorWriter,
        private readonly LeadOperatorDistributor $distributor,
        private readonly AssignmentNotifier $assignmentNotifier,
        private readonly QuoteCompetence $quoteCompetence,
        private readonly AssignmentSiteResolver $siteResolver,
        private readonly AssignmentCandidates $candidates,
    ) {}

    /**
     * In `single` mode every reachable offer receives $operatorId; in
     * `balanced` mode each is distributed among the operators of its OWN
     * Sede competent for its own product lines, and an offer with no such
     * candidate keeps the operator it has and is counted as `skipped` (spec
     * 0113, AC-019: a per-record condition, no longer a 422 on the batch).
     * Whole operation is one transaction.
     *
     * @param  array<int, int>  $requestIds  Offerta (Quote) ids
     */
    public function assignOperators(array $requestIds, User $actor, LeadAssignmentMode $mode, ?int $operatorId): AssignmentOutcome
    {
        return DB::transaction(function () use ($requestIds, $actor, $mode, $operatorId): AssignmentOutcome {
            // Step 1: drop the ids the actor may not reach (D-3 scoping).
            $quotes = $this->inScopeQuotes($requestIds, $actor);

            if ($quotes->isEmpty()) {
                return new AssignmentOutcome(assigned: 0);
            }

            // Step 2: resolve the operator of each offer, per mode. An offer
            // ABSENT from the map is one `balanced` found no candidate for —
            // its Sede or its competence left the pool empty (spec 0110
            // AC-021, spec 0113 AC-019): it keeps the operator it already
            // had, which a null would instead have cleared.
            $operatorPerQuote = $mode === LeadAssignmentMode::Single
                ? array_fill_keys($quotes->modelKeys(), $operatorId)
                : $this->distributeBalanced($quotes->modelKeys());

            // Step 3: write the GA2 Operatore (Quote column + `quote_user`
            // pivot slot sync, spec 0087 D-9) on each offer. Nothing else is
            // written: the Sede was read, not chosen (spec 0113, D-4).
            foreach ($quotes as $quote) {
                $this->assignOne(
                    $quote,
                    $actor,
                    $operatorPerQuote[$quote->id] ?? null,
                    array_key_exists($quote->id, $operatorPerQuote),
                );
            }

            return new AssignmentOutcome(
                assigned: count($operatorPerQuote),
                skipped: $quotes->count() - count($operatorPerQuote),
            );
        });
    }

    /**
     * Bulk GA1 assignment (spec 0104): every reachable offer gets $userId in
     * the GA1 slot, or has that slot CLEARED when $userId is null (D-2).
     * Whole operation is one transaction.
     *
     * Two things assignOperators() does that this deliberately does not, each
     * following what the per-cell GA1 editor already does: it sends no
     * assignment notification (GA1 scopes no visibility and assigns nobody,
     * D-5), and it needs no distributor (only the Operatore slot is bound to
     * a Sede, D-1, so there is no site-scoped pool to balance across and the
     * action has a single mode).
     *
     * @param  array<int, int>  $requestIds  Offerta (Quote) ids
     * @return int the number of offers reached (in scope)
     */
    public function assignManagerGa1(array $requestIds, User $actor, ?int $userId): int
    {
        return DB::transaction(function () use ($requestIds, $actor, $userId): int {
            // Step 1: drop the ids the actor may not reach (D-3 scoping).
            $quotes = $this->inScopeQuotes($requestIds, $actor);

            // Step 2: move the GA1 slot alone on each of them.
            foreach ($quotes as $quote) {
                $this->assignManagerGa1ToOne($quote, $actor, $userId);
            }

            return $quotes->count();
        });
    }

    /**
     * The submitted offers the actor may actually write, in ascending id
     * order (br-balanced step 3 requires a deterministic order).
     *
     * @param  array<int, int>  $requestIds
     * @return Collection<int, Quote>
     */
    private function inScopeQuotes(array $requestIds, User $actor): Collection
    {
        // `opportunity` eager-loaded: both per-offer writers log (and
        // assignOne() notifies) against it for every offer in the batch
        // (D-9), never a per-row lazy load.
        $query = Quote::query()->with('opportunity')->whereIn('id', $requestIds)->orderBy('id');

        return RequestManagementScope::scopeToActor($query, $actor)->get();
    }

    /**
     * br-balanced over each offer's OWN candidates (spec 0113, AC-019): the
     * operators of its `quotes.operational_site_id` (D-4) narrowed by
     * competence for its own opportunity's product lines (spec 0110, INV-1)
     * — the one composition AssignmentCandidates owns for all three
     * surfaces. Weighted by the offers each operator already operates.
     *
     * An offer nobody covers — no Sede, a Sede with no operators, or no
     * competent operator among them — is absent from the returned map: the
     * caller keeps its current operator and reports it as `skipped`, which
     * since 0113 replaces the batch-wide 422 the empty Sede used to raise.
     *
     * @param  array<int, int>  $quoteIds  ordered ascending
     * @return array<int, int> quoteId => operatorId
     */
    private function distributeBalanced(array $quoteIds): array
    {
        $candidatesByQuote = $this->candidates->byRecord(
            $this->siteResolver->forQuotes($quoteIds),
            $this->quoteCompetence->requiredByQuote($quoteIds),
        );

        return $this->distributor->distributeAmong(
            $candidatesByQuote,
            $this->currentLoads($this->involvedOperatorIds($candidatesByQuote)),
        );
    }

    /**
     * The union of every offer's candidates: the loads must be counted (and
     * shared) across ALL the Sedi the batch touches, or two disjoint pools
     * would each rebalance in isolation.
     *
     * @param  array<int, array<int, int>>  $candidatesByQuote
     * @return array<int, int>
     */
    private function involvedOperatorIds(array $candidatesByQuote): array
    {
        return array_values(array_unique(array_merge([], ...array_values($candidatesByQuote))));
    }

    /**
     * Offers already operated per operator (AC-033: counts `quotes`, not
     * the `quote_user` pivot). An operator with none is simply absent from
     * the map (LeadOperatorDistributor defaults it to 0).
     *
     * @param  array<int, int>  $operatorIds
     * @return array<int, int> operatorId => load
     */
    private function currentLoads(array $operatorIds): array
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

    /**
     * One offer: the GA2 Operatore alone (Quote column + `quote_user` pivot
     * slot sync), with the same explicit activity entry the work panel
     * writes, anchored on the Opportunity (D-9). Since spec 0113 nothing
     * else is written — the Sede is the offer's own column, read upstream to
     * scope its candidates, so there is no Sede move to persist or to audit.
     *
     * `$writeOperator` false leaves the Operatore slot entirely alone (spec
     * 0110): the offer was skipped for lack of a candidate, which must not be
     * confused with an explicit "clear the operator". With the Sede gone the
     * offer is then left completely untouched, and `$changed` empty means no
     * notification and no log — the "nothing changed" guard now rests on the
     * operator alone.
     */
    private function assignOne(Quote $quote, User $actor, ?int $operatorId, bool $writeOperator = true): void
    {
        $changed = [];
        $old = [];

        if ($writeOperator) {
            $this->operatorWriter->apply($quote, $operatorId, $changed, $old);
        }

        if ($changed === []) {
            return;
        }

        // spec 0081: one assignment notification per offer, exactly as the
        // per-record work panel emits — a batch of N produces N. Notified
        // against the Opportunity: the notification detail card only
        // understands Registry/Opportunity records (D-2).
        if (($changed['operator_id'] ?? null) !== null) {
            $this->assignmentNotifier->notify(
                $quote->opportunity,
                $actor,
                null,
                [$changed['operator_id'] => ManagerPositions::OPERATOR],
                // Spec 0086, MT-04b: the deep link's request-management
                // branch must open THIS Offerta, not the Opportunity.
                requestManagementRecordId: $quote->id,
                // Spec 0087, D-10: the detail card's "operator" field reads
                // THIS Offerta's own GA2, not the parent Opportunity's.
                requestManagementQuote: $quote,
            );
        }

        activity($quote->opportunity->getTable())
            ->performedOn($quote->opportunity)
            ->causedBy($actor)
            ->event('updated')
            ->withProperties(['attributes' => $changed, 'old' => $old])
            ->log('Request management bulk assignment');
    }

    /**
     * One offer's GA1 slot, through the SAME writer the grid cell uses, so
     * the bulk channel can never grow a rule the per-row channel lacks. An
     * offer already carrying $userId in that slot writes nothing at all
     * (applyGa1's own no-op guard) and therefore logs nothing.
     *
     * The activity entry is anchored on the Opportunity, like assignOne()'s:
     * D-9 keeps the module's whole history there, and the pivot slot is not
     * a fillable attribute, so no automatic model-event log would ever see
     * this write.
     */
    private function assignManagerGa1ToOne(Quote $quote, User $actor, ?int $userId): void
    {
        $changed = [];
        $old = [];

        $this->operatorWriter->applyGa1($quote, $userId, $changed, $old);

        if ($changed === []) {
            return;
        }

        activity($quote->opportunity->getTable())
            ->performedOn($quote->opportunity)
            ->causedBy($actor)
            ->event('updated')
            ->withProperties(['attributes' => $changed, 'old' => $old])
            ->log('Request management bulk GA1 assignment');
    }
}
