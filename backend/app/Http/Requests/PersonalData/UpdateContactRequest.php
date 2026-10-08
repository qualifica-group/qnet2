<?php

namespace App\Http\Requests\PersonalData;

use App\Models\Contact;
use Illuminate\Contracts\Validation\Validator;

/**
 * Validates the payload for PUT/PATCH /api/contacts/{contact}.
 *
 * Reuses StoreContactRequest's per-type domain rules verbatim but drops the
 * owner: a contact is never re-parented through an update. Update is a full
 * replacement of the contact's attributes. Authorization stays in the controller
 * via the ContactPolicy.
 */
class UpdateContactRequest extends StoreContactRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->domainRules();
    }

    /**
     * No owner validation on update (the owner is immutable); the phone
     * uniqueness runs against the card the contact already belongs to.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Contact $contact */
            $contact = $this->route('contact');

            $this->validateNamespacePhone($validator, $contact->contactable, $contact);
        });
    }
}
