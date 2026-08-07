<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\Enums\LeadAssignmentMode;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use App\Services\LeadOperatorDistributor;
use App\Services\Notifications\AssignmentNotifier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for POST /api/request-management/assign-operators (user
 * directive 2026-07-23, "come nei lead"; migrated onto the Quote by spec
 * 0086): bulk-assign a Sede operativa and the GA2 "Operatore"/Supervisore to
 * many offers at once. Distinct from RequestManagementService (the
 * per-record work panel): this is a bulk, cross-record write, kept in its
 * own Service (SRP).
 *
 * Two rules are NOT inherited from the leads flow:
 *  - SCOPE (D-3): an offer the actor neither supervises nor may see through
 *    `request-management.viewAll` is silently skipped, never written. The
 *    endpoint takes ids from a client and the module's whole contract is that
 *    an out-of-scope row does not exist for that actor.
 *  - The "current load" of `mode=balanced` counts OFFERS the operator
 *    already supervises (`quotes.supervisor_id`, AC-033), not leads and not
 *    opportunities-via-pivot: weighting by another module's workload would
 *    distribute against the wrong signal. Only the distribution algorithm
 *    itself (br-balanced) is reused, from LeadOperatorDistributor.
 */
final class RequestAssignmentService
{
    public function __construct(
        private readonly RequestSupervisorWriter $supervisorWriter,
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
            // Quote's own automatic log is suspended) and the Supervisore
            // (Quote column + GA2 pivot sync) on each offer.
            foreach ($quotes as $quote) {
                $this->assignOne($quote, $actor, $operationalSiteId, $operatorPerQuote[$quote->id] ?? null);
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
        // `opportunity` eager-loaded: assignOne() notifies/logs against it
        // for every offer in the batch (D-9), never a per-row lazy load.
        $query = Quote::query()->with('opportunity')->whereIn('id', $requestIds)->orderBy('id');

        return RequestManagementScope::scopeToActor($query, $actor)->get();
    }

    /**
     * br-balanced over the Sede's operators, weighted by the offers each
     * already supervises.
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
     * Offers already supervised per operator (AC-033: counts `quotes`, not
     * the opportunity_user pivot). An operator with none is simply absent
     * from the map (LeadOperatorDistributor defaults it to 0).
     *
     * @param  array<int, int>  $operatorIds
     * @return array<int, int> operatorId => load
     */
    private function currentLoads(array $operatorIds): array
    {
        return DB::table('quotes')
            ->whereIn('supervisor_id', $operatorIds)
            ->selectRaw('supervisor_id, COUNT(*) as aggregate')
            ->groupBy('supervisor_id')
            ->pluck('aggregate', 'supervisor_id')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * One offer: the Sede column then the Supervisore (Quote column + GA2
     * pivot sync), with the same explicit activity entry the work panel
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

        $this->supervisorWriter->apply($quote, $operatorId, $changed, $old);

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
                [$changed['operator_id'] => Opportunity::OPERATOR_MANAGER_POSITION],
                // Spec 0086, MT-04b: the deep link's request-management
                // branch must open THIS Offerta, not the Opportunity.
                requestManagementRecordId: $quote->id,
            );
        }

        activity($quote->opportunity->getTable())
            ->performedOn($quote->opportunity)
            ->causedBy($actor)
            ->event('updated')
            ->withProperties(['attributes' => $changed, 'old' => $old])
            ->log('Request management bulk assignment');
    }
}
