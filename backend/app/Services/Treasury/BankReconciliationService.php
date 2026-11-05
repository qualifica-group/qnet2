<?php

declare(strict_types=1);

namespace App\Services\Treasury;

use App\Enums\TreasuryMovementDirection;
use App\Enums\TreasuryMovementType;
use App\Models\BankReconciliation;
use App\Models\FinancialAccount;
use App\Models\TreasuryMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Bank reconciliations of an account (spec 0363): the reconciled balance at a date
 * (D-5: opening balance + ticked rows from the opening to the date), the saved
 * "bank balance at <date>" with its difference, and the optional adjustment that
 * closes it (D-6). The balance rule is the statement's: rows come from
 * AccountStatementFacts, arithmetic in integer cents.
 */
final class BankReconciliationService
{
    public function __construct(
        private readonly AccountStatementService $statements,
        private readonly AccountStatementFacts $facts,
    ) {}

    /**
     * @return Collection<int, BankReconciliation>
     */
    public function list(FinancialAccount $account): Collection
    {
        return BankReconciliation::query()->with('creator:id,name')
            ->where('financial_account_id', $account->id)
            ->orderByDesc('statement_date')->orderByDesc('id')->get();
    }

    /**
     * @return array{statement_date: string, reconciled_balance: string, unreconciled_count: int}
     */
    public function preview(FinancialAccount $account, string $date): array
    {
        $summary = $this->summary($account, $date);

        return [
            'statement_date' => $date,
            'reconciled_balance' => AccountStatementFacts::toDecimal($summary['balance_cents']),
            'unreconciled_count' => $summary['unreconciled'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  statement_date, bank_balance, note?, create_adjustment, treasury_type_id?
     */
    public function create(FinancialAccount $account, array $data, User $actor): BankReconciliation
    {
        return DB::transaction(function () use ($account, $data, $actor): BankReconciliation {
            // Step 1: serialize with ticks and other reconciliations of the account
            FinancialAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $date = (string) $data['statement_date'];

            if (BankReconciliation::query()->where('financial_account_id', $account->id)->whereDate('statement_date', $date)->exists()) {
                throw ValidationException::withMessages(['statement_date' => [__('treasury_reconciliations.already_reconciled')]]);
            }

            // Step 2: the reconciled balance at the date and the difference from the bank
            $bankBalance = AccountStatementFacts::toDecimal(AccountStatementFacts::toCents((string) $data['bank_balance']));
            $reconciledCents = $this->summary($account, $date)['balance_cents'];
            $differenceCents = AccountStatementFacts::toCents($bankBalance) - $reconciledCents;

            // Step 3: the adjustment closing the difference, already ticked (D-6)
            $adjustment = ($data['create_adjustment'] ?? false) && $differenceCents !== 0
                ? $this->adjustment($account, $date, $differenceCents, $data['treasury_type_id'] ?? null, $actor)
                : null;

            // Step 4: persist
            return BankReconciliation::query()->create([
                'financial_account_id' => $account->id,
                'statement_date' => $date,
                'bank_balance' => $bankBalance,
                'reconciled_balance' => AccountStatementFacts::toDecimal($reconciledCents),
                'difference' => AccountStatementFacts::toDecimal($differenceCents),
                'adjustment_movement_id' => $adjustment?->id,
                'note' => $data['note'] ?? null,
                'created_by' => $actor->id,
            ])->load('creator:id,name');
        });
    }

    /** The adjustment movement stays a normal movement (D-9). */
    public function delete(BankReconciliation $reconciliation): void
    {
        DB::transaction(fn (): ?bool => $reconciliation->delete());
    }

    /**
     * @return array{balance_cents: int, unreconciled: int}
     */
    private function summary(FinancialAccount $account, string $date): array
    {
        $opening = $this->statements->openingDate($account)->toDateString();
        if ($date < $opening) {
            throw ValidationException::withMessages(['statement_date' => [__('treasury_reconciliations.before_opening', ['date' => $opening])]]);
        }

        return AccountStatementFacts::reconciledSummary(
            $this->facts->forAccount($account->id, $opening, $date),
            AccountStatementFacts::toCents((string) $account->opening_balance),
        );
    }

    private function adjustment(FinancialAccount $account, string $date, int $differenceCents, mixed $treasuryTypeId, User $actor): TreasuryMovement
    {
        if ($treasuryTypeId === null) {
            throw ValidationException::withMessages(['treasury_type_id' => [__('treasury_reconciliations.adjustment_type_required')]]);
        }

        return TreasuryMovement::query()->create([
            'type' => TreasuryMovementType::Adjustment,
            'direction' => $differenceCents > 0 ? TreasuryMovementDirection::In : TreasuryMovementDirection::Out,
            'movement_date' => $date,
            'financial_account_id' => $account->id,
            'amount' => AccountStatementFacts::toDecimal(abs($differenceCents)),
            'description' => __('treasury_reconciliations.adjustment_description'),
            'treasury_type_id' => (int) $treasuryTypeId,
            'created_by' => $actor->id,
            'reconciled_at' => now(),
            'reconciled_by' => $actor->id,
        ]);
    }
}
