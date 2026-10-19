<?php

namespace App\Services\Skills;

use App\Models\User;
use App\Models\UserSkill;

/**
 * Writes of the user skills register (spec 0286 D-4).
 */
class UserSkillService
{
    /** Relations read by UserSkillResource. */
    public const array RELATIONS = ['user:id,name', 'skill:id,name,category', 'assessedBy:id,name'];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $actor, array $attributes): UserSkill
    {
        return $this->detail(UserSkill::create([...$attributes, 'assessed_by' => $actor->id]));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $actor, UserSkill $userSkill, array $attributes): UserSkill
    {
        $userSkill->fill([...$attributes, 'assessed_by' => $actor->id])->save();

        return $this->detail($userSkill);
    }

    public function detail(UserSkill $userSkill): UserSkill
    {
        return $userSkill->unsetRelations()->load(self::RELATIONS);
    }
}
