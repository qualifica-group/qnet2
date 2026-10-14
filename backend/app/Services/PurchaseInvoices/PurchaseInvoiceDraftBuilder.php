<?php

declare(strict_types=1);

namespace App\Services\PurchaseInvoices;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Services\PurchaseOrders\PurchaseOrderLineBalance;
use App\Services\PurchaseOrders\PurchaseOrderLineBillingLedger;
use App\Support\Amounts\LineAmountCalculator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Read model of "Registra fattura" (spec 0250, D-1): the document defaults and
 * the invoice lines pre-filled from one or more purchase orders of the same
 * supplier and company, plus the orders that can still be invoiced. Each line
 * is proposed for the quantity the ledger still lets a document bill
 * (spec 0254, D-2: the same for proforma and invoice); lines with nothing left are not offered.
 */
final class PurchaseInvoiceDraftBuilder
{
    /** Order statuses from which a payable document can be registered. */
    public const array INVOICEABLE_STATUSES = [
        PurchaseOrderStatus::Approved,
        PurchaseOrderStatus::SentToSupplier,
        PurchaseOrderStatus::PartiallyFulfilled,
        PurchaseOrderStatus::Fulfilled,
    ];

    private const int QUANTITY_SCALE = 3;

    public function __construct(
        private readonly PurchaseOrderLineBillingLedger $ledger,
        private readonly LineAmountCalculator $engine,
    ) {}

    /**
     * @param  array<int, int>  $purchaseOrderIds
     * @return array<string, mixed>
     *
     * @throws ValidationException when the orders mix supplier/company or are not invoiceable
     */
    public function build(array $purchaseOrderIds): array
    {
        // Step 1: load the orders in a stable order
        $orders = PurchaseOrder::query()
            ->whereIn('id', $purchaseOrderIds)
            ->with(['company:id,denomination', 'supplier:id,name', 'paymentMethod:id,name', 'companySite:id,name', 'operationalSite:id,alias'])
            ->orderBy('id')
            ->get();

        // Step 2: same parties, invoiceable statuses
        $this->assertInvoiceable($orders);

        // Step 3: the lines with a billable quantity left (one ledger read for all of them)
        $allLines = PurchaseOrderLine::query()
            ->whereIn('purchase_order_id', $orders->modelKeys())
            ->with(['product:id,name', 'vatRate:id,name,rate', 'costType:id,name,color', 'workOrder:id,code,title'])
            ->orderBy('purchase_order_id')->orderBy('position')->orderBy('id')
            ->get();
        $balances = $this->ledger->forLines($allLines->modelKeys());
        $lines = $allLines->filter(fn (PurchaseOrderLine $line): bool => $this->isBillable($balances->get($line->id)))->values();

        // Step 4: the defaults and the lines at the proposed quantity
        $codes = $orders->mapWithKeys(fn (PurchaseOrder $order): array => [$order->id => $order->code()]);

        return [
            'defaults' => $this->defaults($orders, $lines),
            'lines' => $lines->map(fn (PurchaseOrderLine $line, int $index): array => $this->line($line, $balances[$line->id]->available(), $codes, $index))->all(),
            'purchase_order_ids' => $orders->modelKeys(),
        ];
    }

    /**
     * Orders of the supplier and company with at least one line a document
     * can still bill. One ledger read whatever the number of orders.
     *
     * @return array<int, array<string, mixed>>
     */
    public function invoiceableOrders(int $supplierId, int $companyId): array
    {
        $orders = PurchaseOrder::query()
            ->where('supplier_id', $supplierId)
            ->where('company_id', $companyId)
            ->whereIn('status', array_map(fn (PurchaseOrderStatus $status): string => $status->value, self::INVOICEABLE_STATUSES))
            ->orderByDesc('order_date')->orderByDesc('id')
            ->get(['id', 'number', 'year', 'order_date', 'grand_total', 'status']);
        $lines = PurchaseOrderLine::query()->whereIn('purchase_order_id', $orders->modelKeys())->get(['id', 'purchase_order_id']);
        $balances = $this->ledger->forLines($lines->modelKeys());
        $billable = $lines->filter(fn (PurchaseOrderLine $line): bool => $this->isBillable($balances->get($line->id)))
            ->pluck('purchase_order_id')->flip();

        return $orders
            ->filter(fn (PurchaseOrder $order): bool => $billable->has($order->id))
            ->map(fn (PurchaseOrder $order): array => [
                'id' => $order->id,
                'code' => $order->code(),
                'order_date' => $order->order_date->toDateString(),
                'grand_total' => $order->grand_total,
                'status' => $order->status->value,
            ])
            ->values()
            ->all();
    }

