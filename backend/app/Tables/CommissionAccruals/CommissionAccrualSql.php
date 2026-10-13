<?php

namespace App\Tables\CommissionAccruals;

use App\Models\CommissionAccrual;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * Every SQL fragment the `commission-accruals` table uses beyond plain column
 * references (spec 0212), plus the joins of the ledger. They are constants of
 * this class and are the only text that reaches GROUP BY / ORDER BY / WHERE as
 * raw SQL: request values travel as bound parameters.
 */
final class CommissionAccrualSql
{
    public const string STATUS_OPEN = 'open';

    public const string STATUS_SETTLED_EXTERNALLY = 'settled_externally';

    public const array STATUSES = [self::STATUS_OPEN, self::STATUS_SETTLED_EXTERNALLY];

    /** Plain columns of the joined query by column id (qualified: the joins repeat names). */
    public const array PLAIN_COLUMNS = [
        'id' => 'commission_accruals.id',
        'accrued_on' => 'commission_accruals.accrued_on',
        'recipient_kind' => 'commission_accruals.recipient_type',
        'recipient_role' => 'commission_accruals.recipient_role',
        'type' => 'commission_accruals.type',
        'customer' => 'registries.name',
        'quote' => 'quotes.code',
        'product' => 'products.name',
        'base_amount' => 'commission_accruals.base_amount',
        'amount' => 'commission_accruals.amount',
        'settled_externally_on' => 'commission_accruals.settled_externally_on',
    ];

    /**
     * The ledger's lookups, all LEFT JOINs: a movement outlives its invoice,
     * quote line and recipient (snapshot columns, nullOnDelete).
     *
     * @param  Builder<CommissionAccrual>  $query
     * @return Builder<CommissionAccrual>
     */
    public static function withJoins(Builder $query): Builder
    {
        return $query
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

    /** Derived status: open until the movement is marked settled externally. */
    public static function status(): string
    {
        return "CASE WHEN commission_accruals.settled_externally_on IS NULL THEN '".self::STATUS_OPEN."' ELSE '".self::STATUS_SETTLED_EXTERNALLY."' END";
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
            default => self::PLAIN_COLUMNS[$columnId] ?? null,
        };
    }
}
