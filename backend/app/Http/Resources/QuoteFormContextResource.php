<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\RequestManagement\ApplicableAttribute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Wire shape for POST /api/quotes/form-context (spec 0084, D-5): the two
 * blocks the Offerta form needs before anything is persisted, resolved by
 * App\Quotes\QuoteAttributeResolver::formContext().
 *
 * The keys and per-item projections are BYTE-FOR-BYTE what `QuoteResource`
 * exposes for the same two blocks — the form-context preview and the saved
 * quote's own detail render the identical sections from the identical types.
 */
class QuoteFormContextResource extends JsonResource
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
