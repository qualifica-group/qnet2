<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\QuoteLine;
use App\Models\WorkOrder;
use App\Models\WorkOrderLinePayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

const MULTI_CREATE_URL = '/api/contracts/multi-quote-work-orders';

it('AC-004: two won contracts of two customers create ONE multi-quote work order', function () {
    [$contractA, $lineA] = multiQuoteProgramContract();
    $product = Product::factory()->create(['category_id' => $lineA->product->category_id]);
    [$contractB, $lineB] = multiQuoteProgramContract($product);
    Sanctum::actingAs(multiQuoteProgramActor());

    $response = $this->postJson(MULTI_CREATE_URL, multiQuoteProgramPayload([$contractA->id, $contractB->id], [$lineA->id, $lineB->id]))->assertCreated();

    $response->assertJsonPath('success', true)->assertJsonPath('message', 'Created')
        ->assertJsonPath('data.quote_id', null)->assertJsonPath('data.is_multi_quote', true)
        ->assertJsonStructure(['permissions']);
    $workOrder = WorkOrder::query()->findOrFail($response->json('data.id'));

    expect($response->json('data.code'))->toBe('COM-0001')
        ->and($workOrder->quoteLines()->pluck('quote_lines.id')->sort()->values()->all())->toBe(collect([$lineA->id, $lineB->id])->sort()->values()->all())
        ->and(collect($response->json('data.source_quotes'))->pluck('id')->sort()->values()->all())
        ->toBe(collect([$contractA->quote_id, $contractB->quote_id])->sort()->values()->all())
        ->and(collect($response->json('data.source_quotes'))->pluck('registry.id')->filter()->unique())->toHaveCount(2)
        ->and(WorkOrder::count())->toBe(1);
});

it('AC-003: POST answers 403 without contracts.program and 422 with a non-won contract, creating nothing', function () {
    [$contractA, $lineA] = multiQuoteProgramContract();
    [$contractB, $lineB] = multiQuoteProgramContract();
    [$notWon, $lineC] = multiQuoteProgramContract(null, 'new');

    Sanctum::actingAs(multiQuoteProgramActor(['contracts.view']));
    $this->postJson(MULTI_CREATE_URL, multiQuoteProgramPayload([$contractA->id, $contractB->id], [$lineA->id, $lineB->id]))->assertForbidden();

    Sanctum::actingAs(multiQuoteProgramActor());
    $this->postJson(MULTI_CREATE_URL, multiQuoteProgramPayload([$contractA->id, $notWon->id], [$lineA->id, $lineC->id]))
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('contract_ids');

    expect(WorkOrder::count())->toBe(0);
});

it('AC-005: 422 on quote_line_ids and nothing created', function (string $case) {
    $flagged = ProductCategory::factory()->create(['multi_quote_work_order' => true]);
    $plain = ProductCategory::factory()->create(['multi_quote_work_order' => false]);
    $otherBranch = ProductCategory::factory()->create(['multi_quote_work_order' => true]);
    $product = fn (ProductCategory $category): Product => Product::factory()->create(['category_id' => $category->id]);

    [$contractA, $lineA] = multiQuoteProgramContract($product($flagged));
    [$contractB, $lineB] = multiQuoteProgramContract($product($flagged));
    [$contractC, $lineC] = multiQuoteProgramContract($product($flagged));

    $ids = match ($case) {
        // REQUIREMENT CHANGED (spec 0267, D-14): a contract with no flagged line is now refused on
        // contract_ids (MultiQuoteContractEligibilityTest); here the plain line sits on an allowed contract.
        'non-flagged branch' => [$lineA->id, QuoteLine::factory()->create(['quote_id' => $contractB->quote_id, 'product_id' => $product($plain)->id, 'sort_order' => 2])->id],
        'two branches' => [$lineA->id, multiQuoteProgramContract($product($otherBranch))[1]->id],
        'single quote' => [$lineA->id, QuoteLine::factory()->create(['quote_id' => $contractA->quote_id, 'product_id' => $lineA->product_id])->id],
        'cost line' => [$lineA->id, QuoteLine::factory()->cost()->create(['quote_id' => $contractB->quote_id, 'product_id' => $lineB->product_id])->id],
        'unselected quote' => [$lineA->id, $lineC->id],
    };
    $selected = $case === 'two branches'
        ? [$contractA->id, QuoteLine::query()->findOrFail($ids[1])->quote->contract->id]
        : [$contractA->id, $contractB->id];
    Sanctum::actingAs(multiQuoteProgramActor());

    $this->postJson(MULTI_CREATE_URL, multiQuoteProgramPayload($selected, $ids))->assertStatus(422)->assertJsonValidationErrors('quote_line_ids');

    expect(WorkOrder::count())->toBe(0);
})->with(['non-flagged branch', 'two branches', 'single quote', 'cost line', 'unselected quote']);

