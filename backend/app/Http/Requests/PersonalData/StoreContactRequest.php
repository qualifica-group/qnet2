<?php

namespace App\Http\Requests\PersonalData;

use App\DataObjects\PersonalData\CreateContact;
use App\Enums\ContactTypeEnum;
use App\Http\Requests\Concerns\FormatsPersonalDataInput;
use App\Http\Requests\Concerns\ResolvesOwner;
use Illuminate\Contracts\Validation\Validator;
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
     * Enforce a valid, existing owner.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validateOwner($validator));
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
