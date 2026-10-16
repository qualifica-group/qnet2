<?php

namespace App\Services;

use App\DataObjects\JobActivities\JobActivityData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\JobActivity;
use App\Services\Shared\NamedCatalogForSelect;

/**
 * Business logic for the `job-activities` resource (spec 0271). The link to
 * job roles is written from the role side only (D-5).
 */
class JobActivityService
{
    public function __construct(private readonly NamedCatalogForSelect $forSelect) {}

    public function create(JobActivityData $data): JobActivity
    {
        return $this->withRelations(JobActivity::create($data->attributes()));
    }

    public function update(JobActivity $jobActivity, JobActivityData $data): JobActivity
    {
        $jobActivity->fill($data->attributes())->save();

        return $this->withRelations($jobActivity);
    }

    /**
     * Restrictive delete (spec 0271 D-9): an activity linked to a job role
     * cannot be removed.
     */
    public function delete(JobActivity $jobActivity): void
    {
        if ($jobActivity->jobRoles()->exists()) {
            abort(409, __('job_activities.in_use'));
        }

        $jobActivity->delete();
    }

    /**
     * Loads what JobActivityResource reads, so it never lazy-loads.
     */
    public function withRelations(JobActivity $jobActivity): JobActivity
    {
        return $jobActivity->load('jobRoles');
    }

    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        return $this->forSelect->run(JobActivity::class, $query);
    }
}
