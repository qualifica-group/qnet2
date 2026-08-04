<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\Enums\LeadAssignmentMode;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\LeadOperatorDistributor;
use App\Services\Notifications\AssignmentNotifier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for POST /api/request-management/assign-operators (user
 * directive 2026-07-23, "come nei lead"): bulk-assign a Sede operativa and
 * the GA2 "Operatore" to many requests at once. Distinct from
 * RequestManagementService (the per-record work panel): this is a bulk,
 * cross-record write, kept in its own Service (SRP).
 *
 * Two rules are NOT inherited from the leads flow:
 *  - SCOPE (D-3): a request the actor neither manages nor may see through
 *    `request-management.viewAll` is silently skipped, never written. The
 *    endpoint takes ids from a client and the module's whole contract is that
 *    an out-of-scope row does not exist for that actor.
 *  - The "current load" of `mode=balanced` counts REQUESTS the operator
 *    already holds as GA2, not leads: weighting by another module's workload
 *    would distribute against the wrong signal. Only the distribution
 *    algorithm itself (br-balanced) is reused, from LeadOperatorDistributor.
 */
final class RequestAssignmentService
{
    public function __construct(
        private readonly RequestManagementScope $scope,
        private readonly RequestOperatorWriter $operatorWriter,
        private readonly LeadOperatorDistributor $distributor,
        private readonly AssignmentNotifier $assignmentNotifier,
    ) {}

    /**
     * Every reachable request always receives $operationalSiteId. In `single`
     * mode each also receives $operatorId; in `balanced` mode operators are
     * distributed across the Sede's own operators (422 when it has none).
     * Whole operation is one transaction.
     *
     * @param  array<int, int>  $requestIds
     * @return int the number of requests assigned
     */
    public function assignOperators(array $requestIds, User $actor, int $operationalSiteId, LeadAssignmentMode $mode, ?int $operatorId): int
    {
        return DB::transaction(function () use ($requestIds, $actor, $operationalSiteId, $mode, $operatorId): int {
            // Step 1: drop the ids the actor may not reach (D-3 scoping).
            $requests = $this->inScopeRequests($requestIds, $actor);

            if ($requests->isEmpty()) {
                return 0;
            }

            // Step 2: resolve the operator of each request, per mode.
            $operatorPerRequest = $mode === LeadAssignmentMode::Single
                ? array_fill_keys($requests->modelKeys(), $operatorId)
                : $this->distributeBalanced($requests->modelKeys(), $operationalSiteId);

            // Step 3: write the Sede (a fillable column, so the automatic
            // model log picks it up) and the GA2 slot (a pivot, explicitly
            // audited) on each request.
            foreach ($requests as $request) {
                $this->assignOne($request, $actor, $operationalSiteId, $operatorPerRequest[$request->id] ?? null);
            }

            return $requests->count();
        });
    }

    /**
     * The submitted requests the actor may actually write, in ascending id
     * order (br-balanced step 3 requires a deterministic order).
     *
     * @param  array<int, int>  $requestIds
     * @return Collection<int, Opportunity>
     */
    private function inScopeRequests(array $requestIds, User $actor): Collection
    {
        /** @var Collection<int, Opportunity> $requests */
        $requests = Opportunity::query()
            ->whereIn('id', $requestIds)
            ->orderBy('id')
            ->get();

        if ($actor->can('request-management.viewAll')) {
            return $requests;
        }

        return $requests->filter(fn (Opportunity $request): bool => $this->scope->isOperatorOf($actor, $request))->values();
    }

    /**
     * br-balanced over the Sede's operators, weighted by the requests each
     * already holds as GA2.
     *
     * @param  array<int, int>  $requestIds  ordered ascending
     * @return array<int, int> requestId => operatorId
     */
    private function distributeBalanced(array $requestIds, int $operationalSiteId): array
    {
        $operatorIds = $this->distributor->operatorIdsForSite($operationalSiteId);

        if ($operatorIds === []) {
            abort(422, 'The selected Sede has no operators to distribute requests to.');
        }

        return $this->distributor->distribute($operatorIds, $this->currentLoads($operatorIds), $requestIds);
    }

    /**
     * Requests already held as GA2 per operator. An operator with none is
     * simply absent from the map (LeadOperatorDistributor defaults it to 0).
     *
     * @param  array<int, int>  $operatorIds
     * @return array<int, int> operatorId => load
     */
    private function currentLoads(array $operatorIds): array
    {
        return DB::table('opportunity_user')
            ->whereIn('user_id', $operatorIds)
            ->where('position', Opportunity::OPERATOR_MANAGER_POSITION)
            ->selectRaw('user_id, COUNT(*) as aggregate')
            ->groupBy('user_id')
            ->pluck('aggregate', 'user_id')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * One request: the Sede column then the operator pivot, with the same
     * explicit activity entry the work panel writes for the operator (the
     * pivot never reaches the automatic model log).
     */
    private function assignOne(Opportunity $request, User $actor, int $operationalSiteId, ?int $operatorId): void
    {
        $request->operational_site_id = $operationalSiteId;
        $request->save();

        $changed = [];
        $old = [];
        $this->operatorWriter->apply($request, $operatorId, $changed, $old);

        if ($changed === []) {
            return;
        }

        // spec 0081: one assignment notification per request, exactly as the
        // per-record work panel emits — a batch of N produces N.
        if (($changed['operator_id'] ?? null) !== null) {
            $this->assignmentNotifier->notify(
                $request,
                $actor,
                null,
                [$changed['operator_id'] => Opportunity::OPERATOR_MANAGER_POSITION],
            );
        }

        activity($request->getTable())
            ->performedOn($request)
            ->causedBy($actor)
            ->event('updated')
            ->withProperties(['attributes' => $changed, 'old' => $old])
            ->log('Request management bulk assignment');
    }
}
