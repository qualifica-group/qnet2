<?php

declare(strict_types=1);

use App\Enums\CommissionRecipientRole;
use App\Enums\InvoiceType;
use App\Enums\PurchaseInvoiceType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\QuoteLineType;
use App\Enums\SupplierCommissionDirection;
use App\Models\BusinessFunction;
use App\Models\CommissionAccrual;
use App\Models\CommissionSettlement;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\FinancialAccount;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\InvoiceLine;
use App\Models\OperationalSite;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use App\Models\PurchaseInvoiceLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteLineCommission;
use App\Models\Referent;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderCost;
use App\Services\Invoices\InvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

// Shared fixtures of the management control tests (spec 0268). Each test file
// loads this file with require_once: the helpers are not in tests/Pest.php.

if (! function_exists('mcActor')) {
    /** A user holding `management-control.view` and nothing else. */
    function mcActor(bool $withPermission = true): User
    {
        Permission::findOrCreate('management-control.view', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user = User::factory()->create();

        return $withPermission ? $user->givePermissionTo('management-control.view') : $user;
    }
}

if (! function_exists('mcGet')) {
    /**
     * GET a management control endpoint; the aggregate cache is emptied first so
     * a test that changes data between two calls never reads a stale payload.
     *
     * @param  array<string, mixed>  $params
     */
    function mcGet(string $endpoint, array $params = []): TestResponse
    {
        Cache::flush();

        return test()->getJson("/api/management-control/{$endpoint}".($params === [] ? '' : '?'.http_build_query($params)));
    }
}

if (! function_exists('mcWorkOrder')) {
    /**
     * A commessa of its own offer.
     *
     * @param  array<string, mixed>  $attributes  work order attributes
     * @param  array<string, mixed>  $quote  offer attributes (company_id, commercial_id...)
     */
    function mcWorkOrder(array $attributes = [], array $quote = []): WorkOrder
    {
        $quote = Quote::factory()->create($quote);

        return WorkOrder::factory()->create(['quote_id' => $quote->id, 'start_date' => '2026-03-10', 'type' => 'project', ...$attributes]);
    }
}

if (! function_exists('mcRevenueLine')) {
    /** A REVENUE line of the commessa (or of $quote on a multi-offer one) on a product of a category tied to $function. */
    function mcRevenueLine(WorkOrder $workOrder, string $net, ?BusinessFunction $function = null, ?SupplierCommissionDirection $direction = null, ?Quote $quote = null): QuoteLine
    {
        $category = ProductCategory::factory()->create(['business_function_id' => $function?->id]);
        $line = QuoteLine::factory()->create([
            'quote_id' => $quote?->id ?? $workOrder->quote_id,
            'product_id' => Product::factory()->create(['category_id' => $category->id])->id,
            'quantity' => 1,
            'unit_price' => $net,
            'net_amount' => $net,
            'total_amount' => $net,
            'supplier_commission_direction' => $direction,
        ]);
        $workOrder->quoteLines()->attach($line->id);

        return $line;
    }
}

if (! function_exists('mcCostLine')) {
    /** A COST line of the offer, allocated to $revenueLine or generic when null. */
    function mcCostLine(string $net, ?QuoteLine $revenueLine, ?int $quoteId = null): QuoteLine
    {
        return QuoteLine::factory()->create([
            'quote_id' => $revenueLine?->quote_id ?? $quoteId,
            'line_type' => QuoteLineType::Cost,
            'offer_line_id' => $revenueLine?->id,
            'net_amount' => $net,
            'total_amount' => $net,
        ]);
    }
}

if (! function_exists('mcCommission')) {
    function mcCommission(QuoteLine $line, CommissionRecipientRole $role, string $amount): QuoteLineCommission
    {
        return QuoteLineCommission::factory()->create(['quote_line_id' => $line->id, 'recipient_role' => $role, 'calculated_amount' => $amount]);
    }
}

if (! function_exists('mcManualCost')) {
    function mcManualCost(WorkOrder $workOrder, string $net, ?QuoteLine $line = null): WorkOrderCost
    {
        return WorkOrderCost::factory()->create(['work_order_id' => $workOrder->id, 'quote_line_id' => $line?->id, 'net_amount' => $net, 'total_amount' => $net]);
    }
}

if (! function_exists('mcInvoice')) {
    /** A receivable document of the commessa with one installment collected for $collected. */
    function mcInvoice(WorkOrder $workOrder, InvoiceType $type, string $net, string $total, ?string $collected = null): Invoice
    {
        $invoice = Invoice::factory()->create(['type' => $type, 'work_order_id' => $workOrder->id, 'net_amount' => $net, 'vat_amount' => '0.00', 'total_amount' => $total]);
        InvoiceInstallment::factory()->create(['invoice_id' => $invoice->id, 'sequence' => 1, 'amount' => $total, 'collected_amount' => $collected]);

        return $invoice;
    }
}

if (! function_exists('mcOrderLine')) {
    /**
     * An order line of one unit at $amount.
     *
     * @param  array<string, mixed>  $order  purchase order attributes
     */
    function mcOrderLine(?WorkOrder $workOrder, string $amount, PurchaseOrderStatus $status = PurchaseOrderStatus::Approved, array $order = [], array $line = []): PurchaseOrderLine
    {
        return PurchaseOrderLine::factory()->create([
            'purchase_order_id' => PurchaseOrder::factory()->withStatus($status)->create($order)->id,
            'work_order_id' => $workOrder?->id,
            'quantity' => '1.000',
            'unit_price' => $amount,
            'taxable_amount' => $amount,
            ...$line,
        ]);
    }
}

if (! function_exists('mcPayableLine')) {
    /**
     * A payable document line of $net, billing $quantity of $orderLine when given.
     *
     * @param  array<string, mixed>  $document  purchase invoice attributes
     */
    function mcPayableLine(?WorkOrder $workOrder, string $net, ?PurchaseOrderLine $orderLine = null, string $quantity = '1.000', array $document = [], ?PurchaseInvoice $invoice = null, array $line = []): PurchaseInvoiceLine
    {
        return PurchaseInvoiceLine::factory()->create([
            'purchase_invoice_id' => ($invoice ?? PurchaseInvoice::factory()->create($document))->id,
            'purchase_order_line_id' => $orderLine?->id,
            'work_order_id' => $workOrder?->id,
            'quantity' => $quantity,
            'net_amount' => $net,
            ...$line,
        ]);
    }
}

if (! function_exists('mcDay')) {
    /** A date relative to today ("Y-m-d"): the exposure report is a snapshot at the current day (spec 0269). */
    function mcDay(int $offset): string
    {
        return CarbonImmutable::today()->addDays($offset)->toDateString();
    }
}

if (! function_exists('mcReceivable')) {
    /**
     * An active document whose installments are given as `due` (days from today),
     * `amount` and, when collected, `collected` (amount) and `collected_at` (days
     * from today, default -1). The total is the sum of the installments.
     *
     * @param  array<int, array<string, mixed>>  $installments
     * @param  array<string, mixed>  $invoice  document attributes
     */
    function mcReceivable(array $installments, array $invoice = []): Invoice
    {
        $total = (string) array_sum(array_column($installments, 'amount'));
        $document = Invoice::factory()->create([
            'type' => InvoiceType::Invoice, 'document_date' => mcDay(-120),
            'net_amount' => $total, 'vat_amount' => '0.00', 'total_amount' => $total, ...$invoice,
        ]);

        foreach (array_values($installments) as $index => $installment) {
            InvoiceInstallment::factory()->create([
                'invoice_id' => $document->id, 'sequence' => $index + 1,
                'due_date' => mcDay($installment['due']), 'amount' => $installment['amount'],
                'collected_amount' => $installment['collected'] ?? null,
                'collected_at' => isset($installment['collected']) ? mcDay($installment['collected_at'] ?? -1) : null,
            ]);
        }

        return $document;
    }
}

if (! function_exists('mcPayable')) {
    /**
     * A payable document whose installments are given as in `mcReceivable()`
     * (`paid`, `paid_at`). Installments are the amount to pay, so the total is
     * their sum plus withholding and ENASARCO.
     *
     * @param  array<int, array<string, mixed>>  $installments
     * @param  array<string, mixed>  $document  document attributes
     */
    function mcPayable(array $installments, array $document = []): PurchaseInvoice
    {
        $total = (string) (array_sum(array_column($installments, 'amount')) + (float) ($document['withholding_amount'] ?? 0) + (float) ($document['enasarco_amount'] ?? 0));
        $invoice = PurchaseInvoice::factory()->create([
            'document_type' => PurchaseInvoiceType::Invoice, 'document_date' => mcDay(-120),
            'net_amount' => $total, 'vat_amount' => '0.00', 'total_amount' => $total, ...$document,
        ]);

        foreach (array_values($installments) as $index => $installment) {
            PurchaseInvoiceInstallment::factory()->create([
                'purchase_invoice_id' => $invoice->id, 'sequence' => $index + 1,
                'due_date' => mcDay($installment['due']), 'amount' => $installment['amount'],
                'paid_amount' => $installment['paid'] ?? null,
                'paid_at' => isset($installment['paid']) ? mcDay($installment['paid_at'] ?? -1) : null,
            ]);
        }

        return $invoice;
    }
}

if (! function_exists('mcExposureScenario')) {
    /**
     * Two companies with a site each and two operational sites. Open amounts:
     * receivable Alfa 100 (offer on Sede 1 / Torino) + 50 (no offer), Beta 200
     * (Sede 2 / Roma); payable Alfa 30 (Sede 1 / Torino), Beta 20 (Sede 2, no
     * operational site); committed Alfa 40 (Sede 1 / Torino), Beta 70 (Sede 2 / Roma).
     *
     * @return array<string, Company|CompanySite|OperationalSite>
     */
    function mcExposureScenario(): array
    {
        $alfa = Company::factory()->create(['denomination' => 'Alfa']);
        $beta = Company::factory()->create(['denomination' => 'Beta']);
        $siteOne = CompanySite::factory()->create(['company_id' => $alfa->id, 'name' => 'Sede 1']);
        $siteTwo = CompanySite::factory()->create(['company_id' => $beta->id, 'name' => 'Sede 2']);
        $torino = OperationalSite::factory()->create(['alias' => 'Torino']);
        $roma = OperationalSite::factory()->create(['alias' => 'Roma']);
        $quoteOne = Quote::factory()->create(['company_id' => $alfa->id, 'company_site_id' => $siteOne->id, 'operational_site_id' => $torino->id]);
        $quoteTwo = Quote::factory()->create(['company_id' => $beta->id, 'company_site_id' => $siteTwo->id, 'operational_site_id' => $roma->id]);

        mcReceivable([['due' => 10, 'amount' => '100.00']], ['company_id' => $alfa->id, 'quote_id' => $quoteOne->id]);
        mcReceivable([['due' => 10, 'amount' => '200.00']], ['company_id' => $beta->id, 'quote_id' => $quoteTwo->id]);
        mcReceivable([['due' => 10, 'amount' => '50.00']], ['company_id' => $alfa->id]);
        mcPayable([['due' => 10, 'amount' => '30.00']], ['company_id' => $alfa->id, 'company_site_id' => $siteOne->id, 'operational_site_id' => $torino->id]);
        mcPayable([['due' => 10, 'amount' => '20.00']], ['company_id' => $beta->id, 'company_site_id' => $siteTwo->id]);
        mcOrderLine(null, '40.00', PurchaseOrderStatus::Approved, ['company_id' => $alfa->id, 'company_site_id' => $siteOne->id, 'operational_site_id' => $torino->id]);
        mcOrderLine(null, '70.00', PurchaseOrderStatus::Approved, ['company_id' => $beta->id, 'company_site_id' => $siteTwo->id, 'operational_site_id' => $roma->id]);

        return compact('alfa', 'beta', 'siteOne', 'siteTwo', 'torino', 'roma');
    }
}

if (! function_exists('mcMonth')) {
    /** "YYYY-MM" of the month `$months` after the current one (spec 0270). */
    function mcMonth(int $months): string
    {
        return CarbonImmutable::today()->startOfMonth()->addMonthsNoOverflow($months)->format('Y-m');
    }
}

if (! function_exists('mcMonthDue')) {
    /** Days from today to the 15th of the month `$months` after the current one (always in the future for $months >= 1). */
    function mcMonthDue(int $months): int
    {
        $today = CarbonImmutable::today();

        return (int) $today->diffInDays($today->startOfMonth()->addMonthsNoOverflow($months)->addDays(14), false);
    }
}

if (! function_exists('mcTrendInvoice')) {
    /**
     * A receivable document dated `$date` with one line per `[net, quote line]` pair.
     *
     * @param  array<int, array{0: string, 1: QuoteLine|null}>  $lines
     * @param  array<string, mixed>  $invoice  document attributes
     */
    function mcTrendInvoice(string $date, array $lines, array $invoice = [], InvoiceType $type = InvoiceType::Invoice): Invoice
    {
        $document = Invoice::factory()->create(['type' => $type, 'document_date' => $date, ...$invoice]);

        foreach ($lines as [$net, $quoteLine]) {
            InvoiceLine::factory()->create(['invoice_id' => $document->id, 'quote_line_id' => $quoteLine?->id, 'net_amount' => $net]);
        }

        return $document;
    }
}

if (! function_exists('mcTrendCost')) {
    /**
     * A payable document line of `$net` dated `$date`, tied to `$workOrder` when given.
     *
     * @param  array<string, mixed>  $document  purchase invoice attributes
     * @param  array<string, mixed>  $line  line attributes
     */
    function mcTrendCost(string $date, string $net, ?WorkOrder $workOrder = null, array $document = [], array $line = []): PurchaseInvoiceLine
    {
        return mcPayableLine($workOrder, $net, document: ['document_date' => $date, ...$document], line: $line);
    }
}

if (! function_exists('mcQueryCount')) {
    /** Number of queries a management control GET runs (the aggregate cache is emptied first; a first call warms the permission lookup). */
    function mcQueryCount(string $endpoint, array $params = []): int
    {
        mcGet($endpoint, $params)->assertOk();
        DB::flushQueryLog();
        DB::enableQueryLog();
        mcGet($endpoint, $params)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}

if (! function_exists('mcProforma')) {
    /**
     * A proforma of `$total` created `$createdDaysAgo` days ago (10:00), without installments.
     *
     * @param  array<string, mixed>  $invoice  document attributes
     */
    function mcProforma(string $total, int $createdDaysAgo, array $invoice = []): Invoice
    {
        return Invoice::factory()->create([
            'type' => InvoiceType::Proforma, 'net_amount' => $total, 'vat_amount' => '0.00', 'total_amount' => $total,
            'created_at' => mcDay(-$createdDaysAgo).' 10:00:00', ...$invoice,
        ]);
    }
}

if (! function_exists('mcConvert')) {
    /**
     * Registers the external number of a proforma through the service, so the activity log records
     * the change of type, and dates that log row `$daysAfterCreation` days after the creation.
     */
    function mcConvert(Invoice $document, int $daysAfterCreation): Invoice
    {
        $created = CarbonImmutable::parse($document->created_at);
        app(InvoiceService::class)->updateDetails($document, ['external_number' => 'T-'.$document->id, 'external_date' => $created->toDateString()], User::factory()->create());
        DB::table('activity_log')
            ->where('subject_type', $document->getMorphClass())->where('subject_id', $document->id)->where('event', 'updated')
            ->update(['created_at' => $created->addDays($daysAfterCreation)->toDateTimeString()]);

        return $document->refresh();
    }
}

if (! function_exists('mcCollect')) {
    /** One installment of `$amount` already collected `$daysAgo` days ago (or still open when null). */
    function mcCollect(Invoice $document, string $amount, ?int $collectedDaysAgo, int $sequence = 1): InvoiceInstallment
    {
        return InvoiceInstallment::factory()->create([
            'invoice_id' => $document->id, 'sequence' => $sequence, 'due_date' => mcDay(10), 'amount' => $amount,
            'collected_amount' => $collectedDaysAgo === null ? null : $amount,
            'collected_at' => $collectedDaysAgo === null ? null : mcDay(-$collectedDaysAgo),
        ]);
    }
}

if (! function_exists('mcAccount')) {
    function mcAccount(string $name): FinancialAccount
    {
        return FinancialAccount::factory()->create(['name' => $name]);
    }
}

if (! function_exists('mcTimeEntry')) {
    /**
     * A time entry of `$minutes` on `$date`, tied to `$workOrder` when given, by `$user` or by a new
     * user whose contract condition pays `$hourlyCost` per hour (null = a profile without contract, spec 0277).
     */
    function mcTimeEntry(?WorkOrder $workOrder, int $minutes, ?string $hourlyCost, string $date = '2026-03-10', ?User $user = null): TimeEntry
    {
        $user ??= User::factory()->withEmployment(fn ($factory) => $hourlyCost === null ? $factory : $factory->paidHourly($hourlyCost))->create();

        return TimeEntry::factory()->create(['user_id' => $user->id, 'work_order_id' => $workOrder?->id, 'minutes' => $minutes, 'date' => $date]);
    }
}

if (! function_exists('mcAgentAccrual')) {
    /**
     * A ledger movement owed to the referent `$agent`.
     *
     * @param  array<string, mixed>  $attributes  accrual attributes (company_id, commission_settlement_id, type...)
     */
    function mcAgentAccrual(Referent $agent, string $amount, string $accruedOn, array $attributes = []): CommissionAccrual
    {
        return CommissionAccrual::factory()->create([
            'recipient_type' => 'referent', 'recipient_id' => $agent->id, 'amount' => $amount, 'accrued_on' => $accruedOn, ...$attributes,
        ]);
    }
}

if (! function_exists('mcAgentSettlement')) {
    /** A settlement statement of `$agent` in the given status (DRAFT, ISSUED, DOCUMENT_RECEIVED, APPROVED, CANCELLED). */
    function mcAgentSettlement(Referent $agent, string $status, string $netPayable, ?Company $company = null, ?PurchaseInvoice $invoice = null): CommissionSettlement
    {
        return CommissionSettlement::factory()->create([
            'referent_id' => $agent->id, 'status' => $status, 'net_payable' => $netPayable,
            'company_id' => $company?->id ?? Company::factory(), 'purchase_invoice_id' => $invoice?->id,
        ]);
    }
}

if (! function_exists('mcAgentPayable')) {
    /**
     * The payable invoice of a statement, one installment per `[amount, paid, paid_at]` triple (paid and paid_at null = open).
     *
     * @param  array<int, array{0: string, 1: string|null, 2: string|null}>  $installments
     */
    function mcAgentPayable(array $installments): PurchaseInvoice
    {
        $invoice = PurchaseInvoice::factory()->create(['document_type' => PurchaseInvoiceType::Invoice]);

        foreach ($installments as $index => [$amount, $paid, $paidAt]) {
            PurchaseInvoiceInstallment::factory()->create([
                'purchase_invoice_id' => $invoice->id, 'sequence' => $index + 1, 'amount' => $amount, 'paid_amount' => $paid, 'paid_at' => $paidAt,
            ]);
        }

        return $invoice;
    }
}
