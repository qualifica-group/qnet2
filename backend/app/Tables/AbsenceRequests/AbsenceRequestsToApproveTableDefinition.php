<?php

namespace App\Tables\AbsenceRequests;

use App\Enums\AbsenceRequestStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The "Da approvare" grid (domain `absence-requests-to-approve`, spec 0278):
 * submitted requests the actor may decide — those in which they are among the
 * snapshotted approvers, or every one but their own for the HR office (D-15).
 */
class AbsenceRequestsToApproveTableDefinition extends AbstractAbsenceRequestsTableDefinition
{
    public function domain(): string
    {
        return 'absence-requests-to-approve';
    }

    protected function applyScope(Builder $query, User $actor): void
    {
        $query->where('absence_requests.status', AbsenceRequestStatus::Submitted->value);

        if ($this->visibility->canManageAll($actor)) {
            $query->where('absence_requests.user_id', '!=', $actor->id);

            return;
        }

        $query->whereHas('approvers', static fn (Builder $approvers) => $approvers->whereKey($actor->id));
    }
}
