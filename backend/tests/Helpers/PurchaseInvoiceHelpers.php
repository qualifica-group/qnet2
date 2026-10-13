<?php

declare(strict_types=1);

use App\Enums\FinancialAccountType;
use App\Enums\PurchaseOrderStatus;
use App\Models\Company;
use App\Models\CostType;
use App\Models\FinancialAccount;
use App\Models\PaymentMethod;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Registry;
use App\Models\User;
use App\Models\VatRate;
use App\Models\WorkOrder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

if (! function_exists('purchaseInvoiceUserWith')) {
    /**
     * A user holding exactly the given purchase-invoices abilities.
     *
     * @param  array<int, string>  $abilities
     */
    function purchaseInvoiceUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'viewActivity', 'pay', 'downgrade'] as $ability) {
            // Explicit guard: Sanctum::actingAs() switches the default one to `sanctum`.
            Permission::findOrCreate("purchase-invoices.{$ability}", 'web');
        }

        // A test may have checked a permission before this call: drop the stale registry.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("purchase-invoices.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('purchaseInvoicePayload')) {
    /**
     * A valid POST payload of a proforma with two lines on different cost types
     * and work orders: 2.5 x 100.00 at 22% (net 250.00, vat 55.00) and
     * 1.2345 x 10.00 without VAT (quantity rounds to 1.235, net 12.35); document
     * net 262.35, vat 55.00, total 317.35, on a 2-installment 30/30 method.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function purchaseInvoicePayload(array $overrides = []): array
    {
        $company = Company::factory()->create();
        $method = PaymentMethod::factory()->create(['installments_count' => 2, 'payment_days' => 30, 'days_between_installments' => 30]);
        $vat = VatRate::factory()->create(['rate' => '22.00']);

        return array_replace([
            'document_type' => 'proforma',
            'document_number' => null,
            'document_date' => '2026-03-15',
            'company_id' => $company->id,
            'supplier_id' => Registry::factory()->supplier()->create()->id,
            'payment_method_id' => $method->id,
            'lines' => [
                [
                    'description' => 'Consulting',
                    'quantity' => '2.5',
                    'unit_price' => '100.00',
                    'vat_rate_id' => $vat->id,
                    'cost_type_id' => CostType::factory()->create()->id,
                    'work_order_id' => WorkOrder::factory()->create()->id,
                ],
                [
                    'description' => 'Materials',
                    'quantity' => '1.2345',
                    'unit_price' => '10.00',
                    'cost_type_id' => CostType::factory()->create()->id,
                    'work_order_id' => WorkOrder::factory()->create()->id,
                ],
            ],
        ], $overrides);
    }
}

if (! function_exists('purchaseInvoiceUpdatePayload')) {
    /**
     * A PUT payload keeping the stored document unchanged (same parties, date,
     * method and one line per stored line); tests override only what they exercise.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function purchaseInvoiceUpdatePayload(PurchaseInvoice $document, array $overrides = []): array
    {
        $document->load('lines');

        return array_replace([
            'document_date' => $document->document_date->toDateString(),
            'company_id' => $document->company_id,
            'supplier_id' => $document->supplier_id,
            'payment_method_id' => $document->payment_method_id,
            'lines' => $document->lines->map(fn ($line): array => [
                'id' => $line->id,
                'purchase_order_line_id' => $line->purchase_order_line_id,
                'description' => $line->description,
                'quantity' => (string) $line->quantity,
                'unit_price' => (string) $line->unit_price,
                'vat_rate_id' => $line->vat_rate_id,
                'cost_type_id' => $line->cost_type_id,
                'work_order_id' => $line->work_order_id,
            ])->all(),
        ], $overrides);
    }
}

if (! function_exists('createPurchaseInvoice')) {
    /**
     * Create a document through the API as a user holding every purchase-invoices ability.
     *
     * @param  array<string, mixed>  $overrides  payload overrides
     */
    function createPurchaseInvoice(array $overrides = []): PurchaseInvoice
    {
        $actor = purchaseInvoiceUserWith(['viewAny', 'view', 'create', 'update', 'delete', 'downgrade']);
        Sanctum::actingAs($actor);

        $id = test()->postJson('/api/purchase-invoices', purchaseInvoicePayload($overrides))->assertCreated()->json('data.id');

        return PurchaseInvoice::query()->findOrFail($id);
    }
}

if (! function_exists('invoiceableOrderOf')) {
    /**
     * An order in an invoiceable status with `$lineCount` lines of 100.00 each
     * (no VAT), of the document parties when a payload is given.
     *
     * @param  array<string, mixed>  $payload  purchaseInvoicePayload() whose supplier and company the order takes
     */
    function invoiceableOrderOf(array $payload, PurchaseOrderStatus $status = PurchaseOrderStatus::Approved, int $lineCount = 2): PurchaseOrder
    {
        $order = purchaseOrderWithLines($status, $lineCount, ['supplier_id' => $payload['supplier_id'], 'company_id' => $payload['company_id']]);

        return $order->load('lines');
    }
}

if (! function_exists('orderLinePayload')) {
    /**
     * A document line billing the given order line.
     *
     * @return array<string, mixed>
     */
    function orderLinePayload(PurchaseOrderLine $line): array
    {
        return [
            'purchase_order_line_id' => $line->id,
            'description' => $line->description,
            'quantity' => (string) $line->quantity,
            'unit_price' => (string) $line->unit_price,
            'cost_type_id' => $line->cost_type_id,
        ];
    }
}

if (! function_exists('companyBankAccount')) {
    function companyBankAccount(int $companyId): FinancialAccount
    {
        return FinancialAccount::factory()->create(['type' => FinancialAccountType::BankAccount, 'company_id' => $companyId]);
    }
}
