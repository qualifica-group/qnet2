<?php

namespace App\Http\Requests\Skills;

use App\DataObjects\Skills\SkillData;
use App\Enums\SkillLevel;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/skills (spec 0286 D-2). Authorization
 * stays in the controller (SkillPolicy).
 */
class StoreSkillRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public const int NAME_MAX = 255;

    public const int CATEGORY_MAX = 100;

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
            'name' => ['required', 'string', 'max:'.self::NAME_MAX, Rule::unique('skills', 'name')],
            ...$this->sharedRules(),
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function sharedRules(): array
    {
        return [
            'category' => ['sometimes', 'nullable', 'string', 'max:'.self::CATEGORY_MAX],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'job_roles' => ['sometimes', 'nullable', 'array'],
            'job_roles.*.id' => ['required', 'integer', 'distinct', 'exists:job_roles,id'],
            'job_roles.*.min_level' => ['required', Rule::enum(SkillLevel::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'skills';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    public function toData(): SkillData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return SkillData::fromValidated($validated);
    }
}
