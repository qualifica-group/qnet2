<?php

namespace App\Http\Requests\StaffTrainings;

use App\Enums\StaffTrainingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query of GET /api/staff-trainings/compliance (spec 0284 D-4/D-6). Access is
 * decided by the controller (StaffTrainingPolicy::viewAny).
 */
class StaffTrainingComplianceRequest extends FormRequest
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
            'status' => ['nullable', Rule::enum(StaffTrainingStatus::class)],
            'business_function_id' => ['nullable', 'integer', 'exists:business_functions,id'],
            'staff_course_id' => ['nullable', 'integer', 'exists:staff_courses,id'],
        ];
    }
}
