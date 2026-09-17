<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\Address;
use App\Models\OperationalSite;
use App\Support\OperationalSiteLabel;
use Illuminate\Http\Request;

/**
 * For-select projection of an OperationalSite (GET /api/operational-sites/for-select).
 *
 * A site has no own name (identity = its address, see OperationalSite): the
 * label is composed by App\Support\OperationalSiteLabel, the ONE definition
 * of it (spec 0112 D-8) — `line1`, plus " - {city}" when the address has a
 * city; subtitle = postal_code when present (omitted otherwise,
 * ForSelectResource's null-optional rule). Relies on the Service
 * eager-loading the primary address (+ city) —
 * never a full OperationalSiteResource.
 *
 * @mixin OperationalSite
 */
class OperationalSiteForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        /** @var Address|null $address */
        $address = $this->addresses->first();

        return [
            'id' => $this->id,
            'label' => OperationalSiteLabel::compose($address),
            'subtitle' => $address?->postal_code,
        ];
    }
}
