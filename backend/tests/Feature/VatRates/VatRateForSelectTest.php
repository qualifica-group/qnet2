<?php

use App\Models\User;
use App\Models\VatRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('vatRateUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function vatRateUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("vat-rates.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("vat-rates.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// auth + authorization
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->getJson('/api/vat-rates/for-select')->assertUnauthorized();
});

it('allows actors without vat-rates.viewAny (200 — ADR 0011 amended)', function () {
    $actor = vatRateUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/vat-rates/for-select')->assertOk();
});

it('allows actors with vat-rates.viewAny (200) and returns the paginated envelope', function () {
    $actor = vatRateUserWith(['viewAny']);
    VatRate::factory()->count(3)->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/vat-rates/for-select')
        ->assertOk()
        ->assertJsonStructure([
            'items' => [['id', 'label']],
            'export_link',
            'pagination' => ['total', 'offset', 'limit', 'total_pages'],
        ]);
});

// ---------------------------------------------------------------------------
// item shape + search
// ---------------------------------------------------------------------------

it('maps a vat rate to { id, label: name, meta: { rate } }', function () {
    $actor = vatRateUserWith(['viewAny']);
    $target = VatRate::factory()->create(['name' => 'IVA 22%', 'rate' => 22]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/vat-rates/for-select?search=IVA 22%')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item)->toMatchArray(['id' => $target->id, 'label' => 'IVA 22%'])
        ->and(array_keys($item))->toEqualCanonicalizing(['id', 'label', 'meta'])
        ->and($item['meta'])->toMatchArray(['rate' => '22.00']);
});

it('exposes meta.rate on every item without altering id/label/subtitle', function () {
    $actor = vatRateUserWith(['viewAny']);
    VatRate::factory()->count(3)->create();
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/vat-rates/for-select')->assertOk();

    collect($response->json('items'))->each(function (array $item) {
        expect($item)->toHaveKeys(['id', 'label', 'meta'])
            ->and($item['meta'])->toHaveKey('rate');
    });
});

it('searches by name', function () {
    $actor = vatRateUserWith(['viewAny']);
    $match = VatRate::factory()->create(['name' => 'Alphonse Target']);
    VatRate::factory()->create(['name' => 'Someone Else']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/vat-rates/for-select?search=Alphonse')->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($match->id);
});

// ---------------------------------------------------------------------------
// ids[] hydration + pagination
// ---------------------------------------------------------------------------

it('appends ids[] even when filtered out by search and does NOT inflate total', function () {
    $actor = vatRateUserWith(['viewAny']);
    $searchMatch = VatRate::factory()->create(['name' => 'Zephyr Searchable']);
    $selected = VatRate::factory()->create(['name' => 'Quentin Selected']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/vat-rates/for-select?search=Zephyr&ids[]={$selected->id}")->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($searchMatch->id)
        ->and($ids)->toContain($selected->id)
        ->and($response->json('pagination.total'))->toBe(1);
});

it('rejects a limit above 100 (422)', function () {
    $actor = vatRateUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/vat-rates/for-select?limit=101')
        ->assertStatus(422)->assertJsonValidationErrors('limit');
});