    private function isBillable(?PurchaseOrderLineBalance $balance): bool
    {
        return $balance !== null && bccomp($balance->available(), '0', self::QUANTITY_SCALE) > 0;
    }

    /**
     * @param  Collection<int, PurchaseOrder>  $orders
     */
    private function assertInvoiceable(Collection $orders): void
    {
        if ($orders->pluck('supplier_id')->unique()->count() > 1 || $orders->pluck('company_id')->unique()->count() > 1) {
            throw ValidationException::withMessages(['purchase_order_ids' => [__('purchase_invoice_drafts.mixed_parties')]]);
        }

        if ($orders->contains(fn (PurchaseOrder $order): bool => ! in_array($order->status, self::INVOICEABLE_STATUSES, true))) {
            throw ValidationException::withMessages(['purchase_order_ids' => [__('purchase_invoice_drafts.not_invoiceable')]]);
        }
    }

    /**
     * Cost type and work order are proposed only when every line agrees.
     *
     * @param  Collection<int, PurchaseOrder>  $orders
     * @param  Collection<int, PurchaseOrderLine>  $lines
     * @return array<string, mixed>
     */
    private function defaults(Collection $orders, Collection $lines): array
    {
        $first = $orders->first();
        $costType = $lines->pluck('cost_type_id')->unique()->count() === 1 ? $lines->first()->costType : null;
        $workOrder = $lines->pluck('work_order_id')->unique()->count() === 1 ? $lines->first()->workOrder : null;
        $site = $orders->first(fn (PurchaseOrder $order): bool => $order->operational_site_id !== null)?->operationalSite;

        return [
            'company' => ['id' => $first->company->id, 'name' => $first->company->denomination],
            'supplier' => ['id' => $first->supplier->id, 'name' => $first->supplier->name],
            'payment_method' => $this->named($orders->first(fn (PurchaseOrder $order): bool => $order->payment_method_id !== null)?->paymentMethod),
            'company_site' => $this->named($orders->first(fn (PurchaseOrder $order): bool => $order->company_site_id !== null)?->companySite),
            'operational_site' => $site === null ? null : ['id' => $site->id, 'name' => $site->alias ?? (string) $site->id],
            'cost_type' => $costType === null ? null : ['id' => $costType->id, 'name' => $costType->name, 'color' => $costType->color],
            'work_order' => $this->workOrder($workOrder),
        ];
    }

    /**
     * @param  string  $available  quantity a document can still bill: the proposed one
     * @param  Collection<int, string>  $codes  order id => "n/year"
     * @return array<string, mixed>
     */
    private function line(PurchaseOrderLine $line, string $available, Collection $codes, int $index): array
    {
        $amounts = $this->engine->line($available, $line->unit_price, $line->vatRate?->rate, self::QUANTITY_SCALE);

        return [
            'id' => null,
            'purchase_order_line_id' => $line->id,
            'purchase_order' => ['id' => $line->purchase_order_id, 'code' => $codes[$line->purchase_order_id]],
            'product' => $this->named($line->product),
            'description' => $line->description,
            'quantity' => $amounts['quantity'],
            'available_quantity' => $amounts['quantity'],
            'unit_price' => $amounts['unit_price'],
            'vat_rate' => $line->vatRate === null ? null : ['id' => $line->vatRate->id, 'name' => $line->vatRate->name, 'rate' => $line->vatRate->rate],
            'cost_type' => ['id' => $line->costType->id, 'name' => $line->costType->name, 'color' => $line->costType->color],
            'work_order' => $this->workOrder($line->workOrder),
            'net_amount' => $amounts['net'],
            'vat_amount' => $amounts['vat'],
            'total_amount' => $amounts['total'],
            'sort_order' => $index,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function named(?object $model): ?array
    {
        return $model === null ? null : ['id' => $model->id, 'name' => $model->name];
    }

    /**
     * @return array{id: int, code: string, title: string}|null
     */
    private function workOrder(?object $workOrder): ?array
    {
        return $workOrder === null ? null : ['id' => $workOrder->id, 'code' => $workOrder->code, 'title' => $workOrder->title];
    }
}
