<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\PurchaseInvoice;
use App\Support\ForSelectSubtitle;
use Illuminate\Http\Request;

/**
 * For-select projection of a PurchaseInvoice (ADR 0011, spec 0347): `label` is the supplier document number (a proforma has none: "#id"), `subtitle` the supplier and the date.
 *
 * @mixin PurchaseInvoice
 */
class PurchaseInvoiceForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->document_number ?? '#'.$this->id,
            'subtitle' => ForSelectSubtitle::compose($this->supplier?->name, $this->document_date),
        ];
    }
}
