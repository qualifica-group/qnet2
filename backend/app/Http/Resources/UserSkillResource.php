<?php

namespace App\Http\Resources;

use App\Models\UserSkill;
use App\Services\Skills\UserSkillVisibility;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Projection of a UserSkill (spec 0286). Expects the relations loaded by
 * UserSkillService::RELATIONS. `can_edit` is computed for the requesting actor.
 *
 * @mixin UserSkill
 */
class UserSkillResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => ['id' => $this->user->id, 'name' => $this->user->name],
            'skill' => ['id' => $this->skill->id, 'name' => $this->skill->name, 'category' => $this->skill->category],
            'level' => $this->level->value,
            'assessed_on' => $this->assessed_on->format('Y-m-d'),
            'notes' => $this->notes,
            'assessed_by' => $this->assessedBy === null ? null : ['id' => $this->assessedBy->id, 'name' => $this->assessedBy->name],
            'can_edit' => app(UserSkillVisibility::class)->canWritePerson($request->user(), (int) $this->user_id),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
