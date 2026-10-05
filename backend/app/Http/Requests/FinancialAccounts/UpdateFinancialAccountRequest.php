<?php

namespace App\Http\Requests\FinancialAccounts;

use App\DataObjects\FinancialAccounts\UpdateFinancialAccountData;
use App\Enums\FinancialAccountType;
use App\Models\FinancialAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/financial-accounts/{financialAccount}
 * (spec 0189). The type is immutable (D-7): it is taken from the stored row, and
 * a different `type` in the payload is a 422. Every field is partial-friendly.
 */
class UpdateFinancialAccountRequest extends FinancialAccountWriteRequest
{
    private function account(): FinancialAccount
    {
        /** @var FinancialAccount $account */
        $account = $this->route('financialAccount');

        return $account;
    }

    protected function accountType(): ?FinancialAccountType
    {
        return $this->account()->type;
    }

    protected function isPartial(): bool
    {
        return true;
    }

    protected function ignoredAccountId(): ?int
    {
        return $this->account()->id;
    }

    protected function typeRule(): array
    {
        return ['type' => ['sometimes', Rule::in([$this->account()->type->value])]];
    }

    protected function effectiveCardType(): ?string
    {
        return $this->has('card_type') ? $this->input('card_type') : $this->account()->card_type?->value;
    }

    protected function effectiveLinkedAccountId(): mixed
    {
        return $this->has('linked_account_id') ? $this->input('linked_account_id') : $this->account()->linked_account_id;
    }

    protected function authorizationModel(): ?Model
    {
        return $this->account();
    }

    public function toData(): UpdateFinancialAccountData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateFinancialAccountData::fromValidated($validated, $this->account()->type);
    }
}
