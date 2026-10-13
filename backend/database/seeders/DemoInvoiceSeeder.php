<?php

namespace Database\Seeders;

use App\Enums\FinancialAccountType;
use App\Models\FinancialAccount;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Database\Seeders\Support\ActiveInvoiceWriter as Writer;
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
    /**
     * One scenario per work order, in work order id order.
     *
     * @var array<int, array{days_ago: int, method: string, collect: string, registered: bool}>
     */
    private const array SCENARIOS = [
        ['days_ago' => 270, 'method' => 'riba_30_60', 'collect' => Writer::COLLECT_ALL, 'registered' => true],
        ['days_ago' => 210, 'method' => 'riba_30_60_90', 'collect' => Writer::COLLECT_FIRST, 'registered' => true],
        ['days_ago' => 180, 'method' => 'riba_30_60_90_eom', 'collect' => Writer::COLLECT_NONE, 'registered' => true],
        ['days_ago' => 150, 'method' => 'bank_transfer_30_120_eom_10', 'collect' => Writer::COLLECT_PARTIAL, 'registered' => false],
        ['days_ago' => 120, 'method' => 'bank_transfer_60_90_120', 'collect' => Writer::COLLECT_FIRST, 'registered' => true],
        ['days_ago' => 95, 'method' => 'bank_transfer_30_60_90_vat_first', 'collect' => Writer::COLLECT_NONE, 'registered' => false],
        ['days_ago' => 75, 'method' => 'bank_transfer_30_60_90_vat_last', 'collect' => Writer::COLLECT_FIRST, 'registered' => true],
        ['days_ago' => 50, 'method' => 'vat_upfront_30_60_90', 'collect' => Writer::COLLECT_FIRST, 'registered' => false],
        ['days_ago' => 40, 'method' => 'direct_debit_12_monthly', 'collect' => Writer::COLLECT_FIRST, 'registered' => true],
        ['days_ago' => 10, 'method' => 'bank_transfer', 'collect' => Writer::COLLECT_NONE, 'registered' => false],
    ];

    public function __construct(private readonly Writer $writer) {}

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

            $lines = $this->writer->lines($workOrder);

            if ($lines === []) {
                continue;
            }

            $invoice = $this->writer->issue($workOrder, $actor, [
                'document_date' => CarbonImmutable::today()->subDays($scenario['days_ago'])->toDateString(),
                'company_id' => $bank->company_id,
                'customer_registry_id' => $customerId,
                'payment_method_id' => $methods[$scenario['method']],
                'financial_account_id' => $bank->id,
                'notes' => null,
                'lines' => $lines,
            ]);

            $this->writer->collect($invoice, $scenario['collect']);

            if ($scenario['registered']) {
                $this->writer->register($invoice, $actor);
            }
        }
    }
}
