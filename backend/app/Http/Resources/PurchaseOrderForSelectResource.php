<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\PurchaseOrder;
use App\Support\ForSelectSubtitle;
use Illuminate\Http\Request;

/**
 * For-select projection of a PurchaseOrder (ADR 0011, spec 0347): `label` is the public "number/year" code, `subtitle` the supplier and the order date.
 *
 * @mixin PurchaseOrder
 */
class PurchaseOrderForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->code(),
            'subtitle' => ForSelectSubtitle::compose($this->supplier?->name, $this->order_date),
        ];
    }
}
