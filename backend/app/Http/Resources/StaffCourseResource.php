<?php

namespace App\Http\Resources;

use App\Models\JobRole;
use App\Models\StaffCourse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Expects `jobRoles` loaded and `trainings_count` (spec 0284, see
 * StaffCourseService::withRelations).
 *
 * @mixin StaffCourse
 */
class StaffCourseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'duration_minutes' => $this->duration_minutes,
            'validity_months' => $this->validity_months,
            'is_active' => $this->is_active,
            'job_roles' => $this->jobRoles
                ->map(static fn (JobRole $role): array => ['id' => $role->id, 'name' => $role->name])
                ->all(),
            'trainings_count' => (int) $this->trainings_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
