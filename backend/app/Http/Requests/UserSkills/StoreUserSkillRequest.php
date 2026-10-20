<?php

namespace App\Http\Requests\UserSkills;

use App\Enums\SkillLevel;
use App\Models\UserSkill;
use App\Services\Skills\UserSkillVisibility;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/user-skills (spec 0286 D-4/D-5). The
 * write right on the target person is checked here, so a caller who may not
 * write gets a 403 before any validation message; one person + skill pair
 * exists only once.
 */
class StoreUserSkillRequest extends FormRequest
{
    public const int NOTES_MAX = 2000;

    public function authorize(): bool
    {
        $visibility = app(UserSkillVisibility::class);
        $userId = $this->input('user_id');

        // Without a usable person only validation can answer, but only for who writes at all.
        return is_numeric($userId)
            ? $visibility->canWritePerson($this->user(), (int) $userId)
            : $visibility->canWriteAny($this->user());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'skill_id' => ['required', 'integer', 'exists:skills,id'],
            'level' => ['required', Rule::enum(SkillLevel::class)],
            'assessed_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:'.self::NOTES_MAX],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $validator->errors()->hasAny(['user_id', 'skill_id']) && $this->pairIsTaken()) {
                $validator->errors()->add('skill_id', __('skills.duplicate'));
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return $validated;
    }

    protected function ignoredId(): ?int
    {
        return null;
    }

    protected function effectiveUserId(): mixed
    {
        return $this->input('user_id');
    }

    protected function effectiveSkillId(): mixed
    {
        return $this->input('skill_id');
    }

    private function pairIsTaken(): bool
    {
        return UserSkill::query()
            ->where('user_id', $this->effectiveUserId())
            ->where('skill_id', $this->effectiveSkillId())
            ->when($this->ignoredId() !== null, fn ($query) => $query->whereKeyNot($this->ignoredId()))
            ->exists();
    }
}
