<?php

use App\Models\CostType;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrderLine;
use App\Models\WorkOrderCost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-001: a cost type used by a purchase invoice cannot be deleted', function () {
    Sanctum::actingAs(costTypeUserWith(['delete']));
    $costType = CostType::factory()->create();
    $invoice = PurchaseInvoice::factory()->create(['cost_type_id' => $costType->id]);

    $this->deleteJson("/api/cost-types/{$costType->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', __('cost_types.in_use'));

    expect($invoice->fresh()->cost_type_id)->toBe($costType->id);
});

it('AC-002: a cost type used by a purchase order line cannot be deleted', function () {
    Sanctum::actingAs(costTypeUserWith(['delete']));
    $line = PurchaseOrderLine::factory()->create();

    $this->deleteJson("/api/cost-types/{$line->cost_type_id}")
        ->assertStatus(409)
        ->assertJsonPath('message', __('cost_types.in_use'));
});

it('AC-006: a cost type used by a product or a commessa cost cannot be deleted', function () {
    Sanctum::actingAs(costTypeUserWith(['viewAny', 'view', 'delete']));
    $byProduct = CostType::factory()->create(['name' => 'ByProduct']);
    Product::factory()->create(['cost_type_id' => $byProduct->id]);
    $byCost = CostType::factory()->create(['name' => 'ByCost']);
    WorkOrderCost::factory()->create(['cost_type_id' => $byCost->id]);

    foreach ([$byProduct, $byCost] as $costType) {
        $this->deleteJson("/api/cost-types/{$costType->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', __('cost_types.in_use'));
    }

    $rows = collect($this->postJson('/api/tables/cost-types/rows', ['startRow' => 0, 'endRow' => 50])
        ->assertOk()->json('items'));

    expect($rows->firstWhere('id', $byProduct->id)['actions'])->not->toContain('delete')
        ->and($rows->firstWhere('id', $byCost->id)['actions'])->not->toContain('delete');
});

it('AC-003: an unused cost type is deleted', function () {
    Sanctum::actingAs(costTypeUserWith(['delete']));
    $costType = CostType::factory()->create();

    $this->deleteJson("/api/cost-types/{$costType->id}")->assertNoContent();

    $this->assertDatabaseMissing('cost_types', ['id' => $costType->id]);
});

it('AC-001: the generic bulk delete refuses a cost type in use and removes the free one', function () {
    Sanctum::actingAs(costTypeUserWith(['viewAny', 'delete']));
    $used = CostType::factory()->create();
    PurchaseInvoice::factory()->create(['cost_type_id' => $used->id]);
    $free = CostType::factory()->create();

    $response = $this->postJson('/api/tables/cost-types/bulk-delete', ['ids' => [$used->id, $free->id]])->assertOk();

    expect($response->json('data.deleted'))->toBe(1);
    $this->assertDatabaseHas('cost_types', ['id' => $used->id]);
    $this->assertDatabaseMissing('cost_types', ['id' => $free->id]);
});

it('AC-004: the grid offers the delete action only for a cost type not in use', function () {
    Sanctum::actingAs(costTypeUserWith(['viewAny', 'view', 'delete']));
    $byInvoice = CostType::factory()->create(['name' => 'ByInvoice']);
    PurchaseInvoice::factory()->create(['cost_type_id' => $byInvoice->id]);
    $byLine = PurchaseOrderLine::factory()->create()->costType;
    $free = CostType::factory()->create(['name' => 'Free']);

    $rows = collect($this->postJson('/api/tables/cost-types/rows', ['startRow' => 0, 'endRow' => 50])
        ->assertOk()->json('items'));

    expect($rows->firstWhere('id', $byInvoice->id)['actions'])->not->toContain('delete')
        ->and($rows->firstWhere('id', $byLine->id)['actions'])->not->toContain('delete')
        ->and($rows->firstWhere('id', $free->id)['actions'])->toContain('delete');
});
