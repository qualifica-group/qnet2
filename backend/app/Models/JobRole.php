<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\JobRoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Job role catalogue (spec 0271 D-1, D-2): assigned to employment profiles,
 * unrelated to Spatie roles and permissions.
 */
#[Fillable(['name', 'description'])]
class JobRole extends BaseModel
{
    /** @use HasFactory<JobRoleFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return BelongsToMany<JobActivity, $this>
     */
    public function jobActivities(): BelongsToMany
    {
        return $this->belongsToMany(JobActivity::class, 'job_activity_job_role');
    }

    /**
     * @return BelongsToMany<EmploymentProfile, $this>
     */
    public function employmentProfiles(): BelongsToMany
    {
        return $this->belongsToMany(EmploymentProfile::class, 'employment_profile_job_role');
    }
}
