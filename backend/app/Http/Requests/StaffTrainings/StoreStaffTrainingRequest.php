<?php

namespace App\Http\Requests\StaffTrainings;

use App\Enums\StaffTrainingOutcome;
use App\Services\StaffTrainings\StaffTrainingVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/staff-trainings (spec 0284 D-3). Only
 * `staff-trainings.manage` writes, checked here so a caller without it gets a
 * 403 before any validation message.
 */
class StoreStaffTrainingRequest extends FormRequest
{
    public const int NOTES_MAX = 2000;

    public function authorize(): bool
    {
        return app(StaffTrainingVisibility::class)->canManage($this->user());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'staff_course_id' => ['required', 'integer', 'exists:staff_courses,id'],
            'completed_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'outcome' => ['required', Rule::enum(StaffTrainingOutcome::class)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:'.self::NOTES_MAX],
        ];
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
}
