<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\StaffCourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Internal staff course catalogue entry (spec 0284 D-2): unrelated to the
 * courses sold to customers. A course used by a training is deactivated,
 * never deleted.
 */
#[Fillable(['name', 'description', 'duration_minutes', 'validity_months', 'is_active'])]
class StaffCourse extends BaseModel
{
    /** @use HasFactory<StaffCourseFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'validity_months' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Job roles that require this course.
     *
     * @return BelongsToMany<JobRole, $this>
     */
    public function jobRoles(): BelongsToMany
    {
        return $this->belongsToMany(JobRole::class, 'job_role_staff_course');
    }

    /**
     * @return HasMany<StaffTraining, $this>
     */
    public function trainings(): HasMany
    {
        return $this->hasMany(StaffTraining::class);
    }
}
