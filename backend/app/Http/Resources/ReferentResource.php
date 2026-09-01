<?php

namespace App\Http\Resources;

use App\Models\Referent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Referent
 */
class ReferentResource extends JsonResource
{
    /**
     * @param  array<string, array{visible: bool}>  $fieldPermissions
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
            'name' => $this->name,
            'referent_type_id' => $this->referent_type_id,
            // {id, name} idratazione select (ADR 0011) or null — always
            // present as a key (spec 0016 data_contract), never omitted. The
            // Service always eager-loads `referentType` for the returned
            // model, so this never triggers a lazy load.
            'referent_type' => $this->referentType !== null
                ? ['id' => $this->referentType->id, 'name' => $this->referentType->name]
                : null,
            // The referent-user link (spec 0090, D-1/D-2): `user_id` and
            // `user` are omitted TOGETHER below when not visible for the
            // actor's field permission, mirroring `referent_type_id`/
            // `referent_type` in shape but not in that omission rule (spec
            // 0090 data_contract, AC-005).
            'user_id' => $this->user_id,
            'user' => $this->user !== null
                ? ['id' => $this->user->id, 'name' => $this->user->name]
                : null,
            'contact_scope' => $this->contact_scope,
            'notes' => $this->notes,
            // The nested personal-data tree, or null — always present as a
            // key, mirroring `referent_type` above (the Service always
            // eager-loads `personalData.contacts`/`personalData.addresses`).
            'personal_data' => $this->personalData !== null
                ? new PersonalDataResource($this->personalData)
                : null,
            'created_at' => $this->created_at,
        ];

        if (($this->fieldPermissions['user_id']['visible'] ?? true) === false) {
            unset($payload['user_id'], $payload['user']);
        }

        return $payload;
    }
}
