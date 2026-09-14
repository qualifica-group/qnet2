<?php

declare(strict_types=1);

namespace App\Services\TimeEntries;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * THE single implementation of the "whose segnatempo/day note is this
 * write for" rule (spec 0122, data_contract POST /api/time-entries AND PUT
 * /api/time-entries/day-notes: "user_id diverso da se stessi solo con
 * manageAll", AC-007/AC-021). Shared by TimeEntryController::store and
 * TimeEntryDayNoteController so the cross-user permission check cannot
 * drift between the two endpoints that both accept an optional `user_id`.
 */
final class TimeEntryOwnerResolver
{
    /**
     * @throws AuthorizationException 403 when
     *                                $userId names
     *                                someone other
     *                                than $actor
     *                                and $actor
     *                                lacks
     *                                `time-entries.
     *                                manageAll`.
     */
    public function resolve(?int $userId, User $actor): User
    {
        if ($userId === null || $userId === $actor->id) {
            return $actor;
        }

        abort_unless($actor->can('time-entries.manageAll'), 403);

        return User::query()->findOrFail($userId);
    }
}
