<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Abstracts\BasePolicy;

/**
 * Policy for the `absence-requests` resource (spec 0278): `viewAny` opens the
 * module, `create` files a request, `manageAll` is the HR office ("sees and
 * manages everyone", like `time-entries.manageAll`). Record-level visibility
 * and the approver rules are enforced by the visibility scope and the
 * workflow service, not by assignable permissions.
 */
class AbsenceRequestPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'absence-requests';
    }

    public function manageAll(User $user): bool
    {
        return $user->can($this->permission('manageAll'));
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['viewAny', 'create', 'manageAll'];
    }
}
