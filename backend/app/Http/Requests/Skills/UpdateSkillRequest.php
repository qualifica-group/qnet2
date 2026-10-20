<?php

namespace App\Http\Requests\Skills;

use App\Models\Skill;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/skills/{skill} (spec 0286). Every
 * field is `sometimes` to support partial updates.
 */
class UpdateSkillRequest extends StoreSkillRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:'.self::NAME_MAX, Rule::unique('skills', 'name')->ignore($this->skill()->id)],
            ...$this->sharedRules(),
        ];
    }

    protected function authorizationModel(): ?Model
    {
        return $this->skill();
    }

    private function skill(): Skill
    {
        /** @var Skill $skill */
        $skill = $this->route('skill');

        return $skill;
    }
}
