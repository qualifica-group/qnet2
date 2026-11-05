<?php

declare(strict_types=1);

namespace App\Services\Treasury;

use App\Enums\TreasuryMovementType;
use App\Models\FinancialAccount;
use App\Models\InvoiceInstallment;
use App\Models\PurchaseInvoiceInstallment;
use App\Models\TreasuryMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tick / untick rows of an account statement (spec 0363). Keys are statement row
 * keys (`movement:ID`, `collection:ID`, `payment:ID`); every key must be a row of
 * that account's statement or nothing changes (single transaction). A transfer is
 * ticked per side, the side being decided by the account (D-8).
 */
final class ReconciliationMarkService
{
    public function __construct(private readonly AccountStatementService $statements) {}

    /**
     * @param  array<int, string>  $keys
     * @return int rows actually changed
     */
    public function handle(FinancialAccount $account, array $keys, bool $reconciled, User $actor): int
    {
        return DB::transaction(function () use ($account, $keys, $reconciled, $actor): int {
            // Step 1: serialize with the reconciliations of the account
            FinancialAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();

            // Step 2: resolve every key to a row of this statement (422 otherwise)
            $targets = $this->resolve($account, $keys);

            // Step 3: apply the mark, counting only the rows that change
            $updated = 0;
            foreach ($targets as [$model, $attributes]) {
                $changes = $this->changes($model, $attributes, $reconciled, $actor);
                if ($changes !== []) {
                    $model->update($changes);
                    $updated++;
                }
            }

            return $updated;
        });
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<int, array{0: Model, 1: array<string, string|bool>}> model and its mark columns
     */
    private function resolve(FinancialAccount $account, array $keys): array
    {
        $opening = $this->statements->openingDate($account)->toDateString();
        $targets = [];

        foreach ($keys as $key) {
            [$source, $id] = array_pad(explode(':', $key, 2), 2, '');
            $target = ctype_digit($id) ? match ($source) {
                AccountStatementFacts::SOURCE_MOVEMENT => $this->movement($account, (int) $id, $opening),
                AccountStatementFacts::SOURCE_COLLECTION => $this->installment(InvoiceInstallment::class, 'invoice', 'collected_amount', 'collected_at', $account, (int) $id, $opening),
                AccountStatementFacts::SOURCE_PAYMENT => $this->installment(PurchaseInvoiceInstallment::class, 'purchaseInvoice', 'paid_amount', 'paid_at', $account, (int) $id, $opening),
                default => null,
            } : null;

            if ($target === null) {
                throw ValidationException::withMessages(['keys' => [__('treasury_reconciliations.mark_key_invalid', ['key' => $key])]]);
            }
            $targets[] = $target;
        }

        return $targets;
    }

    /**
     * @return array{0: Model, 1: array<string, string|bool>}|null
     */
    private function movement(FinancialAccount $account, int $id, string $opening): ?array
    {
        $movement = TreasuryMovement::query()->find($id);
        if ($movement === null || $movement->movement_date->toDateString() < $opening) {
            return null;
        }

        if ($movement->financial_account_id === $account->id) {
            return [$movement, ['at' => 'reconciled_at', 'by' => 'reconciled_by']];
        }

        $destination = $movement->type === TreasuryMovementType::Transfer && $movement->counterpart_account_id === $account->id;

        return $destination ? [$movement, ['at' => 'counterpart_reconciled_at', 'by' => 'counterpart_reconciled_by']] : null;
    }

    /**
     * @param  class-string<Model>  $class
     * @return array{0: Model, 1: array<string, string|bool>}|null
     */
    private function installment(string $class, string $document, string $amountColumn, string $dateColumn, FinancialAccount $account, int $id, string $opening): ?array
    {
        $installment = $class::query()->with($document.':id,financial_account_id')->find($id);
        if ($installment === null || $installment->{$dateColumn} === null || bccomp((string) $installment->{$amountColumn}, '0', 2) <= 0) {
            return null;
        }

        $effective = $installment->financial_account_id ?? $installment->{$document}->financial_account_id;
        if ($effective !== $account->id || $installment->{$dateColumn}->toDateString() < $opening) {
            return null;
        }

        return [$installment, ['at' => 'reconciled_at', 'by' => 'reconciled_by', 'freeze' => $installment->financial_account_id === null]];
    }

    /**
     * @param  array<string, string|bool>  $columns
     * @return array<string, mixed> attributes to write, empty when the row is already in the requested state
     */
    private function changes(Model $model, array $columns, bool $reconciled, User $actor): array
    {
        if (($model->{$columns['at']} !== null) === $reconciled) {
            return [];
        }

        $changes = $reconciled
            ? [$columns['at'] => now(), $columns['by'] => $actor->id]
            : [$columns['at'] => null, $columns['by'] => null];

        // D-7: the effective account becomes the installment's own, so changing the document account cannot move a ticked row
        if ($reconciled && ($columns['freeze'] ?? false)) {
            $changes['financial_account_id'] = $this->accountOf($model);
        }

        return $changes;
    }

    private function accountOf(Model $installment): int
    {
        $document = $installment instanceof InvoiceInstallment ? $installment->invoice : $installment->purchaseInvoice;

        return (int) $document->financial_account_id;
    }
}
