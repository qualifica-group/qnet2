<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\VatRate;
use Illuminate\Http\Request;

/**
 * For-select projection of a VatRate (GET /api/vat-rates/for-select).
 *
 * Minimal by design (ADR 0011): label = name, no subtitle/avatar. Mirrors
 * SourceForSelectResource.
 *
 * `meta.rate` (spec 0065 follow-up) is ADDITIVE: lets a Quote line form
 * recompute the client-side preview when the actor picks a VAT rate that
 * was never hydrated via the product's own `meta.vat_rate` — no existing
 * key changes name or type.
 *
 * @mixin VatRate
 */
class VatRateForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
            'meta' => [
                'rate' => $this->rate,
            ],
        ];
    }
}
