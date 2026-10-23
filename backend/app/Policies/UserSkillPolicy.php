<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Abstracts\BasePolicy;
use App\Services\Skills\UserSkillScope;

/**
 * Policy for the `user-skills` resource (spec 0286 D-5): `viewAny` sees one's
 * own skills, `viewAll` everyone (read only), `manage` writes for anyone;
 * having active subordinates in the org chart also opens the module and shows
 * them, read only (spec 0309 D-2/D-3). Record-level visibility (404) and write
 * rights (403) are enforced by UserSkillVisibility.
 */
class UserSkillPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'user-skills';
    }

    public function viewAny(User $user): bool
    {
        return UserSkillScope::forActor($user) !== null;
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['viewAny', 'viewAll', 'manage'];
    }
}
