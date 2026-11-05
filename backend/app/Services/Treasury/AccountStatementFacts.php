<?php

declare(strict_types=1);

namespace App\Services\Treasury;

use App\Enums\TreasuryMovementDirection;
use App\Enums\TreasuryMovementType;
use App\Models\TreasuryMovement;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Reads the three sources of an account's cash facts (spec 0360): manual
 * movements, collections of invoice installments and payments of purchase
 * installments. Every fact carries a signed amount in integer cents. The
 * account of an installment is its own, else the document's (D-5).
 */
final class AccountStatementFacts
{
    public const string SOURCE_MOVEMENT = 'movement';

    public const string SOURCE_COLLECTION = 'collection';

    public const string SOURCE_PAYMENT = 'payment';

    /** Tie-break order of facts of the same day. */
    public const array SOURCE_ORDER = [self::SOURCE_MOVEMENT => 0, self::SOURCE_COLLECTION => 1, self::SOURCE_PAYMENT => 2];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forAccount(int $accountId, string $from, string $to): array
    {
        return [
            ...$this->movements($accountId, $from, $to),
            ...$this->installments(self::SOURCE_COLLECTION, $accountId, $from, $to),
            ...$this->installments(self::SOURCE_PAYMENT, $accountId, $from, $to),
        ];
    }

