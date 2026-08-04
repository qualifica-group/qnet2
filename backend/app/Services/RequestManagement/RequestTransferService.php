<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\DataObjects\RequestManagement\RequestTransferNotice;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Role;
use App\Models\User;
use App\Notifications\RequestTransferredNotification;
use App\Support\OperationalSiteLabel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Business logic for POST /api/request-management/transfer (spec 0079):
 * moves one or many requests to another Sede operativa, assigning that
 * site's own GA2 "Operatore" in the same call, tracked by two columns
 * (`is_transferred`/`transferred_from_operational_site_id`) and audited by
 * ONE explicit Activity Log entry per request.
 *
 * A SEPARATE service from RequestAssignmentService on purpose (SRP): the
 * bulk assignment documents itself as a plain reassignment, while a transfer
 * carries its own rules — origin capture, the transfer flag, a dedicated
 * notification and a distinct log description — mixing the two would make
 * both drift.
 *
 * Same two dependencies as RequestAssignmentService, minus the distributor:
 * this endpoint has no `balanced` mode (decision utente 2026-08-04, the
 * dialog only offers Sede + Operatore).
 */
final class RequestTransferService
{
    /**
     * The spatie role name notified alongside the new operator (decision
     * utente 2026-08-04) — the ROLE, never `Opportunity::supervisor()`/
     * `supervisor_id` (see spec 0079 context).
     */
    private const string SUPERVISOR_ROLE = 'supervisor';

    public function __construct(
        private readonly RequestManagementScope $scope,
        private readonly RequestOperatorWriter $operatorWriter,
    ) {}

    /**
     * @param  array<int, int>  $requestIds
     * @return int the number of requests transferred
     */
    public function transfer(array $requestIds, User $actor, int $operationalSiteId, int $operatorId): int
    {
        // Resolved once for the whole batch: the destination Sede and the new
        // operator are the SAME for every request this call touches.
        $destinationSite = OperationalSite::query()->with('addresses.city')->findOrFail($operationalSiteId);
        $newOperator = User::query()->findOrFail($operatorId);

        /** @var array<int, RequestTransferNotice> $notices */
        $notices = [];

        $transferred = DB::transaction(function () use ($requestIds, $actor, $operationalSiteId, $operatorId, &$notices): int {
            // Step 1: drop the ids the actor may not reach (D-3 scoping).
            $requests = $this->inScopeRequests($requestIds, $actor);

            if ($requests->isEmpty()) {
                return 0;
            }

            // Step 2: per request, inside the SAME transaction.
            $originLabels = $this->originSiteLabels($requests);

            foreach ($requests as $request) {
                $notices[] = $this->transferOne($request, $actor, $operationalSiteId, $operatorId, $originLabels);
            }

            return $requests->count();
        });

        // Step 3: dispatch AFTER the commit — a notification sent from inside
        // a transaction that later rolls back would be irrecoverable.
        $this->dispatchNotifications($notices, $actor, $destinationSite, $newOperator);

        return $transferred;
    }

    /**
     * The submitted requests the actor may actually write, in ascending id
     * order (same D-3 rule as RequestAssignmentService::inScopeRequests()).
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
     * The composed label of every DISTINCT origin Sede in the batch, resolved
     * in one query — never per row (N+1). A request with no current Sede
     * contributes nothing (its origin stays null, AC-003).
     *
     * @param  Collection<int, Opportunity>  $requests
     * @return array<int, string> operational_site_id => label
     */
    private function originSiteLabels(Collection $requests): array
    {
        $siteIds = $requests->pluck('operational_site_id')->filter()->unique()->values();

        if ($siteIds->isEmpty()) {
            return [];
        }

        return OperationalSite::query()
            ->with('addresses.city')
            ->whereIn('id', $siteIds)
            ->get()
            ->mapWithKeys(fn (OperationalSite $site): array => [$site->id => OperationalSiteLabel::compose($site->primaryAddress)])
            ->all();
    }

