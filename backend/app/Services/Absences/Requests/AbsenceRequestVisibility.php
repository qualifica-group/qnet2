<?php

declare(strict_types=1);

namespace App\Services\Absences\Requests;

use App\Models\AbsenceRequest;
use App\Models\User;
use App\Services\TimeEntries\TimeEntrySubordinateResolver;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * THE single visibility rule of absence requests (spec 0278), reused by the
 * detail, the workflow actions, the grid, the attachment and the balances: an
 * actor sees a request when it is their own, they are among its approvers,
 * the person is an active descendant of theirs, or they hold
 * `absence-requests.manageAll` (the HR office). The same predicate is exposed
 * as a record check and as a query scope, so the two can never disagree.
 */
final class AbsenceRequestVisibility
{
    public function __construct(private readonly TimeEntrySubordinateResolver $subordinates) {}

    public function canManageAll(User $actor): bool
    {
        return $actor->can(AbsenceApproverResolver::MANAGE_ALL_PERMISSION);
    }

    public function isOwnerOrManager(User $actor, AbsenceRequest $request): bool
    {
        return (int) $request->user_id === (int) $actor->id || $this->canManageAll($actor);
    }

    public function isApprover(User $actor, AbsenceRequest $request): bool
    {
        return $request->relationLoaded('approvers')
            ? $request->approvers->contains('id', $actor->id)
            : $request->approvers()->whereKey($actor->id)->exists();
    }

    /** Whether the actor may look at the absences of a person (own, descendant or manageAll). */
    public function canViewPerson(User $actor, int $userId): bool
    {
        return $userId === (int) $actor->id
            || $this->canManageAll($actor)
            || $this->subordinates->isDescendantOf($userId, $actor->id);
    }

    public function canView(User $actor, AbsenceRequest $request): bool
    {
        return $this->canViewPerson($actor, $request->user_id) || $this->isApprover($actor, $request);
    }

    /**
     * canView() for many requests at once (spec 0279 D-3): the team is
     * resolved once instead of per request; `approvers` must be eager loaded.
     *
     * @return Closure(AbsenceRequest): bool
     */
    public function viewChecker(User $actor): Closure
    {
        if ($this->canManageAll($actor)) {
            return static fn (): bool => true;
        }

        $visibleUserIds = array_flip($this->ownAndTeamIds($actor));

        return fn (AbsenceRequest $request): bool => isset($visibleUserIds[$request->user_id])
            || $this->isApprover($actor, $request);
    }

    /** 404, never 403: a request the actor cannot see must not be confirmed to exist. */
    public function assertCanView(User $actor, AbsenceRequest $request): void
    {
        abort_unless($this->canView($actor, $request), 404, 'Resource not found.');
    }

    /**
     * @param  Builder<AbsenceRequest>  $query
     * @return Builder<AbsenceRequest>
     */
    public function scopeToActor(Builder $query, User $actor): Builder
    {
        if ($this->canManageAll($actor)) {
            return $query;
        }

        return $query->where(function (Builder $scoped) use ($actor): void {
            $scoped->whereIn('absence_requests.user_id', $this->ownAndTeamIds($actor))
                ->orWhereHas('approvers', static fn (Builder $approvers) => $approvers->whereKey($actor->id));
        });
    }

    /**
     * The actor and their active descendants: the people of the "all" tab.
     *
     * @return list<int>
     */
    public function ownAndTeamIds(User $actor): array
    {
        return [$actor->id, ...$this->subordinates->descendantIds($actor->id)];
    }
}
