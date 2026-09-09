<?php

namespace App\Http\Requests\Identity;

use App\DataObjects\Identity\IdentityDuplicateCriteria;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for POST /api/identity/duplicate-check (spec 0037,
 * extended 2026-09-09 with `vat_number`). Authorization is intentionally NOT
 * handled here (it stays in the controller: the actor must be able to create
 * an anagrafica or a referente).
 */
class CheckIdentityDuplicatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in IdentityDuplicateCheckController.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tax_code' => ['nullable', 'string', 'max:32'],
            'vat_number' => ['nullable', 'string', 'max:32'],
            'contacts' => ['nullable', 'array'],
            'contacts.*.type' => ['required', 'in:email,phone,mobile'],
            'contacts.*.value' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * At least one non-empty criterion is required — a bare empty payload
     * would otherwise return every card's identifier-less "match".
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->hasNoCriteria()) {
                $validator->errors()->add('tax_code', 'At least one of tax_code, vat_number or contacts is required.');
            }
        });
    }

    private function hasNoCriteria(): bool
    {
        $identifiers = trim((string) $this->input('tax_code', '')).trim((string) $this->input('vat_number', ''));

        $contacts = collect($this->input('contacts', []))
            ->filter(fn (mixed $contact): bool => is_array($contact) && trim((string) ($contact['value'] ?? '')) !== '');

        return $identifiers === '' && $contacts->isEmpty();
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toCriteria(): IdentityDuplicateCriteria
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return IdentityDuplicateCriteria::fromValidated($validated);
    }
}
