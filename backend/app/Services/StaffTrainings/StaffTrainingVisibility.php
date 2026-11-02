<?php

declare(strict_types=1);

namespace App\Services\StaffTrainings;

use App\Models\StaffTraining;
use App\Models\User;
use App\Services\TimeEntries\TimeEntrySubordinateResolver;
use Illuminate\Database\Eloquent\Builder;

/**
 * THE single visibility rule of staff trainings (spec 0284 D-5), reused by the
 * grid, the detail, the attachment and the compliance view: the same rule is
 * exposed as a record check, a query scope and the list of visible people, so
 * the three can never disagree.
 */
final class StaffTrainingVisibility
{
    public const string MANAGE_PERMISSION = 'staff-trainings.manage';

    public function __construct(private readonly TimeEntrySubordinateResolver $subordinates) {}

    public function canManage(User $actor): bool
    {
        return $actor->can(self::MANAGE_PERMISSION);
    }

    /**
     * The ids of the people whose trainings the actor sees; null means everyone.
     * An actor with no access sees nobody.
     *
     * @return list<int>|null
     */
    public function visibleUserIds(User $actor): ?array
    {
        return match (StaffTrainingScope::forActor($actor)) {
            StaffTrainingScope::All => null,
            StaffTrainingScope::Team => [$actor->id, ...$this->subordinates->descendantIds($actor->id)],
            StaffTrainingScope::Own => [$actor->id],
            null => [],
        };
    }

    public function canViewPerson(User $actor, int $userId): bool
    {
        $visible = $this->visibleUserIds($actor);

        return $visible === null || in_array($userId, $visible, true);
    }

    public function canView(User $actor, StaffTraining $training): bool
    {
        return $this->canViewPerson($actor, (int) $training->user_id);
    }

    /** 403 without any access, 404 (never 403) on a training the actor cannot see. */
    public function assertCanView(User $actor, StaffTraining $training): void
    {
        abort_if(StaffTrainingScope::forActor($actor) === null, 403, __('You cannot access staff trainings.'));
        abort_unless($this->canView($actor, $training), 404, 'Resource not found.');
    }

    /**
     * @param  Builder<StaffTraining>  $query
     * @return Builder<StaffTraining>
     */
    public function scopeToActor(Builder $query, User $actor): Builder
    {
        $visible = $this->visibleUserIds($actor);

        return $visible === null ? $query : $query->whereIn('staff_trainings.user_id', $visible);
    }
}
