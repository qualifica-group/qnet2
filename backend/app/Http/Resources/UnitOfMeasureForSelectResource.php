<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\UnitOfMeasure;
use Illuminate\Http\Request;

/**
 * For-select projection of a UnitOfMeasure (GET
 * /api/units-of-measure/for-select, spec 0088).
 *
 * `label` = name, `subtitle` = symbol, `meta.symbol` lets a consumer form
 * (e.g. the Product picker) render the symbol alongside the label without a
 * second lookup.
 *
 * @mixin UnitOfMeasure
 */
class UnitOfMeasureForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
            'subtitle' => $this->symbol,
            'meta' => ['symbol' => $this->symbol],
        ];
    }
}
