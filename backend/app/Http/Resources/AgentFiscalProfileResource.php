<?php

namespace App\Http\Resources;

use App\Models\AgentFiscalProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Detail projection of an AgentFiscalProfile (spec 0212). Fields the actor may
 * not see (per-field permission) are dropped from the payload.
 *
 * @mixin AgentFiscalProfile
 */
class AgentFiscalProfileResource extends JsonResource
{
    /**
     * @param  array<string, array{visible?: bool}>  $fieldPermissions
     */
    public function __construct($resource, private readonly array $fieldPermissions = [])
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = [
            'id' => $this->id,
            'referent_id' => $this->referent_id,
            'referent' => $this->referent === null ? null : ['id' => $this->referent->id, 'name' => $this->referent->name],
            'withholding_enabled' => $this->withholding_enabled,
            'withholding_rate' => $this->withholding_rate,
            'withholding_base_percent' => $this->withholding_base_percent,
            'enasarco_enabled' => $this->enasarco_enabled,
            'enasarco_mandate_type' => $this->enasarco_mandate_type?->value,
            'valid_from' => $this->valid_from?->format('Y-m-d'),
            'valid_until' => $this->valid_until?->format('Y-m-d'),
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        foreach ($this->fieldPermissions as $field => $permission) {
            if (($permission['visible'] ?? true) === true) {
                continue;
            }

            unset($payload[$field]);
        }

        return $payload;
    }
}
