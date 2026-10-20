<?php

namespace Database\Seeders\ManagementControl;

use App\Enums\PurchaseInvoiceType;
use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use App\Models\PurchaseOrder;
use App\Models\Registry;
use App\Services\PurchaseInvoices\PurchaseInvoicePaymentService;
use App\Services\PurchaseInvoices\PurchaseInvoiceService;
use App\Services\PurchaseOrders\PurchaseOrderService;
use App\Services\PurchaseOrders\PurchaseOrderStatusService;
use Carbon\CarbonImmutable;
use Faker\Generator;

/**
 * The passive cycle: purchase orders charged to a commessa, approved and sent, billed
 * by the supplier after the delivery; the recurring structure costs billed with no
 * order and no commessa; every installment paid around its due date. Supplier
 * document numbers run per supplier and year.
 */
final class McPayables
{
    private const array PAY_DELAY_DAYS = [0, 12];

    private const int UNPAID_PERCENT = 4;

    private const array STRUCTURE_BILL_DAY = [1, 8];

    private const array PEAK_MONTHS = [1, 2, 7, 8, 12];

    /** @var array<string, int> next document number by supplier id and year */
    private array $numbers = [];

    public function __construct(
        private readonly PurchaseOrderService $orders,
        private readonly PurchaseOrderStatusService $statuses,
        private readonly PurchaseInvoiceService $invoices,
        private readonly PurchaseInvoicePaymentService $payments,
    ) {}

    /**
     * An order charged to $workOrderId on $orderOn, billed on $billOn.
     *
     * @param  array{company_id: int, company_site_id: int|null, bank_id: int}  $company
     */
    public function workOrderPurchase(McTimeline $timeline, McContext $context, Generator $faker, array $company, int $workOrderId, Registry $supplier, int $costTypeId, string $description, int $quantity, float $price, string $orderOn, string $billOn): void
    {
        $timeline->at($orderOn, function (string $date) use ($timeline, $context, $faker, $company, $workOrderId, $supplier, $costTypeId, $description, $quantity, $price, $billOn): void {
            $order = $this->orders->createOne([
                'supplier_id' => $supplier->id,
                'company_id' => $company['company_id'],
                'company_site_id' => $company['company_site_id'],
                'order_date' => $date,
                'description' => $description,
                'payment_method_id' => $context->supplierPaymentMethodId,
            ], [[
                'id' => null, 'product_id' => null, 'description' => $description, 'cost_type_id' => $costTypeId, 'unit_of_measure_id' => null,
                'quantity' => (string) $quantity, 'unit_price' => (string) $price, 'vat_rate_id' => $context->vatRateId, 'work_order_id' => $workOrderId,
            ]], null, $context->actor);

            foreach ([PurchaseOrderStatus::Approved, PurchaseOrderStatus::SentToSupplier] as $status) {
                $order = $this->statuses->change($order, $status, $context->actor);
            }

            $timeline->at($billOn, fn (string $on) => $this->billOrder($timeline, $context, $faker, $order, $on));
        });
    }

    /**
     * The supplier invoice of an order: one line per order line, linked to it.
     * As the invoice draft does, the header takes the work order (and its
     * offer's operational site) when every line agrees on one.
     */
    public function billOrder(McTimeline $timeline, McContext $context, Generator $faker, PurchaseOrder $order, string $date): void
    {
        $order->load('lines.workOrder.quote');
        $workOrders = $order->lines->pluck('workOrder')->unique('id');
        $workOrder = $workOrders->count() === 1 ? $workOrders->first() : null;

        $invoice = $this->document($context, $order->supplier_id, $order->company_id, $order->company_site_id, $date, $order->lines->map(fn ($line): array => [
            'purchase_order_line_id' => $line->id, 'product_id' => $line->product_id, 'description' => $line->description,
            'quantity' => (string) $line->quantity, 'unit_price' => (string) $line->unit_price, 'vat_rate_id' => $line->vat_rate_id,
            'cost_type_id' => $line->cost_type_id, 'work_order_id' => $line->work_order_id,
        ])->all(), $order->lines->first()?->cost_type_id, [
            'work_order_id' => $workOrder?->id,
            'operational_site_id' => $workOrder?->quote?->operational_site_id,
        ]);
        $invoice->purchaseOrders()->sync([$order->id]);

        $this->schedulePayments($timeline, $faker, $invoice);
    }

