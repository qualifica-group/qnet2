<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts\Rendering\PurchaseOrders;

use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Registry;
use Illuminate\Support\Carbon;

/**
 * The deterministic, NEVER-persisted sample PurchaseOrder a `purchase_orders`
 * layout preview renders against when no real order is visible (spec 0210,
 * counterpart of InvoiceSampleFactory). Every relation the render reads is
 * set explicitly, so nothing is queried and nothing is saved.
 */
final class PurchaseOrderSampleFactory
{
    public function make(): PurchaseOrder
    {
        $today = Carbon::today();

        $company = (new Company)->forceFill(['denomination' => 'Sample company S.r.l.', 'vat_number' => 'IT00000000000']);
        $company->setRelation('addresses', collect());

        $supplier = (new Registry)->forceFill(['name' => 'Sample supplier S.r.l.']);
        $supplier->setRelation('personalData', null);

        $order = (new PurchaseOrder)->forceFill([
            'number' => 1,
            'year' => (int) $today->format('Y'),
            'order_date' => $today,
            'description' => 'Sample order',
            'general_notes' => 'Sample notes',
            'taxable_total' => '300.00',
            'vat_total' => '66.00',
            'grand_total' => '366.00',
        ]);

        $order->setRelation('company', $company);
        $order->setRelation('supplier', $supplier);
        $order->setRelation('customer', null);
        $order->setRelation('paymentMethod', null);
        $order->setRelation('workOrder', null);
        $order->setRelation('companySite', null);
        $order->setRelation('operationalSite', null);
        $order->setRelation('lines', collect([$this->line('Sample item A', '1.000', '100.00'), $this->line('Sample item B', '2.000', '100.00')]));

        return $order;
    }

    private function line(string $description, string $quantity, string $unitPrice): PurchaseOrderLine
    {
        $taxable = number_format((float) $quantity * (float) $unitPrice, 2, '.', '');
        $vat = number_format((float) $taxable * 0.22, 2, '.', '');

        $line = (new PurchaseOrderLine)->forceFill([
            'description' => $description,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'taxable_amount' => $taxable,
            'vat_amount' => $vat,
            'total_amount' => number_format((float) $taxable + (float) $vat, 2, '.', ''),
        ]);
        $line->setRelation('costType', null);
        $line->setRelation('unitOfMeasure', null);
        $line->setRelation('vatRate', null);

        return $line;
    }
}