    /**
     * One request: capture its state BEFORE the write, apply the Sede plus
     * the transfer flags plus the GA2 operator, then the ONE explicit
     * Activity Log entry that is this transfer's audit trail.
     *
     * @param  array<int, string>  $originLabels
     */
    private function transferOne(Opportunity $request, User $actor, int $operationalSiteId, int $operatorId, array $originLabels): RequestTransferNotice
    {
        // Step 1: capture the origin/old state before overwriting anything.
        $originSiteId = $request->operational_site_id;
        $wasTransferred = (bool) $request->is_transferred;
        $previousOperator = $request->operatorManager();

        // Step 2: write the destination Sede plus the transfer flags. The
        // automatic model log is suspended here — `operational_site_id` IS
        // fillable and would otherwise produce its OWN automatic entry — so
        // the explicit entry in Step 4 stays the ONE record of this transfer
        // (AC-011/AC-012), never split across an automatic and an explicit row.
        $request->disableLogging();
        $request->operational_site_id = $operationalSiteId;
        $request->is_transferred = true;
        $request->transferred_from_operational_site_id = $originSiteId;
        $request->save();

        // Step 3: the GA2 "Operatore" slot (pivot) — never reaches the model
        // log either way. `operator_id` is SEEDED here rather than left to
        // apply()'s own early-return: a transfer always ASSIGNS an operator,
        // changed or not (spec 0079 IDEMPOTENZA), unlike updateWork()/
        // RequestAssignmentService, which only report a genuine transition —
        // apply() is shared with both and must keep that behaviour for them.
        // When the operator DOES change, apply() overwrites both keys with
        // the same values already seeded here, so the diff stays consistent.
        $changed = ['operational_site_id' => $operationalSiteId, 'is_transferred' => true, 'operator_id' => $operatorId];
        $old = ['operational_site_id' => $originSiteId, 'is_transferred' => $wasTransferred, 'operator_id' => $previousOperator?->id];
        $this->operatorWriter->apply($request, $operatorId, $changed, $old);

        // Step 4: the ONE explicit Activity Log entry for this transfer.
        activity($request->getTable())
            ->performedOn($request)
            ->causedBy($actor)
            ->event('updated')
            ->withProperties(['attributes' => $changed, 'old' => $old])
            ->log('Request management contact transfer');

        // Step 5: accumulate the notification content — nothing sent here.
        return new RequestTransferNotice(
            requestId: $request->id,
            contactLabel: $request->name,
            originSiteLabel: $originSiteId === null ? null : ($originLabels[$originSiteId] ?? null),
            previousOperatorName: $previousOperator?->name,
        );
    }

    /**
     * The nuovo operatore + every `supervisor`, deduplicated, excluded the
     * actor (decision utente 2026-08-04) — one send per request, so a batch
     * of N produces N notifications per recipient (documented consequence,
     * data_contract).
     *
     * @param  array<int, RequestTransferNotice>  $notices
     */
    private function dispatchNotifications(array $notices, User $actor, OperationalSite $destinationSite, User $newOperator): void
    {
        if ($notices === []) {
            return;
        }

        $recipients = $this->recipients($actor, $newOperator);

        if ($recipients->isEmpty()) {
            return;
        }

        $destinationLabel = OperationalSiteLabel::compose($destinationSite->primaryAddress);
        $transferredAt = Carbon::now();

        foreach ($notices as $notice) {
            Notification::send($recipients, new RequestTransferredNotification(
                requestId: $notice->requestId,
                contactLabel: $notice->contactLabel,
                originSiteLabel: $notice->originSiteLabel,
                destinationSiteLabel: $destinationLabel,
                previousOperatorName: $notice->previousOperatorName,
                newOperatorName: $newOperator->name,
                actorName: $actor->name,
                transferredAt: $transferredAt,
            ));
        }
    }

    /**
     * The nuovo operatore + every titolare of the `supervisor` role.
     * `User::role()` THROWS RoleDoesNotExist when the role row is absent
     * (an instance that never ran TestUsersSeeder, spec 0079 context's
     * documented caveat) — guarded here so that case degrades to "no
     * supervisors" instead of a 500 (AC-016).
     *
     * @return Collection<int, User>
     */
    private function recipients(User $actor, User $newOperator): Collection
    {
        $supervisors = Role::query()->where('name', self::SUPERVISOR_ROLE)->exists()
            ? User::role(self::SUPERVISOR_ROLE)->get()
            : new Collection;

        return $supervisors->push($newOperator)
            ->unique('id')
            ->reject(fn (User $user): bool => $user->id === $actor->id)
            ->values();
    }
}
