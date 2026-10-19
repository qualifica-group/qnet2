<?php

declare(strict_types=1);

namespace App\Services\Absences\Requests;

use App\Enums\AbsenceRequestStatus;
use App\Models\AbsenceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Decision and cancellation of a submitted absence request (spec 0278, D-2,
 * D-9). Every transition locks the row, checks the actor (403) before the
 * state (409), and logs the status change through the model's activity log.
 */
final class AbsenceRequestTransitions
{
    public function __construct(
        private readonly AbsenceRequestVisibility $visibility,
        private readonly AbsenceRequestCapabilities $capabilities,
        private readonly AbsenceRequestService $service,
        private readonly AbsenceRequestNotifier $notifier,
    ) {}

    public function approve(AbsenceRequest $request, User $actor): AbsenceRequest
    {
        return $this->decide($request, $actor, AbsenceRequestStatus::Approved, null);
    }

    public function reject(AbsenceRequest $request, User $actor, string $reason): AbsenceRequest
    {
        return $this->decide($request, $actor, AbsenceRequestStatus::Rejected, $reason);
    }

    public function cancel(AbsenceRequest $request, User $actor): AbsenceRequest
    {
        return DB::transaction(function () use ($request, $actor): AbsenceRequest {
            // Step 1: lock, then the person or the HR office only (403)
            $request = $this->locked($request);
            abort_unless($this->visibility->isOwnerOrManager($actor, $request), 403, 'This action is unauthorized.');

            // Step 2: only a submitted or approved request can be cancelled (409)
            abort_unless($this->capabilities->isCancellableStatus($request), 409, __('absence_requests.not_cancellable'));

            // Step 3: the person cannot cancel an approved request already started (403)
            abort_unless($this->capabilities->canCancel($actor, $request), 403, __('absence_requests.already_started'));

            // Step 4: cancel
            $previousStatus = $request->status;
            $request->update([
                'status' => AbsenceRequestStatus::Cancelled,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
            ]);

            // Step 5: tell who needs to know, once the transaction commits
            $detail = $this->service->detail($request);
            $this->notifier->cancelled($detail, $actor, $previousStatus);

            return $detail;
        });
    }

    private function decide(AbsenceRequest $request, User $actor, AbsenceRequestStatus $to, ?string $reason): AbsenceRequest
    {
        return DB::transaction(function () use ($request, $actor, $to, $reason): AbsenceRequest {
            // Step 1: lock, then only an approver snapshotted at submission decides (403)
            $request = $this->locked($request);
            abort_unless($this->visibility->isApprover($actor, $request), 403, 'This action is unauthorized.');

            // Step 2: only a submitted request can be decided (409)
            abort_unless($request->status === AbsenceRequestStatus::Submitted, 409, __('absence_requests.not_submitted'));

            // Step 3: record the decision
            $request->update([
                'status' => $to,
                'rejection_reason' => $reason,
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ]);

            // Step 4: tell the person, once the transaction commits
            $detail = $this->service->detail($request);
            $to === AbsenceRequestStatus::Approved ? $this->notifier->approved($detail, $actor) : $this->notifier->rejected($detail, $actor);

            return $detail;
        });
    }

    private function locked(AbsenceRequest $request): AbsenceRequest
    {
        return AbsenceRequest::query()->with('approvers:id')->lockForUpdate()->findOrFail($request->id);
    }
}
