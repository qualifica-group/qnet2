<?php

namespace App\Tables\AbsenceRequests;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The "Le mie" grid (domain `absence-requests`, spec 0278): the actor's own requests.
 */
class AbsenceRequestsTableDefinition extends AbstractAbsenceRequestsTableDefinition
{
    public function domain(): string
    {
        return 'absence-requests';
    }

    protected function applyScope(Builder $query, User $actor): void
    {
        $query->where('absence_requests.user_id', $actor->id);
    }
}
