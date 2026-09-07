<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\Enums\LeadAssignmentMode;
use App\Models\Quote;
use App\Models\User;
use App\Services\LeadOperatorDistributor;
use App\Services\Notifications\AssignmentNotifier;
use App\Support\ManagerPositions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the module's two BULK attribution endpoints — POST
 * /api/request-management/assign-operators (user directive 2026-07-23, "come
 * nei lead"; migrated onto the Quote by spec 0086), which assigns a Sede
 * operativa and the GA2 "Operatore" to many offers at once, and POST
 * /api/request-management/assign-manager-ga3 (spec 0104), which moves the GA3
 * slot alone with no Sede in play. Distinct from RequestManagementService
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
 */
final class RequestAssignmentService
{
    public function __construct(
        private readonly RequestOperatorWriter $operatorWriter,
        private readonly LeadOperatorDistributor $distributor,
        private readonly AssignmentNotifier $assignmentNotifier,
    ) {}

    /**
     * Every reachable offer always receives $operationalSiteId. In `single`
     * mode each also receives $operatorId; in `balanced` mode operators are
     * distributed across the Sede's own operators (422 when it has none).
     * Whole operation is one transaction.
     *
     * @param  array<int, int>  $requestIds  Offerta (Quote) ids
     * @return int the number of offers assigned
     */
    public function assignOperators(array $requestIds, User $actor, int $operationalSiteId, LeadAssignmentMode $mode, ?int $operatorId): int
    {
        return DB::transaction(function () use ($requestIds, $actor, $operationalSiteId, $mode, $operatorId): int {
            // Step 1: drop the ids the actor may not reach (D-3 scoping).
            $quotes = $this->inScopeQuotes($requestIds, $actor);

            if ($quotes->isEmpty()) {
                return 0;
            }

            // Step 2: resolve the operator of each offer, per mode.
            $operatorPerQuote = $mode === LeadAssignmentMode::Single
                ? array_fill_keys($quotes->modelKeys(), $operatorId)
                : $this->distributeBalanced($quotes->modelKeys(), $operationalSiteId);

            // Step 3: write the Sede (a fillable column, explicitly audited —
            // D-9 anchors the module's history on the Opportunity, so the
            // Quote's own automatic log is suspended) and the GA2 Operatore
            // (Quote column + `quote_user` pivot slot sync, spec 0087 D-9)
            // on each offer.
            foreach ($quotes as $quote) {
                $this->assignOne($quote, $actor, $operationalSiteId, $operatorPerQuote[$quote->id] ?? null);
            }

            return $quotes->count();
        });
    }

    /**
     * Bulk GA3 assignment (spec 0104): every reachable offer gets $userId in
     * the GA3 slot, or has that slot CLEARED when $userId is null (D-2).
     * Whole operation is one transaction.
     *
     * Three things assignOperators() does that this deliberately does not,
     * each following what the per-cell GA3 editor already does: it writes no
     * Sede (only the Operatore slot is bound to one, D-1), it sends no
     * assignment notification (GA3 scopes no visibility and assigns nobody,
     * D-5), and it needs no distributor (there is no site-scoped pool to
     * balance across, so the action has a single mode).
     *
     * @param  array<int, int>  $requestIds  Offerta (Quote) ids
     * @return int the number of offers reached (in scope), the same count
     *             assignOperators() reports
     */
    public function assignManagerGa3(array $requestIds, User $actor, ?int $userId): int
    {
        return DB::transaction(function () use ($requestIds, $actor, $userId): int {
            // Step 1: drop the ids the actor may not reach (D-3 scoping).
            $quotes = $this->inScopeQuotes($requestIds, $actor);

            // Step 2: move the GA3 slot alone on each of them.
            foreach ($quotes as $quote) {
                $this->assignManagerGa3ToOne($quote, $actor, $userId);
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
     * br-balanced over the Sede's operators, weighted by the offers each
     * already operates.
     *
     * @param  array<int, int>  $quoteIds  ordered ascending
     * @return array<int, int> quoteId => operatorId
     */
    private function distributeBalanced(array $quoteIds, int $operationalSiteId): array
    {
        $operatorIds = $this->distributor->operatorIdsForSite($operationalSiteId);

        if ($operatorIds === []) {
            abort(422, 'The selected Sede has no operators to distribute requests to.');
        }

        return $this->distributor->distribute($operatorIds, $this->currentLoads($operatorIds), $quoteIds);
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
        return DB::table('quotes')
            ->whereIn('operator_id', $operatorIds)
            ->selectRaw('operator_id, COUNT(*) as aggregate')
            ->groupBy('operator_id')
            ->pluck('aggregate', 'operator_id')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * One offer: the Sede column then the GA2 Operatore (Quote column +
     * `quote_user` pivot slot sync), with the same explicit activity entry the work panel
     * writes, anchored on the Opportunity (D-9).
     */
    private function assignOne(Quote $quote, User $actor, int $operationalSiteId, ?int $operatorId): void
    {
        $changed = [];
        $old = [];
        $previousSiteId = $quote->operational_site_id;

        if ($previousSiteId !== $operationalSiteId) {
            $old['operational_site_id'] = $previousSiteId;
            $changed['operational_site_id'] = $operationalSiteId;
        }

        $quote->disableLogging();
        $quote->operational_site_id = $operationalSiteId;
        $quote->save();

        $this->operatorWriter->apply($quote, $operatorId, $changed, $old);

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
     * One offer's GA3 slot, through the SAME writer the grid cell uses, so
     * the bulk channel can never grow a rule the per-row channel lacks. An
     * offer already carrying $userId in that slot writes nothing at all
     * (applyGa3's own no-op guard) and therefore logs nothing.
     *
     * The activity entry is anchored on the Opportunity, like assignOne()'s:
     * D-9 keeps the module's whole history there, and the pivot slot is not
     * a fillable attribute, so no automatic model-event log would ever see
     * this write.
     */
    private function assignManagerGa3ToOne(Quote $quote, User $actor, ?int $userId): void
    {
        $changed = [];
        $old = [];

        $this->operatorWriter->applyGa3($quote, $userId, $changed, $old);

        if ($changed === []) {
            return;
        }

        activity($quote->opportunity->getTable())
            ->performedOn($quote->opportunity)
            ->causedBy($actor)
            ->event('updated')
            ->withProperties(['attributes' => $changed, 'old' => $old])
            ->log('Request management bulk GA3 assignment');
    }
}
