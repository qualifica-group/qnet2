<?php

namespace App\Http\Requests\UserSkills;

use App\Enums\SkillCoverageStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query of GET /api/user-skills/coverage (spec 0286 D-6). Access is decided
 * by the controller (UserSkillPolicy::viewAny).
 */
class UserSkillCoverageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(SkillCoverageStatus::class)],
            'business_function_id' => ['nullable', 'integer', 'exists:business_functions,id'],
            'skill_id' => ['nullable', 'integer', 'exists:skills,id'],
        ];
    }
}
