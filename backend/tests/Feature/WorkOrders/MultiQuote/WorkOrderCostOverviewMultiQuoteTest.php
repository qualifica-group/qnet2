<?php

use App\Models\QuoteLine;
use App\Models\Role as RoleModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('AC-009: the budget gathers the COST lines of every source offer, allocated and generic', function () {
    [$workOrder, $quotes] = multiQuoteReadFixture();
    $lineA = $workOrder->quoteLines()->where('quote_lines.quote_id', $quotes[0]->id)->first();
    $lineB = $workOrder->quoteLines()->where('quote_lines.quote_id', $quotes[1]->id)->first();

    QuoteLine::factory()->cost()->create(['quote_id' => $quotes[0]->id, 'offer_line_id' => $lineA->id, 'net_amount' => 300, 'total_amount' => 300]);
    QuoteLine::factory()->cost()->create(['quote_id' => $quotes[1]->id, 'offer_line_id' => $lineB->id, 'net_amount' => 500, 'total_amount' => 500]);
    $generic = QuoteLine::factory()->cost()->create(['quote_id' => $quotes[1]->id, 'offer_line_id' => null, 'net_amount' => 900, 'total_amount' => 900]);
    QuoteLine::factory()->cost()->create(['quote_id' => QuoteLine::factory()->create()->quote_id, 'offer_line_id' => null, 'net_amount' => 7000, 'total_amount' => 7000]);
    Sanctum::actingAs(workOrderCostsUserWith(['viewCosts']));

    $data = $this->getJson("/api/work-orders/{$workOrder->id}/costs")->assertOk()->json('data');

    expect($data['budget']['allocated_lines'])->toHaveCount(2)
        ->and(collect($data['budget']['unallocated_lines'])->pluck('id')->all())->toBe([$generic->id])
        ->and($data['comparison']['totals']['budget_cost_net'])->toBe('800.00');
});

it('AC-006 (D-10): contract data of a multi-offer commessa lists the lines of every offer', function () {
    [$workOrder] = multiQuoteReadFixture();
    foreach (['view', 'viewContractData', 'viewAll'] as $ability) {
        Permission::findOrCreate("work-orders.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo(['work-orders.view', 'work-orders.viewContractData', 'work-orders.viewAll']);
    Sanctum::actingAs($actor);

    $data = $this->getJson("/api/work-orders/{$workOrder->id}/contract-data")->assertOk()->json('data');

    expect($data['lines'])->toHaveCount(2)->and($data['commissions_visible'])->toBeBool();
});

it('contract data of a multi-offer commessa names the client of each line', function () {
    [$workOrder] = multiQuoteReadFixture();
    foreach (['view', 'viewContractData', 'viewAll'] as $ability) {
        Permission::findOrCreate("work-orders.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo(['work-orders.view', 'work-orders.viewContractData', 'work-orders.viewAll']);
    Sanctum::actingAs($actor);

    $lines = $this->getJson("/api/work-orders/{$workOrder->id}/contract-data")->assertOk()->json('data.lines');

    expect(collect($lines)->pluck('registry.name')->sort()->values()->all())->toBe(['Alfa Srl', 'Beta Spa']);
});

it('contract data hides commissions on a multi-offer commessa when the field is not visible to the role', function () {
    [$workOrder] = multiQuoteReadFixture();
    foreach (['view', 'viewContractData', 'viewAll'] as $ability) {
        Permission::findOrCreate("work-orders.{$ability}");
    }
    $role = RoleModel::create(['name' => 'no-commissions']);
    $role->givePermissionTo(['work-orders.view', 'work-orders.viewContractData', 'work-orders.viewAll']);
    $role->fieldPermissions()->create(['resource' => 'quotes', 'field' => 'commission_value', 'visible' => false, 'editable' => false, 'required' => false]);
    $actor = User::factory()->create();
    $actor->assignRole($role);
    Sanctum::actingAs($actor);

    $data = $this->getJson("/api/work-orders/{$workOrder->id}/contract-data")->assertOk()->json('data');

    expect($data['commissions_visible'])->toBeFalse()
        ->and($data['totals']['commissions_amount'])->toBeNull()
        ->and($data['lines'][0]['commissions_amount'])->toBeNull();
});
