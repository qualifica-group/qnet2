<?php

use App\Models\Contract;
use App\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * Spec 0331 (MT-01): the read-only offer data the Contract detail projects
 * off `quote` (source, product lines, rewards, account managers + labels,
 * layout), with preventLazyLoading active (AC-005).
 */
uses(RefreshDatabase::class);

it('GET projects source, product lines, rewards, managers, labels and layout from the offer', function () {
    $quote = offerProjectionFullQuote();
    $contract = Contract::factory()->for($quote)->create();
    Sanctum::actingAs(contractScopedUserWith(['view', 'viewAll']));

    $response = $this->getJson("/api/contracts/{$contract->id}")->assertOk();

    $response
        ->assertJsonPath('data.source.id', $quote->source_id)
        ->assertJsonPath('data.source.name', $quote->source->name)
        ->assertJsonPath('data.layout.id', $quote->layout_id)
        ->assertJsonPath('data.layout.name', $quote->layout->name)
        ->assertJsonCount(1, 'data.product_lines')
        ->assertJsonPath('data.product_lines.0.id', $quote->opportunity->productLines->first()->id)
        ->assertJsonCount(1, 'data.rewards')
        ->assertJsonPath('data.rewards.0.id', $quote->rewards->first()->id)
        ->assertJsonCount(2, 'data.managers');

    $managers = $response->json('data.managers');
    expect(array_column($managers, 'position'))->toBe([1, 2])
        ->and($managers[0]['id'])->toBe($quote->managers->first()->id)
        ->and($response->json('data.manager_labels'))->toBeArray();
});

it('GET projects empty values when the offer has no source, rewards, managers or layout', function () {
    $contract = Contract::factory()->for(Quote::factory()->create())->create();
    Sanctum::actingAs(contractScopedUserWith(['view', 'viewAll']));

    $this->getJson("/api/contracts/{$contract->id}")
        ->assertOk()
        ->assertJsonPath('data.source', null)
        ->assertJsonPath('data.product_lines', [])
        ->assertJsonPath('data.rewards', [])
        ->assertJsonPath('data.managers', [])
        ->assertJsonPath('data.layout', null);
});
