<?php

namespace App\DataObjects\FinancialAccounts;

use App\Enums\FinancialAccountType;
use Illuminate\Support\Arr;

/**
 * Validated payload for creating a financial account (POST
 * /api/financial-accounts, spec 0189). The writable columns vary by type, so
 * the payload is narrowed to FinancialAccountType::fields() here: a field of
 * another type (null, as `prohibited` lets blanks through) never persists.
 */
final readonly class CreateFinancialAccountData
{
    /**
     * @param  array<string, mixed>  $attributes  the type's writable columns
     */
    public function __construct(
        public FinancialAccountType $type,
        public array $attributes,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        $type = FinancialAccountType::from((string) $data['type']);

        return new self($type, Arr::only($data, $type->fields()));
    }
}
