<?php

use App\Models\CostType;
use App\Models\VatRate;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-004: a saved document computes the VAT once per rate and splits it over the lines', function () {
    Sanctum::actingAs(purchaseInvoiceUserWith(['viewAny', 'view', 'create']));
    $vat22 = VatRate::factory()->create(['rate' => '22.00']);
    $vat10 = VatRate::factory()->create(['rate' => '10.00']);
    $costType = CostType::factory()->create();
    $workOrder = WorkOrder::factory()->create();
    $line = fn (string $price, ?int $vatId): array => [
        'description' => 'Row', 'quantity' => '1', 'unit_price' => $price, 'vat_rate_id' => $vatId,
        'cost_type_id' => $costType->id, 'work_order_id' => $workOrder->id,
    ];

    $data = $this->postJson('/api/purchase-invoices', purchaseInvoicePayload(['lines' => [
        $line('10.01', $vat22->id), $line('10.01', $vat22->id), $line('10.02', $vat22->id), $line('5.55', $vat10->id), $line('3.00', null),
    ]]))->assertCreated()
        ->assertJsonPath('data.net_amount', '38.59')
        ->assertJsonPath('data.vat_amount', '7.17')
        ->assertJsonPath('data.total_amount', '45.76')
        ->json('data');

    expect(array_column($data['lines'], 'vat_amount'))->toBe(['2.20', '2.20', '2.21', '0.56', '0.00'])
        ->and(array_sum(array_map('floatval', array_column($data['lines'], 'vat_amount'))))->toEqualWithDelta(7.17, 0.0001);
});
