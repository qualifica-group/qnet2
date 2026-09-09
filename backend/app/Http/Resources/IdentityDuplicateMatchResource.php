<?php

namespace App\Http\Resources;

use App\DataObjects\Identity\IdentityDuplicateMatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wire shape of a single IdentityDuplicateFinder match: owner morph alias +
 * id, display name and matched_on only — no contact value, tax code or VAT
 * number ever leaves the Service (spec 0037 AC-005: no PII of another record
 * in the response).
 *
 * @mixin IdentityDuplicateMatch
 */
class IdentityDuplicateMatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'owner_type' => $this->ownerType,
            'owner_id' => $this->ownerId,
            'name' => $this->name,
            'matched_on' => $this->matchedOn,
        ];
    }
}
