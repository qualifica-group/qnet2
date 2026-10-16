<?php

namespace App\Services;

use App\DataObjects\JobRoles\JobRoleData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\JobRole;
use App\Services\Shared\NamedCatalogForSelect;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `job-roles` resource (spec 0271).
 */
class JobRoleService
{
    public function __construct(private readonly NamedCatalogForSelect $forSelect) {}

    public function create(JobRoleData $data): JobRole
    {
        return DB::transaction(function () use ($data): JobRole {
            $jobRole = JobRole::create($data->attributes());
            $this->syncActivities($jobRole, $data);

            return $this->withRelations($jobRole);
        });
    }

    public function update(JobRole $jobRole, JobRoleData $data): JobRole
    {
        return DB::transaction(function () use ($jobRole, $data): JobRole {
            $jobRole->fill($data->attributes())->save();
            $this->syncActivities($jobRole, $data);

            return $this->withRelations($jobRole);
        });
    }

    /**
     * Restrictive delete (spec 0271 D-9): a job role still assigned to
     * employment profiles cannot be removed.
     */
    public function delete(JobRole $jobRole): void
    {
        if ($jobRole->employmentProfiles()->exists()) {
            abort(409, __('job_roles.in_use'));
        }

        $jobRole->delete();
    }

    /**
     * Loads what JobRoleResource reads, so it never lazy-loads.
     */
    public function withRelations(JobRole $jobRole): JobRole
    {
        return $jobRole->load('jobActivities')->loadCount(['employmentProfiles as users_count']);
    }

    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        return $this->forSelect->run(JobRole::class, $query);
    }

    private function syncActivities(JobRole $jobRole, JobRoleData $data): void
    {
        if ($data->jobActivityIds() !== null) {
            $jobRole->jobActivities()->sync($data->jobActivityIds());
        }
    }
}
