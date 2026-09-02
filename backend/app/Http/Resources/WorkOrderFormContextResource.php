<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\RequestManagement\ApplicableAttribute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Wire shape for POST /api/work-orders/form-context (spec 0098, D-7): the two
 * blocks the WorkOrder form (and the Contract's "Programma" dialog) need
 * before anything is persisted, resolved by
 * App\WorkOrders\WorkOrderAttributeResolver::formContext().
 *
 * The keys and per-item projections are BYTE-FOR-BYTE what `WorkOrderResource`
 * exposes for the same two blocks — the form-context preview and the saved
 * work order's own detail render the identical sections from the identical
 * types. Identical, in turn, to QuoteFormContextResource/
 * RequestFormContextResource: same section, same components, another module.
 */
class WorkOrderFormContextResource extends JsonResource
{
    /**
     * @param  array{applicable_attributes: Collection<int, ApplicableAttribute>, attribute_layout: array<string, mixed>|null}  $resource
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
        ];
    }
}
