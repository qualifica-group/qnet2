<?php

namespace App\Http\Resources;

use App\Models\Address;
use App\Models\OperationalSite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OperationalSite
 *
 * The site IS its address (spec 0011): unlike CompanyResource's nested
 * `address` object, every address column is emitted FLAT at the top level
 * (id/line1/postal_code + the geo FK cascade with their resolved {id,name}
 * objects), matching the flat write payload 1:1. The caller
 * (OperationalSiteController/OperationalSiteService) always eager-loads the
 * `addresses` relation (+ its geo names) before building this resource.
 */
class OperationalSiteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $address = $this->primaryAddress;

        return [
            'id' => $this->id,
            'alias' => $this->alias,
            'line1' => $address?->line1,
            'postal_code' => $address?->postal_code,
            'country_id' => $address?->country_id,
            'country' => $this->geoSummary($address),
            'state_id' => $address?->state_id,
            'region' => $this->geoSummary($address, 'state'),
            'province_id' => $address?->province_id,
            'province' => $this->geoSummary($address, 'province'),
            'city_id' => $address?->city_id,
            'city' => $this->geoSummary($address, 'city'),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function geoSummary(?Address $address, string $relation = 'country'): ?array
    {
        $geo = $address?->{$relation};

        return $geo === null ? null : ['id' => $geo->id, 'name' => $geo->name];
    }
}
