<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\DataObjects\RequestManagement\RequestTransferNotice;
use App\Enums\TransferRecipientRoleEnum;
use App\Models\OperationalSite;
use App\Models\Quote;
use App\Models\User;
use App\Notifications\RequestTransferredNotification;
use App\Support\OperationalSiteLabel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;

/**
 * Business logic for POST /api/request-management/transfer (spec 0079;
 * migrated onto the Quote by spec 0086, AC-034/AC-035): moves one or many
 * offers to another Sede operativa, assigning that site's own GA2
 * "Operatore"/Supervisore in the same call, tracked by two Quote columns
 * (`is_transferred`/`transferred_from_operational_site_id`, D-6 — per-offer
 * rather than per-deal from now on) and audited by ONE explicit Activity Log
 * entry per offer, anchored on the Opportunity (D-9).
 *
 * A SEPARATE service from RequestAssignmentService on purpose (SRP): the
 * bulk assignment documents itself as a plain reassignment, while a transfer
 * carries its own rules — origin capture, the transfer flag, its own set of
 * notifications and a distinct log description — mixing the two would make
 * both drift. For the same reason this path never emits the generic
 * RecordAssignmentNotification the bulk assignment does (spec 0081): a
 * transfer already tells the incoming operator they were assigned, and a
 * second, vaguer copy would be noise.
 *
 * Same dependency shape as RequestAssignmentService, minus the distributor:
 * this endpoint has no `balanced` mode (decision utente 2026-08-04, the
 * dialog only offers Sede + Operatore).
 */
final class RequestTransferService
{
    /**
     * The permission whose holders are copied on every transfer (spec 0081,
     * decisione utente 2026-08-04). It replaces the `supervisor` ROLE this
     * service used to look up, and it is never the Offerta's own
     * `supervisor_id`, which is a different concept entirely (see spec 0079
     * context).
     */
    private const string TRANSFER_NOTIFICATION_PERMISSION = 'request-management.receiveTransferNotifications';

    public function __construct(
        private readonly RequestSupervisorWriter $supervisorWriter,
    ) {}

    /**
     * @param  array<int, int>  $requestIds  Offerta (Quote) ids
     * @return int the number of offers transferred
     */
    public function transfer(array $requestIds, User $actor, int $operationalSiteId, int $operatorId): int
    {
        // Resolved once for the whole batch: the destination Sede and the new
        // operator are the SAME for every offer this call touches.
        $destinationSite = OperationalSite::query()->with('addresses.city')->findOrFail($operationalSiteId);
        $newOperator = User::query()->findOrFail($operatorId);

        /** @var array<int, RequestTransferNotice> $notices */
        $notices = [];

        $transferred = DB::transaction(function () use ($requestIds, $actor, $operationalSiteId, $operatorId, &$notices): int {
            // Step 1: drop the ids the actor may not reach (D-3 scoping).
            $quotes = $this->inScopeQuotes($requestIds, $actor);

            if ($quotes->isEmpty()) {
                return 0;
            }

            // Step 2: per offer, inside the SAME transaction.
            $originLabels = $this->originSiteLabels($quotes);

            foreach ($quotes as $quote) {
                $notices[] = $this->transferOne($quote, $actor, $operationalSiteId, $operatorId, $originLabels);
            }

            return $quotes->count();
        });

        // Step 3: dispatch AFTER the commit — a notification sent from inside
        // a transaction that later rolls back would be irrecoverable.
        $this->dispatchNotifications($notices, $actor, $destinationSite, $newOperator);

        return $transferred;
    }

    /**
     * The submitted offers the actor may actually write, in ascending id
     * order (same D-3 rule as RequestAssignmentService::inScopeQuotes()).
     * `opportunity` eager-loaded: every step below reads/logs against it
     * (D-9), never a per-row lazy load.
     *
     * @param  array<int, int>  $requestIds
     * @return Collection<int, Quote>
     */
    private function inScopeQuotes(array $requestIds, User $actor): Collection
    {
        $query = Quote::query()->with('opportunity')->whereIn('id', $requestIds)->orderBy('id');

        return RequestManagementScope::scopeToActor($query, $actor)->get();
    }