it('AC-006: a line already programmed gives 422 naming the occupying COM- code', function () {
    [$contractA, $lineA] = multiQuoteProgramContract();
    [$contractB, $lineB] = multiQuoteProgramContract(Product::factory()->create(['category_id' => $lineA->product->category_id]));
    WorkOrder::factory()->create(['quote_id' => $contractA->quote_id, 'code' => 'COM-0099'])->quoteLines()->attach($lineA->id);
    Sanctum::actingAs(multiQuoteProgramActor());

    $response = $this->postJson(MULTI_CREATE_URL, multiQuoteProgramPayload([$contractA->id, $contractB->id], [$lineA->id, $lineB->id]))
        ->assertStatus(422)->assertJsonValidationErrors('quote_line_ids');

    expect($response->json('errors.quote_line_ids.0'))->toContain('COM-0099')
        ->and(WorkOrder::count())->toBe(1);
});

it('validates the body: contract_ids and at least two distinct lines', function () {
    [$contractA, $lineA] = multiQuoteProgramContract();
    [$contractB] = multiQuoteProgramContract();
    Sanctum::actingAs(multiQuoteProgramActor());

    $this->postJson(MULTI_CREATE_URL, multiQuoteProgramPayload([$contractA->id], [$lineA->id, $lineA->id + 1]))->assertStatus(422)->assertJsonValidationErrorFor('contract_ids');
    $this->postJson(MULTI_CREATE_URL, multiQuoteProgramPayload([$contractA->id, $contractA->id], [$lineA->id, $lineA->id + 1]))->assertStatus(422);
    $this->postJson(MULTI_CREATE_URL, multiQuoteProgramPayload(range(1, 51), [$lineA->id, $lineA->id + 1]))->assertStatus(422)->assertJsonValidationErrorFor('contract_ids');
    $this->postJson(MULTI_CREATE_URL, multiQuoteProgramPayload([$contractA->id, $contractB->id], [$lineA->id]))->assertStatus(422)->assertJsonValidationErrorFor('quote_line_ids');
    $this->postJson(MULTI_CREATE_URL, multiQuoteProgramPayload([$contractA->id, $contractB->id], [$lineA->id, $lineA->id]))->assertStatus(422);
    expect(WorkOrder::count())->toBe(0);
});

it('AC-007: PATCH keeps a subset (removed line payments go away); new line or empty list gives 422', function () {
    [$contractA, $lineA] = multiQuoteProgramContract();
    [$contractB, $lineB] = multiQuoteProgramContract(Product::factory()->create(['category_id' => $lineA->product->category_id]));
    $extra = QuoteLine::factory()->create(['quote_id' => $contractA->quote_id, 'product_id' => $lineA->product_id]);
    $workOrder = WorkOrder::factory()->multiQuote()->create();
    $workOrder->quoteLines()->attach([$lineA->id, $lineB->id]);
    WorkOrderLinePayment::factory()->create(['work_order_id' => $workOrder->id, 'quote_line_id' => $lineB->id]);
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['quote_line_ids' => [$lineA->id, $extra->id]])
        ->assertStatus(422)->assertJsonValidationErrors('quote_line_ids');
    $this->patchJson("/api/work-orders/{$workOrder->id}", ['quote_line_ids' => []])
        ->assertStatus(422)->assertJsonValidationErrors('quote_line_ids');
    expect($workOrder->quoteLines()->count())->toBe(2);

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['quote_line_ids' => [$lineA->id]])->assertOk();

    expect($workOrder->quoteLines()->pluck('quote_lines.id')->all())->toBe([$lineA->id])
        ->and(WorkOrderLinePayment::query()->where('work_order_id', $workOrder->id)->count())->toBe(0)
        ->and($workOrder->fresh()->quote_id)->toBeNull();
});

it('the for-select registry filter finds a multi-quote work order through its lines', function () {
    [$workOrder, $quotes] = multiQuoteReadFixture();
    $registryId = $quotes[1]->opportunity->registry_id;
    Sanctum::actingAs(workOrderUserWith(['view']));

    $response = $this->getJson("/api/work-orders/for-select?registry_id={$registryId}")->assertOk();

    expect(collect($response->json('items'))->pluck('id')->all())->toContain($workOrder->id);
});
