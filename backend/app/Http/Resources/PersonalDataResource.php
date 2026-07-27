<?php

namespace App\Http\Resources;

use App\Models\PersonalData;
use App\Support\Geo\GeoNameLocalizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PersonalData
 *
 * Explicit output allowlist for a personal-data card, plus its owned contacts
 * and addresses when eager-loaded.
 *
 * PRIVACY NOTE: tax_code, vat_number and birth_date are hidden on the model
 * (kept out of the activity log and default serialization) and are deliberately
 * re-exposed here so the authorized (personal_data.view) frontend can render the
 * full identity sheet. These are the most sensitive fields of the module
 * (special-category-adjacent / fiscal identifiers); their exposure is a
 * conscious decision that REQUIRES Legal sign-off (purpose, lawful basis,
 * retention, erasure) before this endpoint is released. See the handoff and ADR
 * 0006. Property access bypasses $hidden, so this projection is the re-exposure
 * point — narrow it here if Legal restricts any field.
 */
class PersonalDataResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'company_name' => $this->company_name,
            'full_name' => $this->full_name,
            'ceo' => $this->ceo,
            'tax_code' => $this->tax_code,
            'vat_number' => $this->vat_number,
            'sdi_code' => $this->sdi_code,
            'birth_date' => $this->birth_date,
            'birth_city_id' => $this->birth_city_id,
            // The comune NAME is emitted only when eager-loaded (AddressResource
            // convention): a consumer that needs no label pays no extra query.
            'birth_city' => $this->whenLoaded('birthCity', fn (): ?array => $this->birthCity !== null
                ? ['id' => $this->birthCity->id, 'name' => GeoNameLocalizer::toItalian($this->birthCity->name)]
                : null),
            'gender' => $this->gender,
            'personable_type' => $this->personable_type,
            'personable_id' => $this->personable_id,
            'contacts' => ContactResource::collection($this->whenLoaded('contacts')),
            'addresses' => AddressResource::collection($this->whenLoaded('addresses')),
            'created_at' => $this->created_at,
        ];
    }
}
