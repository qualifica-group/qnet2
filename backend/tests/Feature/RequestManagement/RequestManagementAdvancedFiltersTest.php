<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * AC-013: the advanced filters retargeted onto `opportunity.*` by spec 0086
 * (D-2 — neither `registry`/`referent`/`expected_close_date` lives on
 * `quotes`). `operational_site` and `next_callback_range` are already
 * covered elsewhere (RequestManagementTableTest/RequestManagementCallbackTest);
 * this file closes the three that needed the dot-path/whereHas rewrite and
 * were left unverified: `registry`, `referent`, `expected_close_range`.
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

it('AC-013: the expected_close_range advanced filter narrows by quote.opportunity.expected_close_date', function () {
    $actor = advancedFiltersActor();
    $inRange = Quote::factory()->for(Opportunity::factory()->state(['expected_close_date' => '2026-08-03']))->create();
    $outOfRange = Quote::factory()->for(Opportunity::factory()->state(['expected_close_date' => '2026-09-15']))->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'advancedFilters' => ['expected_close_range' => ['from' => '2026-08-01', 'to' => '2026-08-05']],
    ])->assertOk();

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids->all())->toBe([$inRange->id])
        ->and($ids)->not->toContain($outOfRange->id);
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
