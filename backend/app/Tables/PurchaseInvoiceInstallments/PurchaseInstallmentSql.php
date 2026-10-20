<?php

namespace App\Tables\PurchaseInvoiceInstallments;

use App\Tables\Support\OperationalSiteLabelSql;
use Illuminate\Support\Facades\DB;

/**
 * Every SQL fragment the `purchase-invoice-installments` table uses beyond
 * plain column references (spec 0211). They are constants of this class and
 * are the only text that reaches GROUP BY / ORDER BY / WHERE as raw SQL:
 * request values travel as bound parameters. The status mirrors
 * PurchaseInstallmentStatus exactly.
 */
final class PurchaseInstallmentSql
{
    private const string PAID = 'purchase_invoice_installments.paid_amount';

    private const string AMOUNT = 'purchase_invoice_installments.amount';

    private const string DUE_DATE = 'purchase_invoice_installments.due_date';

    /** Plain columns of the joined query by column id (qualified: the joins repeat names). */
    public const array PLAIN_COLUMNS = [
        'id' => 'purchase_invoice_installments.id',
        'document_type' => 'purchase_invoices.document_type',
        'document_number' => 'purchase_invoices.document_number',
        'document_date' => 'purchase_invoices.document_date',
        'sequence' => 'purchase_invoice_installments.sequence',
        'due_date' => self::DUE_DATE,
        'payment_method_code' => 'purchase_invoice_installments.payment_method_code',
        'amount' => self::AMOUNT,
        'paid_amount' => self::PAID,
        'residual_amount' => 'purchase_invoice_installments.residual_amount',
        'paid_at' => 'purchase_invoice_installments.paid_at',
        'supplier' => 'registries.name',
        'cost_type' => 'cost_types.name',
        'company' => 'companies.denomination',
        'company_site' => 'company_sites.name',
        'financial_account' => 'financial_accounts.name',
    ];

    /** No payment recorded. */
    public static function unpaid(): string
    {
        return 'COALESCE('.self::PAID.', 0) <= 0';
    }

    /** Any payment recorded: it closes the installment. */
    public static function paid(): string
    {
        return self::PAID.' > 0';
    }

    /** Unpaid and due before the bound date (one binding). */
    public static function overdue(): string
    {
        return '('.self::unpaid().' AND '.self::DUE_DATE.' < ?)';
    }

    /**
     * Whole days an unpaid installment is past due, 0 otherwise.
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

    /** Sort key of the derived status: unpaid, paid. */
    public static function statusRank(): string
    {
        return 'CASE WHEN '.self::unpaid().' THEN 0 ELSE 1 END';
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

    /** The operational site alias, else its primary address (see OperationalSiteLabelSql). */
    public static function operationalSiteLabel(): string
    {
        return OperationalSiteLabelSql::expression();
    }

    /**
     * The linked purchase order codes of a document as "n/year, n/year", one
     * grouped scalar per document id of $documentIdColumn (a correlated
     * subquery text, or NULL when no order is linked).
     */
    public static function purchaseOrderCodes(string $documentIdColumn): string
    {
        $joins = ' FROM purchase_invoice_purchase_order'
            .' JOIN purchase_orders ON purchase_orders.id = purchase_invoice_purchase_order.purchase_order_id'
            ." WHERE purchase_invoice_purchase_order.purchase_invoice_id = {$documentIdColumn}";

        // SQLite has no ORDER BY inside GROUP_CONCAT: the ordered subquery feeds it instead.
        if (DB::getDriverName() === 'sqlite') {
            return "(SELECT GROUP_CONCAT(code, ', ') FROM (SELECT purchase_orders.number || '/' || purchase_orders.year AS code{$joins}"
                .' ORDER BY purchase_orders.year, purchase_orders.number))';
        }

        return "(SELECT GROUP_CONCAT(CONCAT(purchase_orders.number, '/', purchase_orders.year) ORDER BY purchase_orders.year, purchase_orders.number SEPARATOR ', '){$joins})";
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
