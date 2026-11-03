<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\Invoice;
use App\Support\ForSelectSubtitle;
use Illuminate\Http\Request;

/**
 * For-select projection of an Invoice (ADR 0011, spec 0347): `label` is "number/year", `subtitle` the customer and the document date.
 *
 * @mixin Invoice
 */
class InvoiceForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->number.'/'.$this->year,
            'subtitle' => ForSelectSubtitle::compose($this->customer?->name, $this->document_date),
        ];
    }
}
