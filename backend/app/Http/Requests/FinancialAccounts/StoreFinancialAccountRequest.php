<?php

namespace App\Http\Requests\FinancialAccounts;

use App\DataObjects\FinancialAccounts\CreateFinancialAccountData;
use App\Enums\FinancialAccountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/financial-accounts (spec 0189): `type` is
 * required and decides which fields are required/prohibited.
 */
class StoreFinancialAccountRequest extends FinancialAccountWriteRequest
{
    protected function accountType(): ?FinancialAccountType
    {
        return FinancialAccountType::tryFrom((string) $this->input('type'));
    }

    protected function isPartial(): bool
    {
        return false;
    }

    protected function ignoredAccountId(): ?int
    {
        return null;
    }

    protected function typeRule(): array
    {
        return ['type' => ['required', 'string', Rule::enum(FinancialAccountType::class)]];
    }

    protected function effectiveCardType(): ?string
    {
        return $this->input('card_type');
    }

    protected function effectiveLinkedAccountId(): mixed
    {
        return $this->input('linked_account_id');
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    public function toData(): CreateFinancialAccountData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateFinancialAccountData::fromValidated($validated);
    }
}
