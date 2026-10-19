<?php

namespace App\Http\Requests\JobRoles;

use App\DataObjects\JobRoles\JobRoleData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\JobRole;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/job-roles/{jobRole} (spec 0271).
 * Every field is `sometimes` to support partial updates.
 */
class UpdateJobRoleRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $jobRole = $this->route('jobRole');
        $ignoreId = $jobRole instanceof JobRole ? $jobRole->id : null;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:'.StoreJobRoleRequest::NAME_MAX, Rule::unique('job_roles', 'name')->ignore($ignoreId)],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.StoreJobRoleRequest::DESCRIPTION_MAX],
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
        /** @var JobRole $jobRole */
        $jobRole = $this->route('jobRole');

        return $jobRole;
    }

    public function toData(): JobRoleData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return JobRoleData::fromValidated($validated);
    }
}