    /** Every recurring structure cost, month by month over the period. */
    public function scheduleStructure(McTimeline $timeline, McContext $context, Generator $faker): void
    {
        $company = $context->companies[0];

        for ($month = CarbonImmutable::parse(McCatalogue::PERIOD_START); $month->toDateString() <= McCatalogue::PERIOD_END; $month = $month->addMonth()) {
            foreach (McCatalogue::STRUCTURE_COSTS as [$supplierName, $costType, $description, $amount, $months, $amplitude]) {
                if ($months !== null && ! in_array($month->month, $months, true)) {
                    continue;
                }

                $date = McTimeline::nextWorkingDay($month->addDays($faker->numberBetween(...self::STRUCTURE_BILL_DAY) - 1))->toDateString();
                $net = round($amount * $this->seasonal($month->month, $amplitude) * $faker->randomFloat(3, 0.97, 1.03), 2);
                $label = sprintf('%s - %s', $description, $month->locale('it')->translatedFormat('F Y'));

                $timeline->at($date, fn (string $on) => $this->schedulePayments($timeline, $faker, $this->document(
                    $context, $context->suppliers[$supplierName]->id, $company['company_id'], $company['company_site_id'], $on,
                    [['description' => $label, 'quantity' => '1', 'unit_price' => (string) $net, 'vat_rate_id' => $context->vatRateId, 'cost_type_id' => $context->costTypes[$costType]]],
                    $context->costTypes[$costType],
                )));
            }
        }
    }

    /** Heating and cooling: the peak months cost more, the mild ones less. */
    private function seasonal(int $month, float $amplitude): float
    {
        return 1 + (in_array($month, self::PEAK_MONTHS, true) ? $amplitude : -$amplitude / 2);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array{work_order_id?: int|null, operational_site_id?: int|null}  $references
     */
    private function document(McContext $context, int $supplierId, int $companyId, ?int $companySiteId, string $date, array $lines, ?int $costTypeId, array $references = []): PurchaseInvoice
    {
        $year = CarbonImmutable::parse($date)->year;
        $key = "{$supplierId}|{$year}";
        $this->numbers[$key] = ($this->numbers[$key] ?? 0) + 1;

        return $this->invoices->create($context->actor, [
            'document_type' => PurchaseInvoiceType::Invoice->value,
            'document_number' => sprintf('%d/%d', $this->numbers[$key], $year),
            'document_date' => $date,
            'company_id' => $companyId,
            'supplier_id' => $supplierId,
            'payment_method_id' => $context->supplierPaymentMethodId,
            'financial_account_id' => $context->bankOf($companyId),
            'cost_type_id' => $costTypeId,
            'company_site_id' => $companySiteId,
            'lines' => $lines,
        ] + array_filter($references));
    }

    private function schedulePayments(McTimeline $timeline, Generator $faker, PurchaseInvoice $invoice): void
    {
        if ($faker->boolean(self::UNPAID_PERCENT)) {
            return;
        }

        $invoice->installments()->get()->each(function (PurchaseInvoiceInstallment $installment) use ($timeline, $faker, $invoice): void {
            $payOn = McTimeline::nextWorkingDay($installment->due_date->toImmutable()->addDays($faker->numberBetween(...self::PAY_DELAY_DAYS)))->toDateString();

            $timeline->at($payOn, fn (string $on) => $this->payments->record($installment->refresh(), [
                'paid_amount' => (string) $installment->amount,
                'paid_at' => $on,
                'financial_account_id' => $invoice->financial_account_id,
            ]));
        });
    }
}
