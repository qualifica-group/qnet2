<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\GenderEnum;
use App\Enums\PersonalDataTypeEnum;
use App\Support\Fiscal\ForeignFiscalCode;
use App\Support\Fiscal\ItalianTaxCode;
use App\Support\Fiscal\ItalianVatNumber;
use App\Support\Fiscal\TaxCodeNameEncoder;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

/**
 * Validates a personal-data card's `tax_code` against the card itself: an
 * individual carries the sixteen-character personal code, a legal entity the
 * eleven-digit numeric one. On top of the control character, an individual's
 * code is checked for CONSISTENCY with the anagraphic fields submitted in the
 * same payload (surname, name, birth date, gender) — a well-formed code that
 * belongs to someone else is rejected. A foreign code, written with its
 * country prefix, is accepted on its shape alone (ForeignFiscalCode): it
 * encodes nothing to compare against the card.
 *
 * The sibling fields live under a per-payload prefix (`''` for the standalone
 * card endpoint, `personal_data.` for the entity forms, `client_identity.` for
 * the request work panel), so the same rule serves all three contracts.
 *
 * Only the fields PRESENT in the payload take part in the comparison: a sparse
 * update that carries the code alone is still checked for validity, not for a
 * consistency it has no data to establish.
 */
final class TaxCode implements DataAwareRule, ValidationRule
{
    private const string INVALID_MESSAGE = 'The tax code is not valid. A foreign one needs its country prefix (e.g. DE12345678901).';

    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(private readonly string $prefix = '') {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @param  Closure(string): void  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        if (ForeignFiscalCode::isValid($value)) {
            return;
        }

        $type = $this->cardType();

        if ($type === PersonalDataTypeEnum::Company) {
            if (! ItalianVatNumber::isValid($value)) {
                $fail(__('The tax code of a company must be 11 digits with a valid control digit, or a foreign code with its country prefix.'));
            }

            return;
        }

        // No `type` in the payload (inline cell editing sends the cell alone):
        // the card may describe either kind, so either shape is accepted —
        // guessing "individual" would reject every legal entity's code.
        if ($type === null) {
            if (! ItalianTaxCode::isValid($value) && ! ItalianVatNumber::isValid($value)) {
                $fail(__(self::INVALID_MESSAGE));
            }

            return;
        }

        if (! ItalianTaxCode::isValid($value)) {
            $fail(__(self::INVALID_MESSAGE));

            return;
        }

        $this->assertMatchesPerson($value, $fail);
    }

    /**
     * Compares the code's encoded person against the submitted one, field by
     * field, so the message names what actually diverges.
     *
     * @param  Closure(string): void  $fail
     */
    private function assertMatchesPerson(string $value, Closure $fail): void
    {
        // Step 1: the surname/name triples.
        $lastName = $this->sibling('last_name');

        if ($lastName !== null && TaxCodeNameEncoder::surname($lastName) !== ItalianTaxCode::surnameTriple($value)) {
            $fail(__('The tax code does not match the last name.'));

            return;
        }

        $firstName = $this->sibling('first_name');

        if ($firstName !== null && TaxCodeNameEncoder::name($firstName) !== ItalianTaxCode::nameTriple($value)) {
            $fail(__('The tax code does not match the first name.'));

            return;
        }

        // Step 2: the encoded birth date (the century is not encoded).
        if (! $this->birthDateMatches($value)) {
            $fail(__('The tax code does not match the birth date.'));

            return;
        }

        // Step 3: the gender, carried by the +40 offset on the birth day.
        $gender = $this->sibling('gender');

        if ($gender !== null && GenderEnum::fromValue($gender) !== $this->encodedGender($value)) {
            $fail(__('The tax code does not match the gender.'));
        }
    }

    private function birthDateMatches(string $value): bool
    {
        $birthDate = $this->sibling('birth_date');

        if ($birthDate === null) {
            return true;
        }

        $parsed = date_parse($birthDate);
        $encoded = ItalianTaxCode::birthDate($value);

        if ($encoded === null || $parsed['error_count'] > 0 || ! is_int($parsed['year'])) {
            return false;
        }

        return $encoded['year'] === $parsed['year'] % 100
            && $parsed['month'] === $encoded['month']
            && $parsed['day'] === $encoded['day'];
    }

    private function encodedGender(string $value): GenderEnum
    {
        return ItalianTaxCode::isFemale($value) ? GenderEnum::Female : GenderEnum::Male;
    }

    /** Null when the payload carries no card type at all (see validate()). */
    private function cardType(): ?PersonalDataTypeEnum
    {
        return PersonalDataTypeEnum::tryFrom((string) $this->sibling('type'));
    }

    /** The sibling field's submitted value, or null when absent or blank. */
    private function sibling(string $field): ?string
    {
        $value = Arr::get($this->data, $this->prefix.$field);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $value;
    }
}
