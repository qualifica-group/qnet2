<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Abstracts\BasePolicy;
use App\Services\StaffTrainings\StaffTrainingScope;

/**
 * Policy for the `staff-trainings` resource (spec 0284 D-5): `viewAny` sees
 * one's own trainings, `viewAll` everyone, `manage` writes for anyone (HR
 * office); having active subordinates in the org chart also opens the module
 * and shows them (spec 0309 D-2, no team permission). Record-level visibility (404) is
 * enforced by StaffTrainingVisibility, not here.
 */
class StaffTrainingPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'staff-trainings';
    }

    public function viewAny(User $user): bool
    {
        return StaffTrainingScope::forActor($user) !== null;
    }

    public function manage(User $user): bool
    {
        return $user->can($this->permission('manage'));
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['viewAny', 'viewAll', 'manage'];
    }
}
