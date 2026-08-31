<?php

use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * GET /api/quotes/for-select (ADR 0011), spec 0059 amendment A-01: the option
 * source behind the `rewarded-referents` "Offerta" advanced filter. Mirrors
 * the opportunities/for-select contract — search, pagination, edit-mode id
 * hydration — with `code` + `title` as the searchable pair.
 */
uses(RefreshDatabase::class);

it('returns {id, label} pairing the code with the title, ordered by code', function () {
    Quote::factory()->create(['code' => 'QUO-0002', 'title' => 'Seconda']);
    Quote::factory()->create(['code' => 'QUO-0001', 'title' => 'Prima']);
    Sanctum::actingAs(User::factory()->create());

    $items = $this->getJson('/api/quotes/for-select')->assertOk()->json('items');

    expect(array_column($items, 'label'))->toBe(['QUO-0001 — Prima', 'QUO-0002 — Seconda'])
        ->and($items[0])->toHaveKeys(['id', 'label']);
});

it('searches by code AND by title', function () {
    $byCode = Quote::factory()->create(['code' => 'QUO-0042', 'title' => 'Irrilevante']);
    $byTitle = Quote::factory()->create(['code' => 'QUO-0099', 'title' => 'Fornitura Acme']);
    Quote::factory()->create(['code' => 'QUO-0100', 'title' => 'Altro']);
    Sanctum::actingAs(User::factory()->create());

    expect($this->getJson('/api/quotes/for-select?search=0042')->assertOk()->json('items.0.id'))
        ->toBe($byCode->id);
    expect($this->getJson('/api/quotes/for-select?search=Acme')->assertOk()->json('items.0.id'))
        ->toBe($byTitle->id);
});

it('hydrates the ids the client already holds without inflating the total', function () {
    $searchable = Quote::factory()->create(['code' => 'QUO-0001', 'title' => 'Acme']);
    $held = Quote::factory()->create(['code' => 'QUO-0500', 'title' => 'Fuori ricerca']);
    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson("/api/quotes/for-select?search=Acme&ids[]={$held->id}")->assertOk();

    expect(array_column($response->json('items'), 'id'))->toBe([$searchable->id, $held->id])
        ->and($response->json('pagination.total'))->toBe(1);
});

it('rejects an unauthenticated caller', function () {
    $this->getJson('/api/quotes/for-select')->assertUnauthorized();
});
