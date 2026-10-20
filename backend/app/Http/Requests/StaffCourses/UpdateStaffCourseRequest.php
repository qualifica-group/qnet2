<?php

namespace App\Http\Requests\StaffCourses;

use App\Models\StaffCourse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/staff-courses/{staffCourse}
 * (spec 0284). Every field is `sometimes` to support partial updates.
 */
class UpdateStaffCourseRequest extends StoreStaffCourseRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:'.self::NAME_MAX, Rule::unique('staff_courses', 'name')->ignore($this->staffCourse()->id)],
            ...$this->sharedRules(),
        ];
    }

    protected function authorizationModel(): ?Model
    {
        return $this->staffCourse();
    }

    private function staffCourse(): StaffCourse
    {
        /** @var StaffCourse $staffCourse */
        $staffCourse = $this->route('staffCourse');

        return $staffCourse;
    }
}
