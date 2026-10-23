<?php

declare(strict_types=1);

namespace App\Services\Skills;

use App\Models\User;
use App\Models\UserSkill;
use App\Services\TimeEntries\TimeEntrySubordinateResolver;
use Illuminate\Database\Eloquent\Builder;

/**
 * THE single visibility and write rule of user skills (spec 0286 D-5), reused
 * by the grid, the detail, the coverage view and every write: read follows the
 * widest scope held (UserSkillScope), write is `manage` only (spec 0309 D-3: a
 * manager sees the subordinates' skills but does not edit them).
 */
final class UserSkillVisibility
{
    public const string MANAGE_PERMISSION = 'user-skills.manage';

    public function __construct(private readonly TimeEntrySubordinateResolver $subordinates) {}

    public function canManage(User $actor): bool
    {
        return $actor->can(self::MANAGE_PERMISSION);
    }

    /**
     * The ids of the people whose skills the actor sees; null means everyone.
     *
     * @return list<int>|null
     */
    public function visibleUserIds(User $actor): ?array
    {
        return match (UserSkillScope::forActor($actor)) {
            UserSkillScope::All => null,
            UserSkillScope::Team => [$actor->id, ...$this->subordinates->descendantIds($actor->id)],
            UserSkillScope::Own => [$actor->id],
            null => [],
        };
    }

    public function canViewPerson(User $actor, int $userId): bool
    {
        $visible = $this->visibleUserIds($actor);

        return $visible === null || in_array($userId, $visible, true);
    }

    /** Whether the actor may add, change or remove the skills of a person. */
    public function canWritePerson(User $actor, int $userId): bool
    {
        return $this->canManage($actor);
    }

    /** Whether the actor may write for at least someone (shows "assign a skill"). */
    public function canWriteAny(User $actor): bool
    {
        return $this->canManage($actor);
    }

    /** 403 without any access, 404 (never 403) on a record the actor cannot see. */
    public function assertCanView(User $actor, UserSkill $userSkill): void
    {
        abort_if(UserSkillScope::forActor($actor) === null, 403, 'This action is unauthorized.');
        abort_unless($this->canViewPerson($actor, (int) $userSkill->user_id), 404, 'Resource not found.');
    }

    public function assertCanWritePerson(User $actor, int $userId): void
    {
        abort_unless($this->canWritePerson($actor, $userId), 403, 'This action is unauthorized.');
    }

    /**
     * @param  Builder<UserSkill>  $query
     * @return Builder<UserSkill>
     */
    public function scopeToActor(Builder $query, User $actor): Builder
    {
        $visible = $this->visibleUserIds($actor);

        return $visible === null ? $query : $query->whereIn('user_skills.user_id', $visible);
    }
}
