<?php

use App\Models\Contract;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Registry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * GET /api/contracts/for-select (ADR 0011, spec 0347 AC-018): rows are
 * restricted by ContractVisibilityScope like the index (spec 0316, D-3).
 */
uses(RefreshDatabase::class);

it('returns {id, label, subtitle} with quote code and title, client plus acceptance date', function () {
    $registry = Registry::factory()->create(['name' => 'Cliente Spa']);
    $opportunity = Opportunity::factory()->create(['registry_id' => $registry->id]);
    $quote = Quote::factory()->create(['code' => 'QUO-0009', 'title' => 'Fornitura', 'opportunity_id' => $opportunity->id]);
    $contract = Contract::factory()->create(['quote_id' => $quote->id, 'accepted_at' => '2026-06-01']);
    Sanctum::actingAs(contractScopedUserWith(['viewAll']));

    $items = $this->getJson('/api/contracts/for-select')->assertOk()->json('items');

    expect($items[0])->toMatchArray(['id' => $contract->id, 'label' => 'QUO-0009 — Fornitura', 'subtitle' => 'Cliente Spa · 2026-06-01']);
});

it('shows only the contracts of visible quotes without viewAll, all of them with viewAll', function () {
    $actor = contractScopedUserWith([]);
    $own = Contract::factory()->create(['quote_id' => Quote::factory()->create(['supervisor_id' => $actor->id])->id]);
    $foreign = Contract::factory()->create();
    Sanctum::actingAs($actor);

    expect(array_column($this->getJson('/api/contracts/for-select')->assertOk()->json('items'), 'id'))->toBe([$own->id]);

    // ids[] hydration must not reveal a row the scope hides.
    $hydrated = $this->getJson("/api/contracts/for-select?ids[]={$foreign->id}")->assertOk();
    expect(array_column($hydrated->json('items'), 'id'))->not->toContain($foreign->id);

    Sanctum::actingAs(contractScopedUserWith(['viewAll']));
    expect(array_column($this->getJson('/api/contracts/for-select')->json('items'), 'id'))->toContain($own->id, $foreign->id);
});

it('searches by quote code and title', function () {
    $byCode = Contract::factory()->create(['quote_id' => Quote::factory()->create(['code' => 'QUO-0042', 'title' => 'x'])->id]);
    $byTitle = Contract::factory()->create(['quote_id' => Quote::factory()->create(['code' => 'QUO-0043', 'title' => 'Manutenzione Acme'])->id]);
    Contract::factory()->create();
    Sanctum::actingAs(contractScopedUserWith(['viewAll']));

    expect(array_column($this->getJson('/api/contracts/for-select?search=0042')->json('items'), 'id'))->toBe([$byCode->id]);
    expect(array_column($this->getJson('/api/contracts/for-select?search=Acme')->json('items'), 'id'))->toBe([$byTitle->id]);
});

it('paginates, skips the count with include_total=0 and hydrates ids[]', function () {
    $contracts = Contract::factory()->count(3)->create();
    Sanctum::actingAs(contractScopedUserWith(['viewAll']));

    $page = $this->getJson('/api/contracts/for-select?limit=2')->assertOk();
    expect($page->json('items'))->toHaveCount(2)->and($page->json('pagination.total'))->toBe(3);

    $noTotal = $this->getJson('/api/contracts/for-select?limit=2&include_total=0')->assertOk();
    expect($noTotal->json('pagination.total'))->toBeNull()->and($noTotal->json('pagination.has_more'))->toBeTrue();

    $held = $contracts->first()->id;
    $hydrated = $this->getJson("/api/contracts/for-select?search=zzz-no-match&ids[]={$held}")->assertOk();
    expect(array_column($hydrated->json('items'), 'id'))->toBe([$held])->and($hydrated->json('pagination.total'))->toBe(0);
});

it('rejects an unauthenticated caller', function () {
    $this->getJson('/api/contracts/for-select')->assertUnauthorized();
});
