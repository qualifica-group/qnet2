<?php

namespace App\Http\Requests\StaffCourses;

use App\DataObjects\StaffCourses\StaffCourseData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/staff-courses (spec 0284 D-2).
 * Authorization stays in the controller (StaffCoursePolicy).
 */
class StoreStaffCourseRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public const int NAME_MAX = 255;

    public const int VALIDITY_MONTHS_MAX = 240;

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
            'name' => ['required', 'string', 'max:'.self::NAME_MAX, Rule::unique('staff_courses', 'name')],
            ...$this->sharedRules(),
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function sharedRules(): array
    {
        return [
            'description' => ['sometimes', 'nullable', 'string'],
            'duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'validity_months' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::VALIDITY_MONTHS_MAX],
            'is_active' => ['sometimes', 'boolean'],
            'job_role_ids' => ['sometimes', 'nullable', 'array'],
            'job_role_ids.*' => ['integer', 'distinct', 'exists:job_roles,id'],
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
        return 'staff-courses';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    public function toData(): StaffCourseData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return StaffCourseData::fromValidated($validated);
    }
}
