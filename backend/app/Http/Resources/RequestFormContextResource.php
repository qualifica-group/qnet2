<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\RequestManagement\ApplicableAttribute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Wire shape for POST /api/request-management/form-context (user directive
 * 2026-08-07): the block the create form needs before anything is persisted,
 * resolved by RequestAttributeResolver::forCategories().
 *
 * The keys and the per-item projections are BYTE-FOR-BYTE the ones
 * RequestManagementResource exposes for the same block — the create form and
 * the work panel render the identical section from the identical types, which
 * is the whole point of this endpoint. Identical, in turn, to
 * QuoteFormContextResource: same section, same components, two modules.
 */
class RequestFormContextResource extends JsonResource
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
