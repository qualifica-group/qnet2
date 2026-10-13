<?php

namespace App\Http\Resources;

use App\Authorization\LegacyIdVisibility;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Company
 *
 * Unlike UserResource's optional `personal_data` (present only when loaded),
 * `address` is a CORE part of the company shape and is always emitted (object
 * or null) — the caller (CompanyController/CompanyService) always eager-loads
 * the `addresses` relation (+ its geo names) before building this resource.
 */
class CompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'old_id' => $this->when(LegacyIdVisibility::allows($request->user()), $this->old_id),
            'denomination' => $this->denomination,
            'vat_number' => $this->vat_number,
            'address' => $this->primaryAddress !== null ? new CompanyAddressResource($this->primaryAddress) : null,
            'created_at' => $this->created_at,
        ];
    }
}
