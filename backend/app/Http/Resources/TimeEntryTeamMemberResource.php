<?php

namespace App\Http\Resources;

use App\DataObjects\TimeEntries\TimeEntryTeamMemberData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One item of GET /api/time-entries/stats/team (spec 0122, data_contract,
 * D-10/D-11): the member's identity/org data plus its pulse coverage/cluster.
 * `avatar_url` mirrors `UserResource::avatarDataUri()` verbatim (same inline
 * data: URI, no extra authenticated request from the client).
 *
 * @mixin TimeEntryTeamMemberData
 */
class TimeEntryTeamMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TimeEntryTeamMemberData $item */
        $item = $this->resource;

        return [
            'user' => [
                'id' => $item->user->id,
                'name' => $item->user->name,
                'email' => $item->user->email,
                'avatar_url' => $item->user->avatarDataUri(),
            ],
            'manager_id' => $item->managerId,
            'job_description' => $item->jobDescription,
            'roles' => $item->user->roles->pluck('name')->values()->all(),
            'business_functions' => $item->businessFunctions,
            'operational_site' => $item->operationalSite === null ? null : [
                'id' => $item->operationalSite->id,
                'label' => $item->operationalSite->alias,
            ],
            'coverage' => $item->coverage,
            'primary_cluster' => $item->primaryCluster,
        ];
    }
}