    /**
     * The composed label of every DISTINCT origin Sede in the batch, resolved
     * in one query — never per row (N+1). An offer with no current Sede
     * contributes nothing (its origin stays null, AC-003).
     *
     * @param  Collection<int, Quote>  $quotes
     * @return array<int, string> operational_site_id => label
     */
    private function originSiteLabels(Collection $quotes): array
    {
        $siteIds = $quotes->pluck('operational_site_id')->filter()->unique()->values();

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
     * One offer: capture its state BEFORE the write, apply the Sede plus the
     * transfer flags plus the Supervisore, then the ONE explicit Activity Log
     * entry that is this transfer's audit trail — anchored on the
     * Opportunity (D-9).
     *
     * @param  array<int, string>  $originLabels
     */
    private function transferOne(Quote $quote, User $actor, int $operationalSiteId, int $operatorId, array $originLabels): RequestTransferNotice
    {
        // Step 1: capture the origin/old state before overwriting anything.
        $originSiteId = $quote->operational_site_id;
        $wasTransferred = (bool) $quote->is_transferred;
        $previousSupervisor = $quote->supervisor;

        // Step 2: write the destination Sede plus the transfer flags. The
        // automatic model log is suspended here (instance-scoped) —
        // `operational_site_id`/`is_transferred` ARE fillable/persisted and
        // would otherwise produce their OWN automatic entry on the Quote's
        // own activity trail — so the explicit entry in Step 4, anchored on
        // the Opportunity (D-9), stays the ONE record of this transfer
        // (AC-011/AC-012), never split across an automatic and an explicit
        // row.
        $quote->disableLogging();
        $quote->operational_site_id = $operationalSiteId;
        $quote->is_transferred = true;
        $quote->transferred_from_operational_site_id = $originSiteId;
        $quote->save();

        // Step 3: the Supervisore (Quote column + GA2 pivot sync) — never
        // reaches the Opportunity's automatic model log either way.
        // `operator_id` is SEEDED here rather than left to apply()'s own
        // early-return: a transfer always ASSIGNS an operator, changed or
        // not (spec 0079 IDEMPOTENZA), unlike updateWork()/
        // RequestAssignmentService, which only report a genuine transition —
        // apply() is shared with both and must keep that behaviour for them.
        // When the operator DOES change, apply() overwrites both keys with
        // the same values already seeded here, so the diff stays consistent.
        $changed = ['operational_site_id' => $operationalSiteId, 'is_transferred' => true, 'operator_id' => $operatorId];
        $old = ['operational_site_id' => $originSiteId, 'is_transferred' => $wasTransferred, 'operator_id' => $previousSupervisor?->id];
        $this->supervisorWriter->apply($quote, $operatorId, $changed, $old);

        // Step 4: the ONE explicit Activity Log entry for this transfer,
        // anchored on the Opportunity (D-9).
        activity($quote->opportunity->getTable())
            ->performedOn($quote->opportunity)
            ->causedBy($actor)
            ->event('updated')
            ->withProperties(['attributes' => $changed, 'old' => $old])
            ->log('Request management contact transfer');

        // Step 5: accumulate the notification content — nothing sent here.
        // `requestId` is the Offerta id (D-2): the panel it deep-links to
        // opens by Quote id.
        return new RequestTransferNotice(
            requestId: $quote->id,
            contactLabel: $quote->opportunity->name,
            originSiteLabel: $originSiteId === null ? null : ($originLabels[$originSiteId] ?? null),
            previousOperatorName: $previousSupervisor?->name,
            previousOperatorId: $previousSupervisor?->id,
            // Spec 0086, MT-04b: the deep link's `/opportunities/:id` branch
            // needs this DISTINCT id — `requestId` now names the Quote.
            opportunityId: $quote->opportunity_id,
        );
    }

    /**
     * Three DISJOINT audiences per offer (spec 0081): whoever lost the
     * contact, whoever gained it, and whoever supervises the module — each
     * with its own text. One send per offer, so a batch of N produces N
     * notifications per recipient (documented consequence, data_contract).
     *
     * @param  array<int, RequestTransferNotice>  $notices
     */
    private function dispatchNotifications(array $notices, User $actor, OperationalSite $destinationSite, User $newOperator): void
    {
        if ($notices === []) {
            return;
        }

        $destinationLabel = OperationalSiteLabel::compose($destinationSite->primaryAddress);
        $transferredAt = Carbon::now();
        $previousOperators = $this->previousOperators($notices);
        $supervisors = $this->supervisors($actor, $newOperator, $previousOperators);

        foreach ($notices as $notice) {
            $build = fn (TransferRecipientRoleEnum $role): RequestTransferredNotification => new RequestTransferredNotification(
                requestId: $notice->requestId,
                contactLabel: $notice->contactLabel,
                originSiteLabel: $notice->originSiteLabel,
                destinationSiteLabel: $destinationLabel,
                previousOperatorName: $notice->previousOperatorName,
                newOperatorName: $newOperator->name,
                actorName: $actor->name,
                transferredAt: $transferredAt,
                recipientRole: $role,
                // Spec 0086, MT-04b: the deep link's `/opportunities/:id`
                // branch needs this DISTINCT id — `requestId` names the Quote.
                opportunityId: $notice->opportunityId,
            );

            // Step 1: the outgoing operator — never the actor, and never the
            // incoming operator (a "transfer" onto the same person is not a
            // loss, and IDEMPOTENZA lets that call through).
            $previousOperator = $previousOperators->get($notice->previousOperatorId);

            if ($previousOperator !== null && $previousOperator->id !== $actor->id && $previousOperator->id !== $newOperator->id) {
                $previousOperator->notify($build(TransferRecipientRoleEnum::PreviousOperator));
            }

            // Step 2: the incoming operator.
            if ($newOperator->id !== $actor->id) {
                $newOperator->notify($build(TransferRecipientRoleEnum::NewOperator));
            }

            // Step 3: the supervisory copy, to nobody already served above.
            if ($supervisors->isNotEmpty()) {
                Notification::send($supervisors, $build(TransferRecipientRoleEnum::Supervisor));
            }
        }
    }

    /**
     * Every DISTINCT outgoing operator of the batch, in one query, keyed by
     * id — never one query per request (N+1).
     *
     * @param  array<int, RequestTransferNotice>  $notices
     * @return Collection<int, User>
     */
    private function previousOperators(array $notices): Collection
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn (RequestTransferNotice $notice): ?int => $notice->previousOperatorId, $notices),
        )));

        if ($ids === []) {
            return new Collection;
        }

        return User::query()->whereIn('id', $ids)->get()->keyBy('id');
    }

    /**
     * Everyone holding `request-management.receiveTransferNotifications`
     * (spec 0081, decisione utente 2026-08-04) — a PERMISSION, not the
     * `supervisor` role this service used to hardcode: the grant survives
     * roles being renamed or split, and it can be revoked per role from the
     * roles screen.
     *
     * The actor, the incoming operator and every outgoing operator are
     * removed: they each already receive their own, more specific text, and
     * nobody is notified twice for one transfer.
     *
     * `User::permission()` THROWS PermissionDoesNotExist when the row is
     * absent (an instance that never ran `permissions:sync`) — guarded here
     * so that case degrades to "no supervisory copy" instead of a 500, the
     * same defence the role lookup this method replaces used to carry.
     *
     * @param  Collection<int, User>  $previousOperators
     * @return Collection<int, User>
     */
    private function supervisors(User $actor, User $newOperator, Collection $previousOperators): Collection
    {
        if (! Permission::query()->where('name', self::TRANSFER_NOTIFICATION_PERMISSION)->exists()) {
            return new Collection;
        }

        $excludedIds = [$actor->id, $newOperator->id, ...$previousOperators->modelKeys()];

        return User::permission(self::TRANSFER_NOTIFICATION_PERMISSION)
            ->get()
            ->reject(fn (User $user): bool => in_array($user->id, $excludedIds, true))
            ->values();
    }
}
