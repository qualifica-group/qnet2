<?php

use App\Models\CostType;
use App\Models\PaymentMethod;
use App\Models\PurchaseInvoice;
use App\Models\User;
use App\Models\VatRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// Spec 0267, D-6 (AC-013): the supplier ledger carries the payable amount.

afterEach(fn () => Carbon::setTestNow());

it('AC-013: a payable invoice with withholdings shows the payable amount and settles to zero', function () {
    Carbon::setTestNow('2026-10-15 10:00:00');
    $method = PaymentMethod::factory()->create(['installments_count' => 2, 'payment_days' => 30, 'days_between_installments' => 30]);
    $invoice = createPurchaseInvoice([
        'document_type' => 'invoice',
        'document_number' => 'FT-77',
        'payment_method_id' => $method->id,
        'withholding_amount' => '115.00',
        'enasarco_amount' => '85.00',
        'lines' => [[
            'description' => 'Fee',
            'quantity' => '1',
            'unit_price' => '1000.00',
            'vat_rate_id' => VatRate::factory()->create(['rate' => '22.00'])->id,
            'cost_type_id' => CostType::factory()->create()->id,
        ]],
    ]);

    foreach (['ledger.view', 'purchase-invoices.view'] as $name) {
        Permission::findOrCreate($name, 'web');
    }
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['ledger.view', 'purchase-invoices.view']);
    Sanctum::actingAs($viewer);

    $url = "/api/registries/{$invoice->supplier_id}/ledger?date_from=2026-01-01";
    $document = $this->getJson($url)->assertOk()->json('data.supplier.documents.0');

    expect($document['credit'])->toEqual(1020.0)
        ->and($document['residual_amount'])->toEqual(1020.0);

    PurchaseInvoice::query()->findOrFail($invoice->id)->installments()->get()
        ->each(fn ($row) => $row->update(['paid_amount' => $row->amount, 'paid_at' => '2026-10-01']));

    $document = $this->getJson($url)->assertOk()->json('data.supplier.documents.0');

    expect($document['credit'])->toEqual(1020.0)
        ->and($document['debit'])->toEqual(1020.0)
        ->and($document['residual_amount'])->toEqual(0.0)
        ->and($document['running_balance'])->toEqual(0.0);
});
