<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\ProformaRequest;
use App\Models\Registry;
use App\Models\User;
use App\Models\VatRate;
use App\Models\WorkOrder;
use Spatie\Permission\Models\Permission;

if (! function_exists('invoiceUserWith')) {
    /**
     * A user holding exactly the given invoices abilities, plus the right to
     * view proforma requests and work orders (the issue flow needs both).
     *
     * @param  array<int, string>  $abilities
     */
    function invoiceUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'viewActivity', 'collect'] as $ability) {
            Permission::findOrCreate("invoices.{$ability}");
        }

        $user = proformaUserWith(['view']);

        foreach ($abilities as $ability) {
            $user->givePermissionTo("invoices.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('invoiceRequest')) {
    /**
     * A pending proforma request on a work order with two lines of `$kind`
     * (a 2-installment 30/30 payment method, the offer issued by a company).
     */
    function invoiceRequest(string $kind = 'consultancy', ?Registry $supplier = null, ?WorkOrder $workOrder = null): ProformaRequest
    {
        if ($workOrder === null) {
            $method = PaymentMethod::factory()->create(['installments_count' => 2, 'payment_days' => 30, 'days_between_installments' => 30]);
            $workOrder = proformaWorkOrder([[$kind, $supplier], [$kind, $supplier]], $method);
            $workOrder->quote->update(['company_id' => Company::factory()->create()->id]);
        }

        return ProformaRequest::factory()->create([
            'work_order_id' => $workOrder->id,
            'kind' => $kind,
            'supplier_id' => $supplier?->id,
            'payment_method_id' => $workOrder->quote->payment_method_id,
        ]);
    }
}

if (! function_exists('invoicePayload')) {
    /**
     * A valid write payload for the request: one line of 3 x 10.10 at 22% (net 30.30, vat 6.67, total 36.97).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function invoicePayload(ProformaRequest $request, array $overrides = []): array
    {
        $quote = $request->workOrder->quote;

        return array_replace([
            'document_date' => '2026-03-15',
            'company_id' => $quote->company_id,
            'customer_registry_id' => Registry::factory()->create()->id,
            'payment_method_id' => $quote->payment_method_id,
            'lines' => [[
                'quote_line_id' => $request->workOrder->quoteLines()->value('quote_lines.id'),
                'description' => 'Consulting',
                'quantity' => '3',
                'unit_price' => '10.10',
                'vat_rate_id' => VatRate::factory()->create(['rate' => '22.00'])->id,
                'net_amount' => '99999.00',
            ]],
        ], $overrides);
    }
}
