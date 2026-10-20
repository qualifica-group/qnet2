<?php

namespace App\Http\Requests\UserSkills;

use App\Enums\SkillLevel;
use App\Models\UserSkill;
use App\Services\Skills\UserSkillVisibility;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/user-skills/{userSkill} (spec
 * 0286). Every field is `sometimes`. A record the actor cannot see is a 404,
 * one they cannot write (or a move onto a person they cannot write) a 403.
 */
class UpdateUserSkillRequest extends StoreUserSkillRequest
{
    public function authorize(): bool
    {
        $visibility = app(UserSkillVisibility::class);
        $visibility->assertCanView($this->user(), $this->userSkill());

        $targetUserId = is_numeric($this->input('user_id')) ? (int) $this->input('user_id') : (int) $this->userSkill()->user_id;

        return $visibility->canWritePerson($this->user(), (int) $this->userSkill()->user_id)
            && $visibility->canWritePerson($this->user(), $targetUserId);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'skill_id' => ['sometimes', 'required', 'integer', 'exists:skills,id'],
            'level' => ['sometimes', 'required', Rule::enum(SkillLevel::class)],
            'assessed_on' => ['sometimes', 'required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:'.self::NOTES_MAX],
        ];
    }

    protected function ignoredId(): ?int
    {
        return $this->userSkill()->id;
    }

    protected function effectiveUserId(): mixed
    {
        return $this->input('user_id', $this->userSkill()->user_id);
    }

    protected function effectiveSkillId(): mixed
    {
        return $this->input('skill_id', $this->userSkill()->skill_id);
    }

    private function userSkill(): UserSkill
    {
        /** @var UserSkill $userSkill */
        $userSkill = $this->route('userSkill');

        return $userSkill;
    }
}
