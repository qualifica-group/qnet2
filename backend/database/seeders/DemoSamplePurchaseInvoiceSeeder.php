<?php

namespace Database\Seeders;

use App\Enums\PurchaseInvoiceType;
use App\Enums\PurchaseOrderStatus;
use App\Models\CostType;
use App\Models\PaymentMethod;
use App\Models\PurchaseOrder;
use App\Models\User;
use Database\Seeders\Support\PayableDocumentWriter as Writer;
use Illuminate\Database\Seeder;

/**
 * Additive payable documents (spec 0211) for DemoAccountingSampleSeeder: one
 * document on every order sent to the supplier (or further) that nothing bills
 * yet, with its real schedule and a mix of paid, partially paid and open
 * installments. Only a fulfilled order is billed by an invoice; the others get a
 * proforma (an advance), since an invoice would receive them (spec 0254, D-12).
 */
class DemoSamplePurchaseInvoiceSeeder extends Seeder
{
    /** The orders a supplier can bill. */
    private const array BILLABLE_STATUSES = [
        PurchaseOrderStatus::SentToSupplier,
        PurchaseOrderStatus::PartiallyFulfilled,
        PurchaseOrderStatus::Fulfilled,
    ];

    /** What is paid on the documents, cycled. */
    private const array PAYMENTS = [Writer::PAY_FIRST, Writer::PAY_PARTIAL, Writer::PAY_NONE];

    /** Document ages in days, cycled. */
    private const array AGES = [150, 90, 45, 15];

    public function __construct(private readonly Writer $writer) {}

    public function run(User $actor): void
    {
        // Step 1: the billable orders nothing bills yet
        $orders = PurchaseOrder::query()
            ->whereIn('status', self::BILLABLE_STATUSES)
            ->where('grand_total', '>', 0)
            ->whereDoesntHave('purchaseInvoices')
            ->orderBy('id')
            ->get();
        $methodIds = PaymentMethod::query()->orderBy('id')->pluck('id');
        $costTypeId = CostType::query()->where('is_active', true)->orderBy('sort_order')->value('id');

        if ($orders->isEmpty() || $methodIds->isEmpty()) {
            return;
        }

        // Step 2: one document per order, with its schedule, then the payments
        foreach ($orders->values() as $position => $order) {
            $type = $order->status === PurchaseOrderStatus::Fulfilled ? PurchaseInvoiceType::Invoice : PurchaseInvoiceType::Proforma;
            $invoice = $this->writer->document(
                $type,
                sprintf('FT %s', $order->code()),
                collect([$order]),
                self::AGES[$position % count(self::AGES)],
                $methodIds[$position % $methodIds->count()],
                $costTypeId,
                $actor,
            );

            $this->writer->pay($invoice, self::PAYMENTS[$position % count(self::PAYMENTS)]);
        }
    }
}
