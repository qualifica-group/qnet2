<?php

namespace App\Tables\AbsenceRequests;

use App\Enums\AbsenceRequestStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The "Da approvare" grid (domain `absence-requests-to-approve`, spec 0278):
 * submitted requests in which the actor is among the snapshotted approvers.
 */
class AbsenceRequestsToApproveTableDefinition extends AbstractAbsenceRequestsTableDefinition
{
    public function domain(): string
    {
        return 'absence-requests-to-approve';
    }

    protected function applyScope(Builder $query, User $actor): void
    {
        $query->where('absence_requests.status', AbsenceRequestStatus::Submitted->value)
            ->whereHas('approvers', static fn (Builder $approvers) => $approvers->whereKey($actor->id));
    }
}
