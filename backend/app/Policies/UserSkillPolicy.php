<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Abstracts\BasePolicy;
use App\Services\Skills\UserSkillScope;

/**
 * Policy for the `user-skills` resource (spec 0286 D-5): `viewAny` sees one's
 * own skills, `viewTeam` also the active descendants (and writes theirs),
 * `viewAll` everyone (read only), `manage` writes for anyone. The widest held
 * wins, so a wider ability opens the module too. Record-level visibility (404)
 * and write rights (403) are enforced by UserSkillVisibility.
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
        return ['viewAny', 'viewTeam', 'viewAll', 'manage'];
    }
}
