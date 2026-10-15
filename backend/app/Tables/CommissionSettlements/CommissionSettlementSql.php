<?php

namespace App\Tables\CommissionSettlements;

use App\Enums\InvoicePaymentStatus;
use App\Models\CommissionSettlement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Every SQL fragment the `commission-settlements` table uses beyond plain
 * column references (spec 0213), plus its joins. They are constants of this
 * class and the only text that reaches GROUP BY / ORDER BY / WHERE as raw SQL:
 * request values travel as bound parameters.
 */
final class CommissionSettlementSql
{
    /** Plain columns of the joined query by column id (qualified: the joins repeat names). */
    public const array PLAIN_COLUMNS = [
        'id' => 'commission_settlements.id',
        'status' => 'commission_settlements.status',
        'referent' => 'referents.name',
        'company' => 'companies.denomination',
        'period_from' => 'commission_settlements.period_from',
        'period_to' => 'commission_settlements.period_to',
        'taxable_amount' => 'commission_settlements.taxable_amount',
        'vat_amount' => 'commission_settlements.vat_amount',
        'withholding_amount' => 'commission_settlements.withholding_amount',
        'enasarco_agent_amount' => 'commission_settlements.enasarco_agent_amount',
        'enasarco_principal_amount' => 'commission_settlements.enasarco_principal_amount',
        'net_payable' => 'commission_settlements.net_payable',
        'issued_at' => 'commission_settlements.issued_at',
        'document_number' => 'commission_settlements.document_number',
    ];

    /**
     * @param  Builder<CommissionSettlement>  $query
     * @return Builder<CommissionSettlement>
     */
    public static function withJoins(Builder $query): Builder
    {
        return $query
            ->leftJoin('companies', 'companies.id', '=', 'commission_settlements.company_id')
            ->leftJoin('referents', 'referents.id', '=', 'commission_settlements.referent_id');
    }

    /** The statement number as "number/year"; NULL while it is a draft (no number yet). */
    public static function code(): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "commission_settlements.number || '/' || commission_settlements.year"
            : "CONCAT(commission_settlements.number, '/', commission_settlements.year)";
    }

    /**
     * Payment status of the linked purchase invoice (spec 0267, D-5), NULL while
     * there is none. Same rule as the purchase-invoices grid: paid when no
     * installment is left unpaid, else the state of the first unpaid one by
     * sequence (not due, overdue up to the seriously-overdue threshold, then
     * seriously overdue); no schedule means nothing due. The dates are
     * server-computed literals, never request input.
     */
    public static function paymentStatus(): string
    {
        $today = Carbon::today();
        $installments = 'FROM purchase_invoice_installments pii WHERE pii.purchase_invoice_id = commission_settlements.purchase_invoice_id';
        $firstUnpaid = "(SELECT DATE(pii.due_date) {$installments} AND (pii.paid_amount IS NULL OR pii.paid_amount <= 0) ORDER BY pii.sequence LIMIT 1)";

        return 'CASE WHEN commission_settlements.purchase_invoice_id IS NULL THEN NULL'
            ." WHEN NOT EXISTS (SELECT 1 {$installments}) THEN '".InvoicePaymentStatus::NotDue->value."'"
            ." WHEN {$firstUnpaid} IS NULL THEN '".InvoicePaymentStatus::Paid->value."'"
            ." WHEN {$firstUnpaid} >= '{$today->toDateString()}' THEN '".InvoicePaymentStatus::NotDue->value."'"
            ." WHEN {$firstUnpaid} < '{$today->copy()->subDays(InvoicePaymentStatus::SERIOUSLY_OVERDUE_DAYS)->toDateString()}' THEN '".InvoicePaymentStatus::SeriouslyOverdue->value."'"
            ." ELSE '".InvoicePaymentStatus::Overdue->value."' END";
    }

    /** "YYYY-MM" of the period end. */
    public static function periodMonth(): string
    {
        return 'SUBSTR(commission_settlements.period_to, 1, 7)';
    }

    /**
     * Value shown for a set-filter / group column, as one SQL expression.
     */
    public static function label(string $columnId): ?string
    {
        return match ($columnId) {
            'code' => self::code(),
            'period_month' => self::periodMonth(),
            'payment_status' => self::paymentStatus(),
            default => self::PLAIN_COLUMNS[$columnId] ?? null,
        };
    }
}
