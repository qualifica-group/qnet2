<?php

declare(strict_types=1);

namespace App\Services\TimeEntries;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Rule R (spec 0122, data_contract "AUTORIZZAZIONE LETTURA"): resolves WHOSE
 * dashboard a `user_id` filter names, for the list/overview/pulse endpoints
 * (AC-016). `time-entries.viewAny` is always required; beyond that, a
 * `user_id` absent or equal to the actor never needs anything more; a
 * `manageAll` or `viewAll` holder (spec 0309 D-6) may read anyone; a plain responsabile may read only an
 * active descendant (`TimeEntrySubordinateResolver`, D-10) — an unknown,
 * inactive or unrelated id is simply not in that list, so it 403s exactly
 * like a stranger's id does, with no separate "not found" branch.
 *
 * Deliberately NOT reused for WRITES: `TimeEntryOwnerResolver` covers those
 * (self or `manageAll` only — a responsabile or a `viewAll` holder reads but
 * never writes someone else's segnatempo, D-8).
 */
final class TimeEntryReadAuthorizer
{
    public function __construct(private readonly TimeEntrySubordinateResolver $subordinates) {}

    public function resolveSelectedUser(?int $filterUserId, User $actor): User
    {
        Gate::forUser($actor)->authorize('time-entries.viewAny');

        if ($filterUserId === null || $filterUserId === $actor->id) {
            return $actor;
        }

        abort_unless(
            $actor->can('time-entries.manageAll')
                || $actor->can('time-entries.viewAll')
                || $this->subordinates->isDescendantOf($filterUserId, $actor->id),
            403,
            __('You cannot see the time entries of this person.'),
        );

        return User::query()->findOrFail($filterUserId);
    }
}
