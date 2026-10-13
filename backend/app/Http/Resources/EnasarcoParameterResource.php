<?php

namespace App\Http\Resources;

use App\Models\EnasarcoParameter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Detail projection of an EnasarcoParameter (spec 0213). Fields the actor may
 * not see (per-field permission) are dropped from the payload.
 *
 * @mixin EnasarcoParameter
 */
class EnasarcoParameterResource extends JsonResource
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
            'year' => $this->year,
            'total_rate' => $this->total_rate,
            'agent_share_percent' => $this->agent_share_percent,
            'single_firm_ceiling' => $this->single_firm_ceiling,
            'multi_firm_ceiling' => $this->multi_firm_ceiling,
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
