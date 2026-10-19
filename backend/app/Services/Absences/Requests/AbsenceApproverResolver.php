<?php

declare(strict_types=1);

namespace App\Services\Absences\Requests;

use App\Models\BusinessFunction;
use App\Models\EmploymentProfile;
use App\Models\User;
use App\Services\RoleAssignmentGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * Who approves the absence requests of a person (spec 0278, D-7), snapshotted
 * on the request at submission: the active direct superiors; else the active
 * managers of the person's business functions; else the active holders of
 * `absence-requests.manageAll`. The person is never among them.
 */
final class AbsenceApproverResolver
{
    public const string MANAGE_ALL_PERMISSION = 'absence-requests.manageAll';

    /**
     * @return Collection<int, User>
     */
    public function resolve(User $person): Collection
    {
        // Step 1: direct superiors, then function managers, then the HR office
        foreach ([$this->superiors(...), $this->functionManagers(...), $this->manageAllHolders(...)] as $source) {
            $approvers = $source($person)->reject(static fn (User $user): bool => (int) $user->id === (int) $person->id)->values();

            if ($approvers->isNotEmpty()) {
                return $approvers;
            }
        }

        return collect();
    }

    /**
     * @return Collection<int, User>
     */
    private function superiors(User $person): Collection
    {
        $profile = EmploymentProfile::query()->where('user_id', $person->id)->first();

        return $profile === null ? collect() : $profile->reportsTo()->where('users.is_active', true)->get()->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function functionManagers(User $person): Collection
    {
        $managerIds = BusinessFunction::query()
            ->whereIn('id', $person->businessFunctions()->select('business_functions.id'))
            ->whereNotNull('manager_id')
            ->pluck('manager_id');

        return User::query()->whereIn('id', $managerIds)->where('is_active', true)->orderBy('name')->get();
    }

    /**
     * The super-admin holds every ability through `Gate::before`, never as a
     * stored permission, so the permission scope alone would miss them.
     *
     * @return Collection<int, User>
     */
    private function manageAllHolders(User $person): Collection
    {
        return User::query()
            ->where('users.is_active', true)
            ->where(function (Builder $holders): void {
                $holders->whereHas('roles', static fn (Builder $roles) => $roles->where('name', RoleAssignmentGuard::PRIVILEGED_ROLE));

                try {
                    $holders->orWhere(static fn (Builder $granted) => $granted->permission(self::MANAGE_ALL_PERMISSION));
                } catch (PermissionDoesNotExist) {
                    // Permission not synced yet: only the super-admins hold it.
                }
            })
            ->orderBy('users.name')
            ->get();
    }
}
