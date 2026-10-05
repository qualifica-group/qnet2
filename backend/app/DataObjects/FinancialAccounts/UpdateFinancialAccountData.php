<?php

namespace App\DataObjects\FinancialAccounts;

use App\Enums\FinancialAccountType;
use Illuminate\Support\Arr;

/**
 * Validated payload for a partial update of a financial account (PUT/PATCH
 * /api/financial-accounts/{financialAccount}, spec 0189): only the submitted
 * fields of the account's own (immutable) type. A blank `card_number` means
 * "keep the stored one" and is dropped.
 */
final readonly class UpdateFinancialAccountData
{
    /**
     * @param  array<string, mixed>  $attributes  the submitted writable columns
     */
    public function __construct(public array $attributes) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data, FinancialAccountType $type): self
    {
        $attributes = Arr::only($data, $type->fields());

        if (($attributes['card_number'] ?? null) === null) {
            unset($attributes['card_number']);
        }

        return new self($attributes);
    }
}
