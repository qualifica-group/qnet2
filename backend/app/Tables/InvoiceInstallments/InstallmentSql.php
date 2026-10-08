<?php

namespace App\Tables\InvoiceInstallments;

use Illuminate\Support\Facades\DB;

/**
 * Every SQL fragment the `invoice-installments` table uses beyond plain column
 * references (spec 0197). They are constants of this class and are the only
 * text that reaches GROUP BY / ORDER BY / WHERE as raw SQL: request values
 * travel as bound parameters. The status mirrors InstallmentStatus exactly.
 */
final class InstallmentSql
{
    private const string COLLECTED = 'invoice_installments.collected_amount';

    private const string AMOUNT = 'invoice_installments.amount';

    private const string DUE_DATE = 'invoice_installments.due_date';

    /** Plain columns of the joined query by column id (qualified: the joins repeat names). */
    public const array PLAIN_COLUMNS = [
        'id' => 'invoice_installments.id',
        'invoice_document_date' => 'invoices.document_date',
        'sequence' => 'invoice_installments.sequence',
        'due_date' => self::DUE_DATE,
        'payment_method_code' => 'invoice_installments.payment_method_code',
        'amount' => self::AMOUNT,
        'collected_amount' => self::COLLECTED,
        'residual_amount' => 'invoice_installments.residual_amount',
        'collected_at' => 'invoice_installments.collected_at',
        'customer' => 'registries.name',
        'company' => 'companies.denomination',
        'company_site' => 'company_sites.name',
    ];

    /** No collection recorded. */
    public static function unpaid(): string
    {
        return 'COALESCE('.self::COLLECTED.', 0) <= 0';
    }

    /** Some collection, below the amount. */
    public static function partiallyPaid(): string
    {
        return self::COLLECTED.' > 0 AND '.self::COLLECTED.' < '.self::AMOUNT;
    }

    /** Collected up to or beyond the amount. */
    public static function paid(): string
    {
        return self::COLLECTED.' > 0 AND '.self::COLLECTED.' >= '.self::AMOUNT;
    }

    /** Unpaid or partially paid. */
    public static function open(): string
    {
        return '('.self::unpaid().' OR '.self::COLLECTED.' < '.self::AMOUNT.')';
    }

    /** Open and due before the bound date (one binding). */
    public static function overdue(): string
    {
        return '('.self::open().' AND '.self::DUE_DATE.' < ?)';
    }

    /**
     * Whole days an open installment is past due, 0 otherwise.
     *
     * @return array{0: string, 1: int} expression and the number of bindings (all the same date)
     */
    public static function daysOverdue(): array
    {
        $difference = DB::getDriverName() === 'sqlite'
            ? 'CAST(julianday(?) - julianday('.self::DUE_DATE.') AS INTEGER)'
            : 'DATEDIFF(?, '.self::DUE_DATE.')';

        return ['CASE WHEN '.self::overdue().' THEN '.$difference.' ELSE 0 END', 2];
    }

    /** Sort key of the derived status: unpaid, partially paid, paid. */
    public static function statusRank(): string
    {
        return 'CASE WHEN '.self::unpaid().' THEN 0 WHEN '.self::paid().' THEN 2 ELSE 1 END';
    }

    /** "YYYY-MM" of the due date. */
    public static function dueMonth(): string
    {
        return 'SUBSTR('.self::DUE_DATE.', 1, 7)';
    }

    /** The work order as "CODE - title". */
    public static function workOrderLabel(): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "work_orders.code || ' - ' || work_orders.title"
            : "CONCAT(work_orders.code, ' - ', work_orders.title)";
    }

    /** The operational site alias, falling back to its id when it has none. */
    public static function operationalSiteLabel(): string
    {
        return 'COALESCE(operational_sites.alias, CAST(operational_sites.id AS CHAR))';
    }

    /**
     * Value shown for a set-filter / group column, as one SQL expression.
     */
    public static function label(string $columnId): ?string
    {
        return match ($columnId) {
            'work_order' => self::workOrderLabel(),
            'operational_site' => self::operationalSiteLabel(),
            'due_month' => self::dueMonth(),
            default => self::PLAIN_COLUMNS[$columnId] ?? null,
        };
    }
}
