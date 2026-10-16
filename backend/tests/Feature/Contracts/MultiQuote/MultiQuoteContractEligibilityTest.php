<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function plainBranchProduct(): Product
{
    return Product::factory()->create(['category_id' => ProductCategory::factory()->create(['multi_quote_work_order' => false])->id]);
}

it('AC-019: GET lines and POST refuse with 422 on contract_ids when a contract does not allow it, nothing created', function () {
    [$allowed, $lineA] = multiQuoteProgramContract();
    [$refused, $lineB] = multiQuoteProgramContract(plainBranchProduct());
    Sanctum::actingAs(multiQuoteProgramActor());
    $ids = [$allowed->id, $refused->id];

    $this->getJson('/api/contracts/multi-programmable-lines?'.http_build_query(['contract_ids' => $ids]))
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('contract_ids');
    $this->postJson('/api/contracts/multi-quote-work-orders', multiQuoteProgramPayload($ids, [$lineA->id, $lineB->id]))
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('contract_ids');

    expect(WorkOrder::query()->count())->toBe(0);
});

it('AC-019: authorization is checked before eligibility (403 wins over 422)', function () {
    [$allowed] = multiQuoteProgramContract();
    [$refused] = multiQuoteProgramContract(plainBranchProduct(), 'new');
    Sanctum::actingAs(multiQuoteProgramActor(['contracts.view']));

    $this->getJson('/api/contracts/multi-programmable-lines?'.http_build_query(['contract_ids' => [$allowed->id, $refused->id]]))
        ->assertForbidden();
});

it('AC-003: a not-won contract is refused before the eligibility check', function () {
    [$allowed] = multiQuoteProgramContract();
    [$refused] = multiQuoteProgramContract(plainBranchProduct(), 'new');
    Sanctum::actingAs(multiQuoteProgramActor());

    $this->getJson('/api/contracts/multi-programmable-lines?'.http_build_query(['contract_ids' => [$allowed->id, $refused->id]]), ['Accept-Language' => 'it'])
        ->assertStatus(422)
        ->assertJsonPath('errors.contract_ids.0', __('work_orders.multi_quote.contracts_not_won', ['codes' => $refused->quote->code.' ('.$refused->quote->opportunity->registry->name.')'], 'it'));
});

it('AC-019: the refusal explains in the user language which contracts and why', function () {
    [$allowed] = multiQuoteProgramContract();
    [$refused] = multiQuoteProgramContract(plainBranchProduct());
    Sanctum::actingAs(multiQuoteProgramActor());

    $this->getJson('/api/contracts/multi-programmable-lines?'.http_build_query(['contract_ids' => [$allowed->id, $refused->id]]), ['Accept-Language' => 'it'])
        ->assertStatus(422)
        ->assertJsonPath('errors.contract_ids.0', __('work_orders.multi_quote.contracts_not_allowed', ['codes' => $refused->quote->code.' ('.$refused->quote->opportunity->registry->name.')'], 'it'));
});
