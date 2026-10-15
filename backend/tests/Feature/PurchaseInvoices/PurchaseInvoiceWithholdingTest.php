<?php

use App\Enums\VatAllocation;
use App\Models\CostType;
use App\Models\PaymentMethod;
use App\Models\PurchaseInvoice;
use App\Models\VatRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// Spec 0267, D-6 (AC-012): withholding and ENASARCO reduce the amount paid.

/** One line 1000.00 at 22% (net 1000.00, vat 220.00, total 1220.00) on a 2-installment method. */
function withholdingPayload(VatAllocation $allocation, array $overrides = []): array
{
    $method = PaymentMethod::factory()->create([
        'installments_count' => 2, 'payment_days' => 30, 'days_between_installments' => 30, 'vat_allocation' => $allocation,
    ]);

    return array_replace(purchaseInvoicePayload([
        'payment_method_id' => $method->id,
        'lines' => [[
            'description' => 'Fee',
            'quantity' => '1',
            'unit_price' => '1000.00',
            'vat_rate_id' => VatRate::factory()->create(['rate' => '22.00'])->id,
            'cost_type_id' => CostType::factory()->create()->id,
        ]],
    ]), $overrides);
}

beforeEach(function () {
    Sanctum::actingAs(purchaseInvoiceUserWith(['viewAny', 'view', 'create', 'update', 'delete']));
});

it('AC-012: the schedule sums to the payable amount for every VAT allocation', function (VatAllocation $allocation) {
    $response = $this->postJson('/api/purchase-invoices', withholdingPayload($allocation, ['withholding_amount' => '115.00', 'enasarco_amount' => '85.00']))
        ->assertCreated()
        ->assertJsonPath('data.total_amount', '1220.00')
        ->assertJsonPath('data.withholding_amount', '115.00')
        ->assertJsonPath('data.enasarco_amount', '85.00')
        ->assertJsonPath('data.payable_amount', '1020.00')
        ->assertJsonPath('data.residual_amount', '1020.00')
        ->assertJsonPath('data.commission_settlement', null);

    $amounts = array_column($response->json('data.installments'), 'amount');

    expect(round(array_sum(array_map('floatval', $amounts)), 2))->toBe(1020.0);
})->with(VatAllocation::cases());

it('AC-012: omitted or null withholdings default to zero and the behaviour is unchanged', function (array $extra) {
    $response = $this->postJson('/api/purchase-invoices', withholdingPayload(VatAllocation::Split, $extra))
        ->assertCreated()
        ->assertJsonPath('data.withholding_amount', '0.00')
        ->assertJsonPath('data.enasarco_amount', '0.00')
        ->assertJsonPath('data.payable_amount', '1220.00');

    expect(array_column($response->json('data.installments'), 'amount'))->toBe(['610.00', '610.00']);
})->with([[[]], [['withholding_amount' => null, 'enasarco_amount' => null]]]);

it('AC-012: withholdings equal to or above the total are refused on withholding_amount', function (string $withholding, string $enasarco) {
    $this->postJson('/api/purchase-invoices', withholdingPayload(VatAllocation::Split, ['withholding_amount' => $withholding, 'enasarco_amount' => $enasarco]))
        ->assertUnprocessable()->assertJsonValidationErrors('withholding_amount');

    expect(PurchaseInvoice::query()->count())->toBe(0);
})->with([['1220.00', '0'], ['1000.00', '220.00'], ['2000', '0']]);

it('AC-012: negative withholdings are refused', function () {
    $this->postJson('/api/purchase-invoices', withholdingPayload(VatAllocation::Split, ['enasarco_amount' => '-1']))
        ->assertUnprocessable()->assertJsonValidationErrors('enasarco_amount');
});

