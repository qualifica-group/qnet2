<?php

declare(strict_types=1);

namespace App\Services\Treasury;

use App\Models\FinancialAccount;
use App\Models\TreasuryMovement;
use Illuminate\Validation\ValidationException;

/**
 * Sets the opening balance of a financial account (spec 0355, D-1). The change
 * is audited by the model's activity log (before/after). The opening date
 * cannot move past a manual movement already registered on the account.
 */
final class AccountStatementOpeningBalanceUpdater
{
    public function handle(FinancialAccount $account, string $balance, string $date): FinancialAccount
    {
        // Step 1: the new opening date must not leave a manual movement before it
        $this->assertNoMovementBefore($account, $date);

        // Step 2: persist (activity log records previous and new values)
        $account->update(['opening_balance' => $balance, 'opening_balance_date' => $date]);

        return $account->refresh();
    }

    private function assertNoMovementBefore(FinancialAccount $account, string $date): void
    {
        $exists = TreasuryMovement::query()
            ->where('movement_date', '<', $date)
            ->where(fn ($query) => $query->where('financial_account_id', $account->id)->orWhere('counterpart_account_id', $account->id))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['opening_balance_date' => [__('treasury_statements.opening_after_movement')]]);
        }
    }
}
