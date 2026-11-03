<?php

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * GET /api/purchase-requests/for-select (ADR 0011, spec 0347 AC-018): rows are
 * restricted by PurchaseRequestVisibilityScope like the index (D-16).
 */
uses(RefreshDatabase::class);

it('returns {id, label, subtitle} with the subject and requester plus date', function () {
    $requester = User::factory()->create(['name' => 'Mario Rossi']);
    $request = PurchaseRequest::factory()->create(['subject' => 'Portatili', 'requested_at' => '2026-05-09', 'requester_id' => $requester->id]);
    Sanctum::actingAs($requester);

    $items = $this->getJson('/api/purchase-requests/for-select')->assertOk()->json('items');

    expect($items[0])->toMatchArray(['id' => $request->id, 'label' => 'Portatili', 'subtitle' => 'Mario Rossi · 2026-05-09']);
});

it('shows only own requests without viewAll, every request with viewAll', function () {
    $actor = purchaseRequestUserWith(['view']);
    $own = PurchaseRequest::factory()->create(['requester_id' => $actor->id]);
    $managed = PurchaseRequest::factory()->create(['function_manager_id' => $actor->id]);
    $authored = PurchaseRequest::factory()->create(['created_by' => $actor->id]);
    $foreign = PurchaseRequest::factory()->create();
    Sanctum::actingAs($actor);

    $ids = array_column($this->getJson('/api/purchase-requests/for-select')->assertOk()->json('items'), 'id');
    expect($ids)->toEqualCanonicalizing([$own->id, $managed->id, $authored->id]);

    // ids[] hydration must not reveal a row the scope hides.
    $hydrated = $this->getJson("/api/purchase-requests/for-select?ids[]={$foreign->id}")->assertOk();
    expect(array_column($hydrated->json('items'), 'id'))->not->toContain($foreign->id);

    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll']));
    expect(array_column($this->getJson('/api/purchase-requests/for-select')->json('items'), 'id'))->toContain($foreign->id);
});

it('searches by subject and requester name', function () {
    $bySubject = PurchaseRequest::factory()->create(['subject' => 'Sedie ergonomiche']);
    $byRequester = PurchaseRequest::factory()->create(['subject' => 'Altro', 'requester_id' => User::factory()->create(['name' => 'Giulia Bianchi'])->id]);
    PurchaseRequest::factory()->create(['subject' => 'Estraneo']);
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll']));

    expect(array_column($this->getJson('/api/purchase-requests/for-select?search=Sedie')->json('items'), 'id'))->toBe([$bySubject->id]);
    expect(array_column($this->getJson('/api/purchase-requests/for-select?search=Giulia')->json('items'), 'id'))->toBe([$byRequester->id]);
});

it('paginates, skips the count with include_total=0 and hydrates ids[]', function () {
    $requests = PurchaseRequest::factory()->count(3)->create();
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll']));

    $page = $this->getJson('/api/purchase-requests/for-select?limit=2')->assertOk();
    expect($page->json('items'))->toHaveCount(2)->and($page->json('pagination.total'))->toBe(3);

    $noTotal = $this->getJson('/api/purchase-requests/for-select?limit=2&include_total=0')->assertOk();
    expect($noTotal->json('pagination.total'))->toBeNull()->and($noTotal->json('pagination.has_more'))->toBeTrue();

    $held = $requests->first()->id;
    $hydrated = $this->getJson("/api/purchase-requests/for-select?search=zzz-no-match&ids[]={$held}")->assertOk();
    expect(array_column($hydrated->json('items'), 'id'))->toBe([$held])->and($hydrated->json('pagination.total'))->toBe(0);
});

it('rejects an unauthenticated caller', function () {
    $this->getJson('/api/purchase-requests/for-select')->assertUnauthorized();
});