    /**
     * Collected/paid installments with no account on the installment nor on the document (D-4).
     *
     * @return array<int, array<string, mixed>>
     */
    public function unassigned(string $from, ?int $companyId): array
    {
        $facts = [];
        foreach ([self::SOURCE_COLLECTION, self::SOURCE_PAYMENT] as $source) {
            $installment = $this->installmentAlias($source);
            $document = $this->documentAlias($source);
            $query = $this->installmentQuery($source)
                ->where($this->dateColumn($source), '>=', $from)
                ->whereNull("{$installment}.financial_account_id")
                ->whereNull("{$document}.financial_account_id");
            if ($companyId !== null) {
                $query->where("{$document}.company_id", $companyId);
            }
            foreach ($query->get() as $row) {
                $facts[] = $this->installmentFact($source, $row);
            }
        }

        return $facts;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function movements(int $accountId, string $from, string $to): array
    {
        $movements = TreasuryMovement::query()
            ->with(['registry:id,name', 'workOrder:id,code'])
            ->whereDate('movement_date', '>=', $from)->whereDate('movement_date', '<=', $to)
            ->where(function ($query) use ($accountId): void {
                $query->where('financial_account_id', $accountId)
                    ->orWhere(fn ($transfer) => $transfer->where('type', TreasuryMovementType::Transfer->value)->where('counterpart_account_id', $accountId));
            })
            ->get();

        return $movements->map(fn (TreasuryMovement $movement): array => [
            'source' => self::SOURCE_MOVEMENT,
            'id' => $movement->id,
            'date' => $movement->movement_date->toDateString(),
            'type' => $this->movementType($movement),
            'description' => $movement->description,
            'registry' => $movement->registry === null ? null : ['id' => $movement->registry->id, 'name' => $movement->registry->name],
            'document' => null,
            'work_order' => $movement->workOrder === null ? null : ['id' => $movement->workOrder->id, 'code' => $movement->workOrder->code],
            'cents' => $this->movementCents($movement, $accountId),
            'movement_id' => $movement->id,
            'reconciled' => $this->movementSideReconciled($movement, $accountId),
        ])->all();
    }

    /** A transfer is ticked per side: the origin on `reconciled_at`, the destination on `counterpart_reconciled_at` (D-8). */
    private function movementSideReconciled(TreasuryMovement $movement, int $accountId): bool
    {
        $destination = $movement->type === TreasuryMovementType::Transfer && $movement->counterpart_account_id === $accountId;

        return ($destination ? $movement->counterpart_reconciled_at : $movement->reconciled_at) !== null;
    }

    private function movementType(TreasuryMovement $movement): string
    {
        return match ($movement->type) {
            TreasuryMovementType::Income => 'manual_income',
            TreasuryMovementType::Expense => 'manual_expense',
            TreasuryMovementType::Transfer => 'transfer',
            TreasuryMovementType::Adjustment => 'adjustment',
        };
    }

    private function movementCents(TreasuryMovement $movement, int $accountId): int
    {
        $cents = self::toCents((string) $movement->amount);
        $positive = match ($movement->type) {
            TreasuryMovementType::Income => true,
            TreasuryMovementType::Expense => false,
            TreasuryMovementType::Transfer => $movement->counterpart_account_id === $accountId,
            TreasuryMovementType::Adjustment => $movement->direction === TreasuryMovementDirection::In,
        };

        return $positive ? $cents : -$cents;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function installments(string $source, int $accountId, string $from, string $to): array
    {
        $installment = $this->installmentAlias($source);
        $document = $this->documentAlias($source);
        $query = $this->installmentQuery($source)
            ->whereDate($this->dateColumn($source), '>=', $from)->whereDate($this->dateColumn($source), '<=', $to)
            ->where(function (Builder $query) use ($installment, $document, $accountId): void {
                $query->where("{$installment}.financial_account_id", $accountId)
                    ->orWhere(fn (Builder $inherited) => $inherited
                        ->whereNull("{$installment}.financial_account_id")
                        ->where("{$document}.financial_account_id", $accountId));
            });

        return $query->get()->map(fn (object $row): array => $this->installmentFact($source, $row))->all();
    }

    private function installmentQuery(string $source): Builder
    {
        if ($source === self::SOURCE_COLLECTION) {
            return DB::table('invoice_installments as ii')
                ->join('invoices as doc', 'doc.id', '=', 'ii.invoice_id')
                ->leftJoin('registries as reg', 'reg.id', '=', 'doc.customer_registry_id')
                ->leftJoin('work_orders as wo', 'wo.id', '=', 'doc.work_order_id')
                ->whereNotNull('ii.collected_at')->where('ii.collected_amount', '>', 0)->whereNull('ii.compensation_id')
                ->select(['ii.id', 'ii.collected_amount as amount', 'ii.collected_at as at', 'ii.reconciled_at', 'doc.id as doc_id', 'doc.number', 'doc.year',
                    'reg.id as reg_id', 'reg.name as reg_name', 'wo.id as wo_id', 'wo.code as wo_code']);
        }

        return DB::table('purchase_invoice_installments as pi')
            ->join('purchase_invoices as pdoc', 'pdoc.id', '=', 'pi.purchase_invoice_id')
            ->leftJoin('registries as reg', 'reg.id', '=', 'pdoc.supplier_id')
            ->leftJoin('work_orders as wo', 'wo.id', '=', 'pdoc.work_order_id')
            ->whereNotNull('pi.paid_at')->where('pi.paid_amount', '>', 0)->whereNull('pi.compensation_id')
            ->select(['pi.id', 'pi.paid_amount as amount', 'pi.paid_at as at', 'pi.reconciled_at', 'pdoc.id as doc_id', 'pdoc.document_number as number',
                'reg.id as reg_id', 'reg.name as reg_name', 'wo.id as wo_id', 'wo.code as wo_code']);
    }

    private function installmentAlias(string $source): string
    {
        return $source === self::SOURCE_COLLECTION ? 'ii' : 'pi';
    }

    private function documentAlias(string $source): string
    {
        return $source === self::SOURCE_COLLECTION ? 'doc' : 'pdoc';
    }

    private function dateColumn(string $source): string
    {
        return $source === self::SOURCE_COLLECTION ? 'ii.collected_at' : 'pi.paid_at';
    }

    /**
     * @return array<string, mixed>
     */
    private function installmentFact(string $source, object $row): array
    {
        $collection = $source === self::SOURCE_COLLECTION;
        $number = $collection ? "{$row->number}/{$row->year}" : $row->number;
        $cents = self::toCents((string) $row->amount);

        return [
            'source' => $source,
            'id' => (int) $row->id,
            'date' => substr((string) $row->at, 0, 10),
            'type' => $source,
            'description' => $number ?? '',
            'registry' => $row->reg_id === null ? null : ['id' => (int) $row->reg_id, 'name' => $row->reg_name],
            'document' => ['kind' => $collection ? 'invoice' : 'purchase_invoice', 'id' => (int) $row->doc_id, 'number' => $number],
            'work_order' => $row->wo_id === null ? null : ['id' => (int) $row->wo_id, 'code' => $row->wo_code],
            'cents' => $collection ? $cents : -$cents,
            'movement_id' => null,
            'reconciled' => $row->reconciled_at !== null,
        ];
    }

    /**
     * Reconciled balance (D-5: the opening balance counts as reconciled) and number of
     * unreconciled rows of the facts from the opening date to the end of the period.
     *
     * @param  array<int, array<string, mixed>>  $facts
     * @return array{balance_cents: int, unreconciled: int}
     */
    public static function reconciledSummary(array $facts, int $openingCents): array
    {
        $balance = $openingCents;
        $unreconciled = 0;
        foreach ($facts as $fact) {
            $fact['reconciled'] ? $balance += $fact['cents'] : $unreconciled++;
        }

        return ['balance_cents' => $balance, 'unreconciled' => $unreconciled];
    }

    /** Decimal string ("1234.56", "-5.1") to integer cents, without floats. */
    public static function toCents(string $value): int
    {
        $negative = str_starts_with($value, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($value, '-+'), 2), 2, '');
        $cents = ((int) $whole) * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return $negative ? -$cents : $cents;
    }

    public static function toDecimal(int $cents): string
    {
        return ($cents < 0 ? '-' : '').intdiv(abs($cents), 100).'.'.str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }
}
