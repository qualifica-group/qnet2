<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\JobActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Job activity catalogue (spec 0271 D-5): linked to job roles; the link is
 * edited from the role side only.
 */
#[Fillable(['name', 'description'])]
class JobActivity extends BaseModel
{
    /** @use HasFactory<JobActivityFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return BelongsToMany<JobRole, $this>
     */
    public function jobRoles(): BelongsToMany
    {
        return $this->belongsToMany(JobRole::class, 'job_activity_job_role');
    }
}
