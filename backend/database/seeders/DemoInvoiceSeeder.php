<?php

namespace Database\Seeders;

use App\Enums\FinancialAccountType;
use App\Enums\ProformaRequestKind;
use App\Enums\QuoteLineType;
use App\Enums\ResidualMode;
use App\Models\FinancialAccount;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\PaymentMethod;
use App\Models\QuoteLine;
use App\Models\User;
use App\Models\VatRate;
use App\Models\WorkOrder;
use App\Services\Invoices\InvoiceCollectionService;
use App\Services\Invoices\InvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Demo active invoices (spec 0194): one proforma request + document per work
 * order, issued through InvoiceService so amounts, numbers and installment
 * schedules are the real ones. Document dates are relative to today and the
 * collections are mixed, so the list shows paid, partially paid, overdue,
 * seriously overdue and not-yet-due documents. Depends on DemoWorkOrderSeeder,
 * DemoPaymentMethodSeeder and DemoFinancialAccountSeeder. Re-runnable: the
 * previous demo documents and requests are deleted first.
 */
class DemoInvoiceSeeder extends Seeder
{
    private const string COLLECT_ALL = 'all';

    private const string COLLECT_FIRST = 'first';

    private const string COLLECT_PARTIAL = 'partial';

    private const string COLLECT_NONE = 'none';

    /** Fallback when neither the quote line nor its product carries a VAT rate. */
    private const string DEFAULT_VAT_RATE = '22';

    /**
     * One scenario per work order, in work order id order.
     *
     * @var array<int, array{days_ago: int, method: string, collect: string, registered: bool}>
     */
    private const array SCENARIOS = [
        ['days_ago' => 270, 'method' => 'riba_30_60', 'collect' => self::COLLECT_ALL, 'registered' => true],
        ['days_ago' => 210, 'method' => 'riba_30_60_90', 'collect' => self::COLLECT_FIRST, 'registered' => true],
        ['days_ago' => 180, 'method' => 'riba_30_60_90_eom', 'collect' => self::COLLECT_NONE, 'registered' => true],
        ['days_ago' => 150, 'method' => 'bank_transfer_30_120_eom_10', 'collect' => self::COLLECT_PARTIAL, 'registered' => false],
        ['days_ago' => 120, 'method' => 'bank_transfer_60_90_120', 'collect' => self::COLLECT_FIRST, 'registered' => true],
        ['days_ago' => 95, 'method' => 'bank_transfer_30_60_90_vat_first', 'collect' => self::COLLECT_NONE, 'registered' => false],
        ['days_ago' => 75, 'method' => 'bank_transfer_30_60_90_vat_last', 'collect' => self::COLLECT_FIRST, 'registered' => true],
        ['days_ago' => 50, 'method' => 'vat_upfront_30_60_90', 'collect' => self::COLLECT_FIRST, 'registered' => false],
        ['days_ago' => 40, 'method' => 'direct_debit_12_monthly', 'collect' => self::COLLECT_FIRST, 'registered' => true],
        ['days_ago' => 10, 'method' => 'bank_transfer', 'collect' => self::COLLECT_NONE, 'registered' => false],
    ];

    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly InvoiceCollectionService $collections,
    ) {}

    public function run(): void
    {
        // Step 1: clear the previous demo documents (their requests cascade with the work orders)
        Invoice::query()->delete();

        // Step 2: resolve the shared context; nothing to issue without it
        $actor = User::query()->orderBy('id')->first();
        $bank = FinancialAccount::query()->where('type', FinancialAccountType::BankAccount->value)
            ->whereNotNull('company_id')->orderBy('id')->first();
        $methods = PaymentMethod::query()->pluck('id', 'code');

        if ($actor === null || $bank === null) {
            return;
        }

        $workOrders = WorkOrder::query()->with('quote.opportunity')->orderBy('id')->get();

        // Step 3: one request + document per scenario
        foreach (self::SCENARIOS as $index => $scenario) {
            $workOrder = $workOrders->get($index);
            $customerId = $workOrder?->quote?->opportunity?->registry_id;

            if ($customerId === null || ! $methods->has($scenario['method'])) {
                continue;
            }

            $lines = $this->lines($workOrder);

            if ($lines === []) {
                continue;
            }

            $invoice = $this->issue($workOrder, $actor, [
                'document_date' => CarbonImmutable::today()->subDays($scenario['days_ago'])->toDateString(),
                'company_id' => $bank->company_id,
                'customer_registry_id' => $customerId,
                'payment_method_id' => $methods[$scenario['method']],
                'financial_account_id' => $bank->id,
                'notes' => null,
                'lines' => $lines,
            ]);

            $this->collect($invoice, $scenario['collect']);

            if ($scenario['registered']) {
                $this->invoices->updateDetails($invoice, [
                    'external_number' => sprintf('%d/%s', $invoice->number, 'FE'),
                    'external_date' => $invoice->document_date->toDateString(),
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function issue(WorkOrder $workOrder, User $actor, array $data): Invoice
    {
        $request = $workOrder->proformaRequests()->create([
            'kind' => ProformaRequestKind::Consultancy,
            'payment_method_id' => $data['payment_method_id'],
            'note' => 'Richiesta proforma demo.',
            'assigned_to' => $actor->id,
            'assigned_by' => $actor->id,
        ]);

        return $this->invoices->create($request, $actor, $data);
    }

    /**
     * The work order's revenue lines in the InvoiceWritePayload line shape.
     *
     * @return array<int, array<string, mixed>>
     */
    private function lines(WorkOrder $workOrder): array
    {
        $fallbackVatRateId = VatRate::query()->where('rate', self::DEFAULT_VAT_RATE)->value('id')
            ?? VatRate::query()->orderBy('id')->value('id');

        return $workOrder->quoteLines()
            ->where('line_type', QuoteLineType::Revenue->value)
            ->with('product:id,name,vat_rate_id')
            ->get()
            ->map(fn (QuoteLine $line): array => [
                'quote_line_id' => $line->id,
                'product_id' => $line->product_id,
                'description' => $line->additional_description ?? $line->product?->name ?? 'Servizio',
                'quantity' => (string) $line->quantity,
                'unit_price' => (string) $line->unit_price,
                'vat_rate_id' => $line->vat_rate_id ?? $line->product?->vat_rate_id ?? $fallbackVatRateId,
            ])
            ->filter(fn (array $line): bool => $line['vat_rate_id'] !== null && (float) $line['unit_price'] > 0)
            ->values()
            ->all();
    }

    private function collect(Invoice $invoice, string $mode): void
    {
        $installments = $invoice->installments->sortBy('sequence')->values();
        $targets = match ($mode) {
            self::COLLECT_ALL => $installments,
            self::COLLECT_FIRST, self::COLLECT_PARTIAL => $installments->take(1),
            default => collect(),
        };

        foreach ($targets as $installment) {
            $payload = ['collected_amount' => (string) $installment->amount, 'collected_at' => $this->collectedAt($installment)];

            // A partial collection spreads its residual on the later installments (spec 0196, D-5).
            if ($mode === self::COLLECT_PARTIAL) {
                $payload['collected_amount'] = bcdiv((string) $installment->amount, '2', 2);
                $payload['residual_mode'] = ResidualMode::Spread->value;
            }

            $this->collections->record($installment, $payload);
        }
    }

    /** Collected on the due date, never in the future. */
    private function collectedAt(InvoiceInstallment $installment): string
    {
        return $installment->due_date->min(CarbonImmutable::today())->toDateString();
    }
}
