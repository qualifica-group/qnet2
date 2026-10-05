<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Kind of financial account (spec 0189, D-7): the discriminator stored in
 * financial_accounts.type. It also owns the per-type write surface, so the
 * FormRequests (prohibited fields) and the DTOs (what is persisted) share one
 * source of truth.
 */
enum FinancialAccountType: string
{
    use HasMeta;

    /** Fields every type accepts. */
    public const array COMMON_FIELDS = ['name', 'company_id', 'notes'];

    public const array BANK_FIELDS = ['iban', 'account_number'];

    public const array ADDRESS_FIELDS = ['address_line', 'postal_code', 'country_id', 'state_id', 'province_id', 'city_id'];

    public const array CARD_FIELDS = ['card_type', 'card_circuit', 'linked_account_id', 'card_holder', 'card_number', 'card_expiry'];

    #[Label('Bank account')]
    case BankAccount = 'bank_account';

    #[Label('Card')]
    case Card = 'card';

    #[Label('Cash')]
    case Cash = 'cash';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * The writable fields of this type (without `type` itself).
     *
     * @return array<int, string>
     */
    public function fields(): array
    {
        return match ($this) {
            self::BankAccount => [...self::COMMON_FIELDS, ...self::BANK_FIELDS, ...self::ADDRESS_FIELDS],
            self::Card => [...self::COMMON_FIELDS, ...self::CARD_FIELDS],
            self::Cash => [...self::COMMON_FIELDS, ...self::ADDRESS_FIELDS],
        };
    }

    /**
     * The writable fields that belong to OTHER types only: they must be absent
     * from a payload of this type.
     *
     * @return array<int, string>
     */
    public function foreignFields(): array
    {
        $all = [...self::COMMON_FIELDS, ...self::BANK_FIELDS, ...self::ADDRESS_FIELDS, ...self::CARD_FIELDS];

        return array_values(array_diff($all, $this->fields()));
    }
}
