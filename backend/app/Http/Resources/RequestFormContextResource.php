<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\SummarizesWorkflowStatuses;
use App\Models\OpportunityWorkflowStatus;
use App\RequestManagement\ApplicableAttribute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Wire shape for POST /api/request-management/form-context (user directive
 * 2026-07-31): the three blocks the create form needs before anything is
 * persisted, resolved by RequestFormContextResolver.
 *
 * The keys and the per-item projections are BYTE-FOR-BYTE the ones
 * RequestManagementResource exposes for the same three blocks — the create
 * form and the work panel render the identical sections from the identical
 * types, which is the whole point of this endpoint.
 */
class RequestFormContextResource extends JsonResource
{
    use SummarizesWorkflowStatuses;

    /**
     * @param  array{applicable_attributes: Collection<int, ApplicableAttribute>, attribute_layout: array<string, mixed>|null, workflow_statuses: Collection<int, OpportunityWorkflowStatus>}  $resource
     */
    public function __construct(array $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'applicable_attributes' => $this->resource['applicable_attributes']
                ->map(fn (ApplicableAttribute $attribute): array => $attribute->toArray())
                ->values()
                ->all(),
            'attribute_layout' => $this->resource['attribute_layout'],
            'workflow_statuses' => $this->summarizeWorkflowStatuses($this->resource['workflow_statuses']),
        ];
    }
}
