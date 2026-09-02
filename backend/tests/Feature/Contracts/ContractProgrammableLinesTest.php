<?php

use App\Enums\QuoteLineType;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\QuoteLine;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * GET /api/contracts/{contract}/programmable-lines (spec 0095, D-6):
 * AC-020/021/022, plus the doubled gate (`contracts.program` ANDed with the
 * ClosedWon-group lifecycle, ContractActionAvailability::mayProgram()).
 */
uses(RefreshDatabase::class);

if (! function_exists('programmableLinesActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function programmableLinesActorWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'program'] as $ability) {
            Permission::findOrCreate("contracts.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("contracts.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('programmableContract')) {
    function programmableContract(): Contract
    {
        return Contract::factory()->create([
            'contract_status_id' => ContractStatus::where('system_key', 'validated')->sole()->id,
        ]);
    }
}

it('AC-020: returns only REVENUE lines, ordered by sort_order, with product/category/quantity/unit_of_measure', function () {
    $contract = programmableContract();
    $category = ProductCategory::factory()->create(['name' => 'Formazione']);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $unit = UnitOfMeasure::factory()->create(['name' => 'Ore', 'symbol' => 'h']);

    $second = QuoteLine::factory()->create([
        'quote_id' => $contract->quote_id, 'product_id' => $product->id,
        'unit_of_measure_id' => $unit->id, 'quantity' => 3, 'sort_order' => 2,
    ]);
    $first = QuoteLine::factory()->create([
        'quote_id' => $contract->quote_id, 'product_id' => $product->id,
        'unit_of_measure_id' => $unit->id, 'quantity' => 1, 'sort_order' => 1,
    ]);
    QuoteLine::factory()->cost()->create(['quote_id' => $contract->quote_id, 'sort_order' => 0]);

    $actor = programmableLinesActorWith(['program']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/contracts/{$contract->id}/programmable-lines")->assertOk();
    $data = $response->json('data');

    expect($data)->toHaveCount(2)
        ->and(collect($data)->pluck('id')->all())->toBe([$first->id, $second->id])
        ->and($data[0]['product']['category']['name'])->toBe('Formazione')
        ->and($data[0]['unit_of_measure'])->toBe(['id' => $unit->id, 'name' => 'Ore', 'symbol' => 'h'])
        ->and($data[0]['quantity'])->toBe('1.00');
});

it('AC-021: a line already used by a work order carries {id, code}; a free one carries null', function () {
    $contract = programmableContract();
    $occupied = QuoteLine::factory()->create(['quote_id' => $contract->quote_id, 'sort_order' => 1]);
    $free = QuoteLine::factory()->create(['quote_id' => $contract->quote_id, 'sort_order' => 2]);
    $workOrder = WorkOrder::factory()->create(['quote_id' => $contract->quote_id, 'code' => 'COM-0042']);
    $workOrder->quoteLines()->attach($occupied->id);

    $actor = programmableLinesActorWith(['program']);
    Sanctum::actingAs($actor);

    $data = $this->getJson("/api/contracts/{$contract->id}/programmable-lines")->assertOk()->json('data');
    $byId = collect($data)->keyBy('id');

    expect($byId[$occupied->id]['work_order'])->toBe(['id' => $workOrder->id, 'code' => 'COM-0042'])
        ->and($byId[$free->id]['work_order'])->toBeNull();
});

it('AC-022: COST lines never appear', function () {
    $contract = programmableContract();
    $costLine = QuoteLine::factory()->cost()->create(['quote_id' => $contract->quote_id]);

    $actor = programmableLinesActorWith(['program']);
    Sanctum::actingAs($actor);

    $data = $this->getJson("/api/contracts/{$contract->id}/programmable-lines")->assertOk()->json('data');

    expect(collect($data)->pluck('id')->all())->not->toContain($costLine->id)
        ->and(QuoteLine::query()->where('id', $costLine->id)->value('line_type'))->toBe(QuoteLineType::Cost);
});

it('403 without contracts.program', function () {
    $contract = programmableContract();
    $actor = programmableLinesActorWith([]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/contracts/{$contract->id}/programmable-lines")->assertForbidden();
});

it('403 when the contract is not in the ClosedWon group, even with contracts.program', function () {
    $contract = Contract::factory()->create([
        'contract_status_id' => ContractStatus::where('name', 'Programmato')->sole()->id,
    ]);
    $actor = programmableLinesActorWith(['program']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/contracts/{$contract->id}/programmable-lines")->assertForbidden();
});

it('404 on an unknown contract id', function () {
    $actor = programmableLinesActorWith(['program']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/contracts/999999/programmable-lines')->assertNotFound();
});

it('401 when unauthenticated', function () {
    $contract = programmableContract();

    $this->getJson("/api/contracts/{$contract->id}/programmable-lines")->assertUnauthorized();
});
