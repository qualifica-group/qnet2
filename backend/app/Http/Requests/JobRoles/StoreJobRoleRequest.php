<?php

namespace App\Http\Requests\JobRoles;

use App\DataObjects\JobRoles\JobRoleData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/job-roles (spec 0271). Authorization
 * stays in the controller (JobRolePolicy).
 */
class StoreJobRoleRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public const int NAME_MAX = 191;

    public const int DESCRIPTION_MAX = 1000;

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
            'name' => ['required', 'string', 'max:'.self::NAME_MAX, Rule::unique('job_roles', 'name')],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.self::DESCRIPTION_MAX],
            'job_activity_ids' => ['sometimes', 'nullable', 'array'],
            'job_activity_ids.*' => ['integer', 'distinct', 'exists:job_activities,id'],
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
        return 'job-roles';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    public function toData(): JobRoleData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return JobRoleData::fromValidated($validated);
    }
}
