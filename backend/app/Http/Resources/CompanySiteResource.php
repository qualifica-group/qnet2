<?php

namespace App\Http\Resources;

use App\Models\CompanySite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CompanySite
 *
 * Full company-site shape (spec 0020): the site's own fields plus its nested
 * personal-data card (contacts + address) via PersonalDataResource — exactly
 * like RegistryResource — and the owned banks. The former "Altro" section AND
 * the client-specific ERP settings (responsible_*, proforma/invoice
 * progressives, quotation_*) are gone: those attributes are now universal
 * custom fields (spec 0021), serialized generically via the custom-fields
 * decorator.
 */
class CompanySiteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge($this->coreFields(), $this->settingsFields());
    }

    /**
     * @return array<string, mixed>
     */
    private function coreFields(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'notes' => $this->notes,
            'is_default' => $this->is_default,
            'logo_url' => $this->logoDataUri(),
            // The nested personal-data tree (card + contacts + address), or
            // null — always present as a key (the Service always eager-loads
            // `personalData.contacts`/`personalData.addresses`), mirrors
            // RegistryResource.
            'personal_data' => $this->personalData !== null
                ? new PersonalDataResource($this->personalData)
                : null,
            'banks' => CompanySiteBankResource::collection($this->whenLoaded('banks')),
            'created_at' => $this->created_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsFields(): array
    {
        return [
            'company_id' => $this->company_id,
            'company' => $this->when(
                $this->relationLoaded('company') && $this->company !== null,
                fn (): array => ['id' => $this->company->id, 'label' => $this->company->denomination],
            ),
        ];
    }
}
