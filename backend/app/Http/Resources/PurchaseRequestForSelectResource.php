<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\PurchaseRequest;
use App\Support\ForSelectSubtitle;
use Illuminate\Http\Request;

/**
 * For-select projection of a PurchaseRequest (ADR 0011, spec 0347): `label` is the subject, `subtitle` the requester and the request date.
 *
 * @mixin PurchaseRequest
 */
class PurchaseRequestForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->subject,
            'subtitle' => ForSelectSubtitle::compose($this->requester?->name, $this->requested_at),
        ];
    }
}
