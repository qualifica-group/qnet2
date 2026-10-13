<?php

namespace Database\Seeders;

use App\Enums\FinancialAccountType;
use App\Models\FinancialAccount;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Database\Seeders\Support\ActiveInvoiceWriter as Writer;
use Illuminate\Database\Seeder;

/**
 * Additive active invoicing (specs 0193/0194) for DemoAccountingSampleSeeder:
 * on work orders with no proforma request yet, a request and its document
 * issued through InvoiceService, with mixed collections and registrations.
 * Some requests are left pending, so the Contabilita' queue has work in it.
 * Work orders already invoiced are never touched again.
 */
class DemoSampleInvoiceSeeder extends Seeder
{
    public const int DEFAULT_WORK_ORDERS = 6;

    /**
     * Cycled over the work orders. A null `collect` leaves the request pending.
     *
     * @var array<int, array{days_ago: int, collect: string|null, registered: bool}>
     */
    private const array SCENARIOS = [
        ['days_ago' => 200, 'collect' => Writer::COLLECT_ALL, 'registered' => true],
        ['days_ago' => 150, 'collect' => Writer::COLLECT_FIRST, 'registered' => true],
        ['days_ago' => 100, 'collect' => Writer::COLLECT_PARTIAL, 'registered' => false],
        ['days_ago' => 0, 'collect' => null, 'registered' => false],
        ['days_ago' => 60, 'collect' => Writer::COLLECT_NONE, 'registered' => true],
        ['days_ago' => 20, 'collect' => Writer::COLLECT_NONE, 'registered' => false],
    ];

    public function __construct(private readonly Writer $writer) {}

    public function run(User $actor, int $workOrders = self::DEFAULT_WORK_ORDERS): void
    {
        // Step 1: the issuing bank account, the payment methods and the work orders nothing invoices yet
        $bank = FinancialAccount::query()->where('type', FinancialAccountType::BankAccount->value)
            ->whereNotNull('company_id')->orderBy('id')->first();
        $methodIds = PaymentMethod::query()->orderBy('id')->pluck('id');

        if ($bank === null || $methodIds->isEmpty()) {
            $this->command?->warn('Active invoices skipped: a bank account with a company or payment methods are missing.');

            return;
        }

        $candidates = WorkOrder::query()
            ->whereDoesntHave('proformaRequests')
            ->whereDoesntHave('invoices')
            ->whereHas('quote.opportunity')
            ->with('quote.opportunity')
            ->orderBy('id')
            ->limit($workOrders)
            ->get();

        // Step 2: one request per work order, issued and collected per scenario
        foreach ($candidates->values() as $position => $workOrder) {
            $scenario = self::SCENARIOS[$position % count(self::SCENARIOS)];
            $methodId = $methodIds[$position % $methodIds->count()];
            $lines = $this->writer->lines($workOrder);

            if ($scenario['collect'] === null || $lines === []) {
                $this->writer->request($workOrder, $actor, $methodId);

                continue;
            }

            $invoice = $this->writer->issue($workOrder, $actor, [
                'document_date' => CarbonImmutable::today()->subDays($scenario['days_ago'])->toDateString(),
                'company_id' => $bank->company_id,
                'customer_registry_id' => $workOrder->quote->opportunity->registry_id,
                'payment_method_id' => $methodId,
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
