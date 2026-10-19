<?php

namespace App\Tables\AbsenceRequests;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The "Tutte" grid (domain `absence-requests-all`, spec 0278): the actor's own
 * requests plus those of their active descendants; every request for the HR
 * office (`manageAll`).
 */
class AbsenceRequestsAllTableDefinition extends AbstractAbsenceRequestsTableDefinition
{
    public function domain(): string
    {
        return 'absence-requests-all';
    }

    protected function applyScope(Builder $query, User $actor): void
    {
        if (! $this->visibility->canManageAll($actor)) {
            $query->whereIn('absence_requests.user_id', $this->visibility->ownAndTeamIds($actor));
        }
    }
}
