<?php

namespace App\Http\Resources;

use App\Models\CompanyClosure;
use App\Support\OperationalSiteLabel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Projection of a CompanyClosure (spec 0304). Expects the relations of
 * CompanyClosureService::RELATIONS and `absence_requests_count`; `generation`
 * is added by the write endpoints of a collective leave.
 *
 * @mixin CompanyClosure
 */
class CompanyClosureResource extends JsonResource
{
    /**
     * @param  array<string, mixed>|null  $generation
     */
    public function __construct($resource, private readonly ?array $generation = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'starts_on' => $this->starts_on->format('Y-m-d'),
            'ends_on' => $this->ends_on->format('Y-m-d'),
            'kind' => $this->kind->value,
            'absence_type' => $this->absenceType === null ? null : ['id' => $this->absenceType->id, 'name' => $this->absenceType->name],
            'applies_to_all_sites' => $this->applies_to_all_sites,
            'operational_sites' => $this->operationalSites
                ->map(static fn ($site): array => ['id' => $site->id, 'name' => OperationalSiteLabel::displayName($site)])
                ->values()->all(),
            'repeats_yearly' => $this->repeats_yearly,
            'is_started' => $this->isStarted(),
            'generated_requests_count' => (int) $this->absence_requests_count,
            ...($this->generation === null ? [] : ['generation' => $this->generation]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
