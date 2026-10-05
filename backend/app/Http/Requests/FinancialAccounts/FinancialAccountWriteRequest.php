<?php

namespace App\Http\Requests\FinancialAccounts;

use App\Enums\FinancialAccountType;
use App\Enums\FinancialCardCircuit;
use App\Enums\FinancialCardType;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Rules\ValidCardNumber;
use App\Rules\ValidIban;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared validation of the financial-account write payload (spec 0189): the
 * rules depend on the account type, and every field that belongs to another
 * type is `prohibited`. Subclasses only decide the type, whether the payload is
 * partial (PATCH) and the row ignored by the IBAN uniqueness.
 *
 * Authorization is NOT handled here (it stays in the controller via the
 * Policy). The CVV/PIN of a card are never accepted: any such key is rejected.
 */
abstract class FinancialAccountWriteRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    private const int TEXT_MAX = 255;

    private const int ACCOUNT_NUMBER_MAX = 50;

    private const int POSTAL_CODE_MAX = 10;

    private const string EXPIRY_PATTERN = '/^(0[1-9]|1[0-2])\/\d{4}$/';

    /** Card data that must never reach the database (D-1). */
    private const array FORBIDDEN_CARD_SECRETS = ['cvv', 'card_ccv', 'card_password'];

    public function authorize(): bool
    {
        return true;
    }

    /** The account type the rules are built for; null when it cannot be resolved. */
    abstract protected function accountType(): ?FinancialAccountType;

    /** Whether absent fields are tolerated (update) instead of required (store). */
    abstract protected function isPartial(): bool;

    /** The id the IBAN uniqueness ignores (the row being updated). */
    abstract protected function ignoredAccountId(): ?int;

    /**
     * @return array<string, array<int, mixed>>
     */
    abstract protected function typeRule(): array;

    /** The card type after this write (the payload's, else the stored one). */
    abstract protected function effectiveCardType(): ?string;

    /** The linked account id after this write (the payload's, else the stored one). */
    abstract protected function effectiveLinkedAccountId(): mixed;

    /**
     * Uppercase/space-free IBAN and digits-only card number are the canonical
     * forms validated, compared for uniqueness and stored.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        if (is_string($this->input('iban'))) {
            $normalized['iban'] = ValidIban::normalize($this->input('iban'));
        }

        if (is_string($this->input('card_number'))) {
            $normalized['card_number'] = ValidCardNumber::normalize($this->input('card_number'));
        }

        $this->merge($normalized);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $type = $this->accountType();
        $rules = $this->typeRule();

        if ($type === null) {
            return $rules;
        }

        $fieldRules = $this->fieldRules($type);

        foreach ([...$type->foreignFields(), ...self::FORBIDDEN_CARD_SECRETS] as $field) {
            $fieldRules[$field] = ['prohibited'];
        }

        return $rules + $fieldRules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
            $this->assertCreditCardIsLinked($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'financial-accounts';
    }

    /**
     * A credit card must end up associated with a bank account (D-2), also on
     * a partial update that omits one of the two fields.
     */
    private function assertCreditCardIsLinked(Validator $validator): void
    {
        if ($this->accountType() !== FinancialAccountType::Card || $validator->errors()->hasAny(['card_type', 'linked_account_id'])) {
            return;
        }

        if ($this->effectiveCardType() === FinancialCardType::Credit->value && $this->effectiveLinkedAccountId() === null) {
            $validator->errors()->add('linked_account_id', __('validation.required', ['attribute' => 'linked account id']));
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function fieldRules(FinancialAccountType $type): array
    {
        $rules = $this->commonRules() + $this->addressRules() + $this->bankRules() + $this->cardRules();

        return array_intersect_key($rules, array_flip($type->fields()));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function commonRules(): array
    {
        return [
            'name' => $this->required(['string', 'max:'.self::TEXT_MAX]),
            'company_id' => ['sometimes', 'nullable', 'integer', Rule::exists('companies', 'id')],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function addressRules(): array
    {
        return [
            'address_line' => ['sometimes', 'nullable', 'string', 'max:'.self::TEXT_MAX],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:'.self::POSTAL_CODE_MAX],
            'country_id' => ['sometimes', 'nullable', 'integer', Rule::exists('countries', 'id')],
            'state_id' => ['sometimes', 'nullable', 'integer', Rule::exists('states', 'id')],
            'province_id' => ['sometimes', 'nullable', 'integer', Rule::exists('provinces', 'id')],
            'city_id' => ['sometimes', 'nullable', 'integer', Rule::exists('cities', 'id')],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function bankRules(): array
    {
        return [
            'iban' => $this->required(['string', new ValidIban, Rule::unique('financial_accounts', 'iban')->ignore($this->ignoredAccountId())]),
            'account_number' => $this->required(['string', 'max:'.self::ACCOUNT_NUMBER_MAX]),
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function cardRules(): array
    {
        $bankAccountOnly = Rule::exists('financial_accounts', 'id')->where('type', FinancialAccountType::BankAccount->value);

        return [
            'card_type' => $this->required([Rule::enum(FinancialCardType::class)]),
            'card_circuit' => $this->required([Rule::enum(FinancialCardCircuit::class)]),
            'linked_account_id' => ['sometimes', 'nullable', 'integer', $bankAccountOnly],
            'card_holder' => $this->required(['string', 'max:'.self::TEXT_MAX]),
            // Optional on update: absent (or blank) keeps the stored number.
            'card_number' => $this->isPartial()
                ? ['sometimes', 'nullable', 'string', new ValidCardNumber]
                : ['required', 'string', new ValidCardNumber],
            'card_expiry' => $this->required(['string', 'regex:'.self::EXPIRY_PATTERN]),
        ];
    }

    /**
     * @param  array<int, mixed>  $rules
     * @return array<int, mixed>
     */
    private function required(array $rules): array
    {
        return $this->isPartial() ? ['sometimes', 'required', ...$rules] : ['required', ...$rules];
    }
}
