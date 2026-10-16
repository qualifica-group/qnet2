<?php

namespace App\Http\Resources;

use App\Models\JobActivity;
use App\Models\JobRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Expects `jobRoles` loaded (spec 0271, see JobActivityService::withRelations).
 *
 * @mixin JobActivity
 */
class JobActivityResource extends JsonResource
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
            'job_role_ids' => $this->jobRoles->pluck('id')->all(),
            'job_roles' => $this->jobRoles
                ->map(static fn (JobRole $role): array => ['id' => $role->id, 'name' => $role->name])
                ->all(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'abilities' => [
                'update' => (bool) $actor?->can('job-activities.update'),
                'delete' => (bool) $actor?->can('job-activities.delete'),
            ],
        ];
    }
}
