<?php

namespace App\Http\Requests\PersonalData;

use App\DataObjects\PersonalData\CreateContact;
use App\Enums\ContactTypeEnum;
use App\Http\Requests\Concerns\FormatsPersonalDataInput;
use App\Http\Requests\Concerns\ResolvesOwner;
use App\Models\Contact;
use App\Models\PersonalData;
use App\Support\ContactValueNormalizer;
use App\Support\IdentityUniquenessScope;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/contacts.
 *
 * The `value` is validated per-type (email/PEC must be a valid email, website a
 * valid URL, phone/fax a phone pattern). The per-type rules live on
 * ContactTypeEnum::valueRules() so they stay a single source of truth. The
 * contact is attached to a polymorphic owner (contactable_type/contactable_id)
 * resolved through the config allowlist. Authorization stays in the controller
 * via the ContactPolicy.
 */
class StoreContactRequest extends FormRequest
{
    use FormatsPersonalDataInput, ResolvesOwner;

    public function authorize(): bool
    {
        // Authorization handled in the controller via the ContactPolicy.
        return true;
    }

    /**
     * Canonicalize the typed `value` for its channel before the per-type rules
     * run (user directive 2026-07-23) — a phone typed `333 12 34 567` and one
     * typed `333-1234567` must be stored identically. Inherited by
     * UpdateContactRequest.
     */
    protected function prepareForValidation(): void
    {
        $this->formatContactInput();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->domainRules(), $this->ownerRules());
    }

    /**
     * The contact's own validation rules, owner-agnostic. Kept separate so the
     * UpdateContactRequest reuses them verbatim and the existing unit tests keep
     * validating the per-type rules in isolation.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function domainRules(): array
    {
        $type = ContactTypeEnum::tryFrom((string) $this->input('type'));

        return [
            'type' => ['required', Rule::enum(ContactTypeEnum::class)],
            'label' => ['nullable', 'string', 'max:255'],
            'value' => array_merge(
                ['required', 'string', 'max:255'],
                $type?->valueRules() ?? [],
            ),
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Enforce a valid, existing owner, then the namespace-wide phone
     * uniqueness on it.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateOwner($validator);

            if ($validator->errors()->isEmpty()) {
                $this->validateNamespacePhone($validator, $this->owner());
            }
        });
    }

    /**
     * A phone added to a card of the identity namespace must not be held by
     * any other card of it (user directive 2026-10-08): the anagrafica,
     * referente and profile details add contacts through this endpoint, and
     * without this check they were the one door the form-level gate
     * (`ValidatesPhoneUniqueness`) left open. A card outside the namespace (a
     * company site) is never checked, exactly as on the forms.
     *
     * $current is the contact under update: a number it already holds is a
     * no-op, not a new duplicate, so a legacy collision never locks the row.
     */
    protected function validateNamespacePhone(Validator $validator, ?Model $owner, ?Contact $current = null): void
    {
        if ($validator->errors()->isNotEmpty() || $this->input('type') !== ContactTypeEnum::Phone->value) {
            return;
        }

        if (! $owner instanceof PersonalData || ! IdentityUniquenessScope::covers($owner->personable_type)) {
            return;
        }

        $normalized = ContactValueNormalizer::contact(ContactTypeEnum::Phone, (string) $this->input('value'));
        $unchanged = $current !== null && $current->type === ContactTypeEnum::Phone && $current->normalized_value === $normalized;

        if ($normalized === '' || $unchanged) {
            return;
        }

        if (IdentityUniquenessScope::phoneTaken(IdentityUniquenessScope::cards()->whereKeyNot($owner->getKey()), $normalized)) {
            $validator->errors()->add('value', __('The phone number is already assigned to another record.'));
        }
    }

    protected function ownerConfigKey(): string
    {
        return 'personal_data.contactable_types';
    }

    protected function ownerTypeField(): string
    {
        return 'contactable_type';
    }

    protected function ownerIdField(): string
    {
        return 'contactable_id';
    }

    /**
     * The validated payload as a typed DTO.
     */
    public function toData(): CreateContact
    {
        return new CreateContact(
            type: ContactTypeEnum::from($this->string('type')->toString()),
            value: $this->string('value')->toString(),
            label: $this->input('label'),
            isPrimary: $this->boolean('is_primary'),
        );
    }
}
