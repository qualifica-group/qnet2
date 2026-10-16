<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\QuoteLine;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

const MULTI_LINES_URL = '/api/contracts/multi-programmable-lines';

it('AC-002: lines carry contract, quote, branch root and eligibility flags', function () {
    $flagged = ProductCategory::factory()->create(['multi_quote_work_order' => true, 'name' => 'Ramo A']);
    $child = ProductCategory::factory()->childOf($flagged)->create(['multi_quote_work_order' => true]);
    $plain = ProductCategory::factory()->create(['multi_quote_work_order' => false]);

    [$contractA, $lineA] = multiQuoteProgramContract(Product::factory()->create(['category_id' => $child->id]));
    [$contractB, $lineB] = multiQuoteProgramContract(Product::factory()->create(['category_id' => $flagged->id]));
    $occupied = QuoteLine::factory()->create(['quote_id' => $contractB->quote_id, 'product_id' => $lineB->product_id, 'sort_order' => 2]);
    WorkOrder::factory()->create(['quote_id' => $contractB->quote_id])->quoteLines()->attach($occupied->id);
    $notFlagged = QuoteLine::factory()->create(['quote_id' => $contractB->quote_id, 'product_id' => Product::factory()->create(['category_id' => $plain->id])->id, 'sort_order' => 3]);
    QuoteLine::factory()->cost()->create(['quote_id' => $contractB->quote_id, 'product_id' => $lineB->product_id]);
    Sanctum::actingAs(multiQuoteProgramActor());

    $response = $this->getJson(MULTI_LINES_URL.'?'.http_build_query(['contract_ids' => [$contractB->id, $contractA->id]]))->assertOk();

    expect(collect($response->json('data.contracts'))->pluck('id')->all())->toBe([$contractB->id, $contractA->id])
        ->and($response->json('data.contracts.0.quote.id'))->toBe($contractB->quote_id)
        ->and($response->json('data.contracts.0.registry.name'))->toBe($contractB->quote->opportunity->registry->name);

    $lines = collect($response->json('data.lines'))->keyBy('id');
    expect($lines)->toHaveCount(4)
        ->and($lines[$lineB->id])->toMatchArray(['contract_id' => $contractB->id, 'quote_id' => $contractB->quote_id, 'multi_quote_eligible' => true, 'branch_root' => ['id' => $flagged->id, 'name' => 'Ramo A']])
        ->and($lines[$lineA->id]['branch_root']['id'])->toBe($flagged->id)
        ->and($lines[$lineA->id]['multi_quote_eligible'])->toBeTrue()
        ->and($lines[$occupied->id]['multi_quote_eligible'])->toBeFalse()
        ->and($lines[$occupied->id]['work_order']['code'])->not->toBeNull()
        ->and($lines[$notFlagged->id]['multi_quote_eligible'])->toBeFalse()
        ->and($lines[$lineB->id])->toHaveKeys(['sort_order', 'product', 'quantity', 'unit_of_measure', 'work_order']);
});

it('AC-003: 403 for a user without contracts.program', function () {
    [$a] = multiQuoteProgramContract();
    [$b] = multiQuoteProgramContract();
    Sanctum::actingAs(multiQuoteProgramActor(['contracts.view']));

    $this->getJson(MULTI_LINES_URL.'?'.http_build_query(['contract_ids' => [$a->id, $b->id]]))->assertForbidden();
});

it('AC-003: 422 on contract_ids naming every selected contract that is not won', function () {
    [$a] = multiQuoteProgramContract();
    [$b] = multiQuoteProgramContract(null, 'new');
    Sanctum::actingAs(multiQuoteProgramActor());

    $this->getJson(MULTI_LINES_URL.'?'.http_build_query(['contract_ids' => [$a->id, $b->id]]), ['Accept-Language' => 'it'])
        ->assertStatus(422)
        ->assertJsonPath('errors.contract_ids.0', __('work_orders.multi_quote.contracts_not_won', ['codes' => $b->quote->code.' ('.$b->quote->opportunity->registry->name.')'], 'it'));
});

it('AC-003: the 403 for a user without contracts.program carries a translated message', function () {
    [$a] = multiQuoteProgramContract();
    [$b] = multiQuoteProgramContract();
    Sanctum::actingAs(multiQuoteProgramActor(['contracts.view']));

    $this->getJson(MULTI_LINES_URL.'?'.http_build_query(['contract_ids' => [$a->id, $b->id]]), ['Accept-Language' => 'it'])
        ->assertForbidden()
        ->assertJsonPath('message', __('This action is unauthorized.', [], 'it'));
});

it('validates contract_ids: fewer than 2, duplicates, unknown ids and over the maximum', function () {
    [$a] = multiQuoteProgramContract();
    Sanctum::actingAs(multiQuoteProgramActor());
    $this->getJson(MULTI_LINES_URL.'?'.http_build_query(['contract_ids' => [$a->id]]))->assertStatus(422)->assertJsonValidationErrorFor('contract_ids');
    Sanctum::actingAs(multiQuoteProgramActor());

    $this->getJson(MULTI_LINES_URL.'?'.http_build_query(['contract_ids' => [$a->id, $a->id]]))->assertStatus(422);
    $this->getJson(MULTI_LINES_URL.'?'.http_build_query(['contract_ids' => [$a->id, 999999]]))->assertStatus(422);
    $this->getJson(MULTI_LINES_URL.'?'.http_build_query(['contract_ids' => range(1, 51)]))->assertStatus(422)->assertJsonValidationErrorFor('contract_ids');
    $this->getJson(MULTI_LINES_URL)->assertStatus(422);
});
