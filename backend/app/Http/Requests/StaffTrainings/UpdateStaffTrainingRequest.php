<?php

namespace App\Http\Requests\StaffTrainings;

use App\Enums\StaffTrainingOutcome;
use App\Models\StaffTraining;
use App\Services\StaffTrainings\StaffTrainingVisibility;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/staff-trainings/{staffTraining}
 * (spec 0284). Every field is `sometimes` to support partial updates. A
 * training the actor cannot see is a 404, before the `manage` check.
 */
class UpdateStaffTrainingRequest extends StoreStaffTrainingRequest
{
    public function authorize(): bool
    {
        /** @var StaffTraining $training */
        $training = $this->route('staffTraining');
        $visibility = app(StaffTrainingVisibility::class);

        $visibility->assertCanView($this->user(), $training);

        return $visibility->canManage($this->user());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'staff_course_id' => ['sometimes', 'required', 'integer', 'exists:staff_courses,id'],
            'completed_on' => ['sometimes', 'required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'outcome' => ['sometimes', 'required', Rule::enum(StaffTrainingOutcome::class)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:'.self::NOTES_MAX],
        ];
    }
}
