<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\DataObjects\PersonalData\CreatePersonalData;
use App\DataObjects\Users\ContactInput;
use App\Enums\ContactTypeEnum;
use App\Models\PersonalData;
use App\Models\Registry;
use App\Rules\UniquePersonalDataIdentifier;
use App\Support\ContactValueNormalizer;
use App\Support\IdentityUniquenessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * The identity-uniqueness gate on the EXISTING client a request edits (user
 * directive 2026-10-08): the work panel and the grid's inline cells both write
 * the client's codice fiscale, partita IVA and phone onto its anagrafica, and
 * both must refuse a value another user, anagrafica or referente already holds
 * — the same constraint the anagrafica form and the request create form apply.
 *
 * Lives on `RequestClientProfileWriter`, the one place both channels reach,
 * rather than on a FormRequest: the inline cell's validation is column-driven
 * and knows nothing about the row's client.
 *
 * Only a value the card does NOT already hold is checked. A legacy duplicate
 * already stored on the card must not lock the operator out of saving the
 * rest of the panel; what is refused is introducing a new one.
 */
final class RequestClientUniquenessGuard
{
    /** Inline single-field key -> fiscal column it writes. */
    private const array FIELD_FISCAL_COLUMNS = [
        'client_tax_code' => 'tax_code',
        'client_vat_number' => 'vat_number',
    ];

    private const string FIELD_PHONE_KEY = 'client_phone';

    private const string PHONE_TAKEN_MESSAGE = 'The phone number is already assigned to another record.';

    /**
     * The inline channel: one cell, keyed by its payload key.
     *
     * @throws ValidationException
     */
    public function assertFieldFree(Registry $registry, PersonalData $card, string $key, ?string $value): void
    {
        $message = match (true) {
            isset(self::FIELD_FISCAL_COLUMNS[$key]) => $this->fiscalConflict($registry, $card, self::FIELD_FISCAL_COLUMNS[$key], $value),
            $key === self::FIELD_PHONE_KEY => $this->phoneConflict($registry, $card, $value),
            default => null,
        };

        if ($message !== null) {
            throw ValidationException::withMessages([$key => $message]);
        }
    }

    /**
     * The work-panel channel: the identity card and the full contact set, with
     * errors keyed exactly as the create form's FormRequest keys them.
     *
     * @param  array<int, ContactInput>|null  $contacts
     *
     * @throws ValidationException
     */
    public function assertBlocksFree(Registry $registry, ?PersonalData $card, ?CreatePersonalData $identity, ?array $contacts): void
    {
        $errors = [];

        if ($identity !== null) {
            $submitted = ['tax_code' => $identity->taxCode, 'vat_number' => $identity->vatNumber];

            foreach (IdentityUniquenessScope::FISCAL_COLUMNS as $column) {
                $message = $this->fiscalConflict($registry, $card, $column, $submitted[$column]);

                if ($message !== null) {
                    $errors["client_identity.{$column}"] = $message;
                }
            }
        }

        foreach ($contacts ?? [] as $index => $contact) {
            if ($contact->data->type !== ContactTypeEnum::Phone) {
                continue;
            }

            $message = $this->phoneConflict($registry, $card, $contact->data->value);

            if ($message !== null) {
                $errors["client_contacts.{$index}.value"] = $message;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function fiscalConflict(Registry $registry, ?PersonalData $card, string $column, ?string $value): ?string
    {
        $normalized = ContactValueNormalizer::taxCode((string) $value);

        if ($normalized === '' || ($card !== null && ContactValueNormalizer::taxCode((string) $card->{$column}) === $normalized)) {
            return null;
        }

        $taken = IdentityUniquenessScope::withFiscalIdentifier($this->otherCards($registry), $normalized)->exists();

        return $taken ? __(UniquePersonalDataIdentifier::MESSAGES[$column]) : null;
    }

    private function phoneConflict(Registry $registry, ?PersonalData $card, ?string $value): ?string
    {
        $normalized = ContactValueNormalizer::contact(ContactTypeEnum::Phone, (string) $value);

        if ($normalized === '' || $this->cardHoldsPhone($card, $normalized)) {
            return null;
        }

        return IdentityUniquenessScope::phoneTaken($this->otherCards($registry), $normalized)
            ? __(self::PHONE_TAKEN_MESSAGE)
            : null;
    }

    private function cardHoldsPhone(?PersonalData $card, string $normalized): bool
    {
        return $card !== null && $card->contacts()
            ->where('type', ContactTypeEnum::Phone->value)
            ->where('normalized_value', $normalized)
            ->exists();
    }

    /**
     * @return Builder<PersonalData>
     */
    private function otherCards(Registry $registry): Builder
    {
        return IdentityUniquenessScope::cards(Registry::class, $registry->id);
    }
}
