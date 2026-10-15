<?php

use App\Models\Contract;
use App\Models\Quote;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-008: a multi-offer commessa exposes null single-offer fields and every source offer ordered by code', function () {
    [$workOrder, $quotes] = multiQuoteReadFixture();
    Sanctum::actingAs(workOrderUserWith(['view']));

    $response = $this->getJson("/api/work-orders/{$workOrder->id}")->assertOk();

    $response->assertJsonPath('data.is_multi_quote', true)
        ->assertJsonPath('data.quote', null)
        ->assertJsonPath('data.contract', null)
        ->assertJsonPath('data.contract_number', null)
        ->assertJsonPath('data.registry', null)
        ->assertJsonPath('data.company', null)
        ->assertJsonPath('data.company_site', null)
        ->assertJsonPath('data.operational_site', null);

    $sources = collect($response->json('data.source_quotes'));
    $expected = collect($quotes)->sortBy('code')->values();

    expect($sources->pluck('id')->all())->toBe($expected->pluck('id')->all())
        ->and($sources->first()['contract'])->toBe(['id' => Contract::query()->where('quote_id', $expected[0]->id)->value('id')])
        ->and($sources->pluck('registry.name')->sort()->values()->all())->toBe(['Alfa Srl', 'Beta Spa'])
        ->and($sources->first())->toHaveKeys(['id', 'code', 'title', 'contract', 'registry', 'company']);
});

it('each line of a multi-offer commessa carries the client of its own offer', function () {
    [$workOrder, $quotes] = multiQuoteReadFixture();
    Sanctum::actingAs(workOrderUserWith(['view']));

    $lines = collect($this->getJson("/api/work-orders/{$workOrder->id}")->assertOk()->json('data.quote_lines'));
    $expected = collect($quotes)->mapWithKeys(fn (Quote $quote): array => [
        $quote->lines()->value('id') => ['id' => $quote->opportunity->registry_id, 'name' => $quote->opportunity->registry->name],
    ]);

    expect($lines->mapWithKeys(fn (array $line): array => [$line['id'] => $line['registry']])->all())->toEqual($expected->all());
});

it('a mono-offer commessa keeps its fields and lists exactly one source offer', function () {
    $quote = Quote::factory()->create();
    $contract = Contract::factory()->create(['quote_id' => $quote->id]);
    $workOrder = WorkOrder::factory()->create(['quote_id' => $quote->id]);
    Sanctum::actingAs(workOrderUserWith(['view']));

    $response = $this->getJson("/api/work-orders/{$workOrder->id}")->assertOk();

    $response->assertJsonPath('data.is_multi_quote', false)
        ->assertJsonPath('data.contract_number', $quote->code)
        ->assertJsonPath('data.quote.id', $quote->id)
        ->assertJsonPath('data.contract.id', $contract->id)
        ->assertJsonCount(1, 'data.source_quotes')
        ->assertJsonPath('data.source_quotes.0.id', $quote->id)
        ->assertJsonPath('data.source_quotes.0.contract.id', $contract->id)
        ->assertJsonPath('data.source_quotes.0.registry.id', $quote->opportunity->registry_id);
});

it('the multi-offer detail is denied without work-orders.view', function () {
    [$workOrder] = multiQuoteReadFixture();
    Sanctum::actingAs(workOrderUserWith([]));

    $this->getJson("/api/work-orders/{$workOrder->id}")->assertForbidden();
});

it('for-select copes with a multi-offer commessa: registry meta is null', function () {
    [$workOrder] = multiQuoteReadFixture();
    Sanctum::actingAs(workOrderUserWith(['viewAny']));

    $response = $this->getJson('/api/work-orders/for-select')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $workOrder->id);

    expect($item['meta']['registry'])->toBeNull()->and($item['meta']['registry_id'])->toBeNull();
});
