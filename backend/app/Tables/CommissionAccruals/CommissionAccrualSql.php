<?php

namespace App\Tables\CommissionAccruals;

use App\Enums\CommissionSettlementStatus;
use App\Models\CommissionAccrual;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * Every SQL fragment the `commission-accruals` table uses beyond plain column
 * references (spec 0212, 0213), plus the joins of the ledger. They are
 * constants of this class and are the only text that reaches GROUP BY /
 * ORDER BY / WHERE as raw SQL: request values travel as bound parameters.
 */
final class CommissionAccrualSql
{
    public const string STATUS_OPEN = 'open';

    public const string STATUS_DRAFT = 'draft';

    public const string STATUS_ISSUED = 'issued';

    public const string STATUS_SETTLED_EXTERNALLY = 'settled_externally';

    public const array STATUSES = [self::STATUS_OPEN, self::STATUS_DRAFT, self::STATUS_ISSUED, self::STATUS_SETTLED_EXTERNALLY];

    /** What the `settlement` column shows for a movement attached to a draft (no number yet). */
    public const string SETTLEMENT_DRAFT_LABEL = 'Bozza';

    /** Plain columns of the joined query by column id (qualified: the joins repeat names). */
    public const array PLAIN_COLUMNS = [
        'id' => 'commission_accruals.id',
        'accrued_on' => 'commission_accruals.accrued_on',
        'recipient_kind' => 'commission_accruals.recipient_type',
        'recipient_role' => 'commission_accruals.recipient_role',
        'type' => 'commission_accruals.type',
        'company' => 'companies.denomination',
        'customer' => 'registries.name',
        'quote' => 'quotes.code',
        'product' => 'products.name',
        'base_amount' => 'commission_accruals.base_amount',
        'amount' => 'commission_accruals.amount',
        'settled_externally_on' => 'commission_accruals.settled_externally_on',
    ];

    /**
     * The ledger's lookups, all LEFT JOINs: a movement outlives its invoice,
     * quote line and recipient (snapshot columns, nullOnDelete), and a manual
     * adjustment has no invoice at all.
     *
     * @param  Builder<CommissionAccrual>  $query
     * @return Builder<CommissionAccrual>
     */
    public static function withJoins(Builder $query): Builder
    {
        return $query
            ->leftJoin('companies', 'companies.id', '=', 'commission_accruals.company_id')
            ->leftJoin('commission_settlements', 'commission_settlements.id', '=', 'commission_accruals.commission_settlement_id')
            ->leftJoin('invoices', 'invoices.id', '=', 'commission_accruals.invoice_id')
            ->leftJoin('registries', 'registries.id', '=', 'invoices.customer_registry_id')
            ->leftJoin('quote_lines', 'quote_lines.id', '=', 'commission_accruals.quote_line_id')
            ->leftJoin('quotes', 'quotes.id', '=', 'quote_lines.quote_id')
            ->leftJoin('products', 'products.id', '=', 'quote_lines.product_id')
            ->leftJoin('referents', fn (JoinClause $join) => $join
                ->on('referents.id', '=', 'commission_accruals.recipient_id')
                ->where('commission_accruals.recipient_type', '=', 'referent'))
            ->leftJoin('users', fn (JoinClause $join) => $join
                ->on('users.id', '=', 'commission_accruals.recipient_id')
                ->where('commission_accruals.recipient_type', '=', 'user'));
    }

    /** Name of the referent or user the movement is owed to. */
    public static function recipientName(): string
    {
        return 'COALESCE(referents.name, users.name)';
    }

    /** Group key of a recipient: the same name can belong to two people. */
    public static function recipientKey(): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "commission_accruals.recipient_type || ':' || commission_accruals.recipient_id"
            : "CONCAT(commission_accruals.recipient_type, ':', commission_accruals.recipient_id)";
    }

    /** "YYYY-MM" of the accrual date. */
    public static function accruedMonth(): string
    {
        return 'SUBSTR(commission_accruals.accrued_on, 1, 7)';
    }

    /** The invoice as "number/year". */
    public static function invoiceLabel(): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "invoices.number || '/' || invoices.year"
            : "CONCAT(invoices.number, '/', invoices.year)";
    }

    /** The settlement as "number/year", the draft label before it is numbered, null when unattached. */
    public static function settlementLabel(): string
    {
        $code = DB::getDriverName() === 'sqlite'
            ? "commission_settlements.number || '/' || commission_settlements.year"
            : "CONCAT(commission_settlements.number, '/', commission_settlements.year)";

        return 'CASE WHEN commission_settlements.id IS NULL THEN NULL'
            ." WHEN commission_settlements.number IS NULL THEN '".self::SETTLEMENT_DRAFT_LABEL."'"
            ." ELSE {$code} END";
    }

    /** SQL list of the statuses of a valid settlement (issued, document received, approved). */
    private static function validStatusList(): string
    {
        return implode(', ', array_map(static fn (CommissionSettlementStatus $status): string => "'{$status->value}'", CommissionSettlementStatus::validStatuses()));
    }

    /**
     * Derived status: settled outside the system wins, then the state of the
     * attached settlement (draft / issued), else open.
     */
    public static function status(): string
    {
        return "CASE WHEN commission_accruals.settled_externally_on IS NOT NULL THEN '".self::STATUS_SETTLED_EXTERNALLY."'"
            ." WHEN commission_settlements.status = '".CommissionSettlementStatus::Draft->value."' THEN '".self::STATUS_DRAFT."'"
            .' WHEN commission_settlements.status IN ('.self::validStatusList().") THEN '".self::STATUS_ISSUED."'"
            ." ELSE '".self::STATUS_OPEN."' END";
    }

    /**
     * Value shown for a set-filter / group column, as one SQL expression.
     */
    public static function label(string $columnId): ?string
    {
        return match ($columnId) {
            'recipient' => self::recipientName(),
            'accrued_month' => self::accruedMonth(),
            'status' => self::status(),
            'invoice' => self::invoiceLabel(),
            'settlement' => self::settlementLabel(),
            default => self::PLAIN_COLUMNS[$columnId] ?? null,
        };
    }
}
