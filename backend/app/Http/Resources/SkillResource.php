<?php

namespace App\Http\Resources;

use App\Models\JobRole;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Expects `jobRoles` loaded and `user_skills_count` (spec 0286, see
 * SkillService::withRelations).
 *
 * @mixin Skill
 */
class SkillResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'job_roles' => $this->jobRoles
                ->map(static fn (JobRole $role): array => ['id' => $role->id, 'name' => $role->name, 'min_level' => (int) $role->pivot->min_level])
                ->all(),
            'users_count' => (int) $this->user_skills_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
