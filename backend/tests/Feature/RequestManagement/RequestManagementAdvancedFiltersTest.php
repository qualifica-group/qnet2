<?php

use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * AC-013: the advanced filters retargeted onto `opportunity.*` by spec 0086
 * (D-2 — neither `registry` nor `referent` lives on `quotes`).
 * `operational_site` and `next_callback_range` are already covered elsewhere
 * (RequestManagementTableTest/RequestManagementCallbackTest); this file
 * closes the two that needed the dot-path rewrite and were left unverified:
 * `registry`, `referent`. The former `expected_close_range` filter was
 * removed on user directive 2026-09-10 (see the guard below).
 *
 * AC-014: the grid's default sort, `quotes.created_at desc` (a real column on
 * the row's own table now, AC-014).
 */
uses(RefreshDatabase::class);

if (! function_exists('advancedFiltersActor')) {
    function advancedFiltersActor(): User
    {
        foreach (['viewAny', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['request-management.viewAny', 'request-management.viewAll']);

        return $user;
    }
}

it('AC-013: the registry advanced filter matches through quote.opportunity.registry', function () {
    $actor = advancedFiltersActor();
    $wanted = Registry::factory()->create();
    $matching = Quote::factory()->for(Opportunity::factory()->state(['registry_id' => $wanted->id]))->create();
    $excluded = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'advancedFilters' => ['registry' => [$wanted->id]],
    ])->assertOk();

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids->all())->toBe([$matching->id])
        ->and($ids)->not->toContain($excluded->id);
});

it('AC-013: the referent advanced filter matches through quote.opportunity.referent', function () {
    $actor = advancedFiltersActor();
    $wanted = Referent::factory()->create();
    $matching = Quote::factory()->for(Opportunity::factory()->state(['referent_id' => $wanted->id]))->create();
    $excluded = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'advancedFilters' => ['referent' => [$wanted->id]],
    ])->assertOk();

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids->all())->toBe([$matching->id])
        ->and($ids)->not->toContain($excluded->id);
});

it('AC-014: with no sortModel, rows are ordered by quotes.created_at descending', function () {
    $actor = advancedFiltersActor();
    $older = Quote::factory()->create();
    $older->forceFill(['created_at' => now()->subDays(2)])->save();
    $newer = Quote::factory()->create();
    $newer->forceFill(['created_at' => now()->subDay()])->save();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk();

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids->all())->toBe([$newer->id, $older->id]);
});

it('the expected_close_range advanced filter is no longer exposed, with or without a product category', function () {
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs(advancedFiltersActor());

    foreach (['', "?product_category_id={$category->id}"] as $query) {
        $names = collect($this->getJson("/api/tables/request-management/columns{$query}")
            ->assertOk()->json('data.advancedFilters'))->pluck('name');

        expect($names)->not->toContain('expected_close_range');
    }
});
