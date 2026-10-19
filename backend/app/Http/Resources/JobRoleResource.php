<?php

namespace App\Http\Resources;

use App\Models\JobActivity;
use App\Models\JobRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Expects `jobActivities` loaded and `users_count` (spec 0271, see
 * JobRoleService::withRelations).
 *
 * @mixin JobRole
 */
class JobRoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $actor = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'job_activity_ids' => $this->jobActivities->pluck('id')->all(),
            'job_activities' => $this->jobActivities
                ->map(static fn (JobActivity $activity): array => ['id' => $activity->id, 'name' => $activity->name])
                ->all(),
            'users_count' => (int) $this->users_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'abilities' => [
                'update' => (bool) $actor?->can('job-roles.update'),
                'delete' => (bool) $actor?->can('job-roles.delete'),
            ],
        ];
    }
}