it('AC-012: the withholdings are frozen once a payment is recorded', function () {
    $document = createPurchaseInvoice(withholdingPayload(VatAllocation::Split, ['withholding_amount' => '115.00', 'enasarco_amount' => '85.00']));
    $first = $document->installments()->first();
    $first->update(['paid_amount' => $first->amount, 'paid_at' => '2026-04-14']);
    $keep = ['withholding_amount' => '115.00', 'enasarco_amount' => '85.00'];

    $this->putJson("/api/purchase-invoices/{$document->id}", purchaseInvoiceUpdatePayload($document, ['withholding_amount' => '100.00', 'enasarco_amount' => '85.00']))
        ->assertUnprocessable()->assertJsonValidationErrors('withholding_amount');
    $this->putJson("/api/purchase-invoices/{$document->id}", purchaseInvoiceUpdatePayload($document, ['withholding_amount' => '115.00', 'enasarco_amount' => '0']))
        ->assertUnprocessable()->assertJsonValidationErrors('enasarco_amount');

    // Unchanged withholdings pass and the rebalanced schedule still sums to the payable amount
    $this->putJson("/api/purchase-invoices/{$document->id}", purchaseInvoiceUpdatePayload($document, $keep + ['notes' => 'ok']))
        ->assertOk()->assertJsonPath('data.payable_amount', '1020.00');

    expect(round((float) $document->refresh()->installments()->sum('amount'), 2))->toBe(1020.0);
});

it('AC-012: PATCH keeps the stored withholdings when they are not sent', function () {
    $document = createPurchaseInvoice(withholdingPayload(VatAllocation::Split, ['withholding_amount' => '115.00', 'enasarco_amount' => '85.00']));

    $this->patchJson("/api/purchase-invoices/{$document->id}", ['notes' => 'x'])
        ->assertOk()
        ->assertJsonPath('data.withholding_amount', '115.00')
        ->assertJsonPath('data.payable_amount', '1020.00');
});

it('AC-012: the installment preview applies the withholdings', function () {
    $payload = withholdingPayload(VatAllocation::Last);

    $rows = $this->postJson('/api/purchase-invoices/installment-preview', [
        'document_date' => '2026-03-15',
        'payment_method_id' => $payload['payment_method_id'],
        'net_amount' => '1000.00',
        'vat_amount' => '220.00',
        'total_amount' => '1220.00',
        'withholding_amount' => '115.00',
        'enasarco_amount' => '85.00',
    ])->assertOk()->json('data');

    expect(round(array_sum(array_map(fn (array $row): float => (float) $row['amount'], $rows)), 2))->toBe(1020.0);
});

it('AC-012: the grid exposes, sorts and filters the payable amount', function () {
    $this->postJson('/api/purchase-invoices', withholdingPayload(VatAllocation::Split, ['withholding_amount' => '115.00', 'enasarco_amount' => '85.00']))->assertCreated();
    $this->postJson('/api/purchase-invoices', withholdingPayload(VatAllocation::Split))->assertCreated();

    $window = ['startRow' => 0, 'endRow' => 50];
    $url = '/api/tables/purchase-invoices/rows';

    $this->getJson('/api/tables/purchase-invoices/columns')->assertOk()->assertJsonFragment(['id' => 'payable_amount']);

    $rows = $this->postJson($url, $window + ['sortModel' => [['colId' => 'payable_amount', 'sort' => 'asc']]])->assertOk()->json('items');
    expect(array_column($rows, 'payable_amount'))->toBe(['1020.00', '1220.00']);

    $rows = $this->postJson($url, $window + ['sortModel' => [['colId' => 'payable_amount', 'sort' => 'desc']]])->assertOk()->json('items');
    expect(array_column($rows, 'payable_amount'))->toBe(['1220.00', '1020.00']);

    $filtered = $this->postJson($url, $window + ['filterModel' => ['payable_amount' => ['filterType' => 'number', 'type' => 'lessThan', 'filter' => 1100]]])->assertOk()->json('items');
    expect(array_column($filtered, 'payable_amount'))->toBe(['1020.00']);
});
