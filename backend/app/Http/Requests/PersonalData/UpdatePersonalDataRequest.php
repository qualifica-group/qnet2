<?php

namespace App\Http\Requests\PersonalData;

use App\Models\PersonalData;
use Illuminate\Contracts\Validation\Validator;

/**
 * Validates the payload for PUT/PATCH /api/personal-data/{personalData}.
 *
 * Reuses StorePersonalDataRequest's per-type domain rules verbatim but drops the
 * owner: a card is never re-parented through an update, so the owner fields are
 * neither accepted nor re-validated. Update is a full replacement of the card's
 * attributes (the service overwrites every column), so the same required/per-type
 * rules apply. Authorization stays in the controller via the PersonalDataPolicy.
 */
class UpdatePersonalDataRequest extends StorePersonalDataRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->domainRules();
    }

    /**
     * No owner validation on update (the owner is immutable); the fiscal
     * uniqueness runs against the owner the card already has.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var PersonalData $card */
            $card = $this->route('personalData');

            $this->validateNamespaceFiscalIdentity($validator, $card->personable);
        });
    }
}
