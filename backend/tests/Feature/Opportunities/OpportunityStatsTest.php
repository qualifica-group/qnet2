<?php

use App\Models\City;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-043 — GET /api/stats/opportunities
// ---------------------------------------------------------------------------

it('403 without opportunities.viewAny (AC-043)', function () {
    $actor = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/stats/opportunities')->assertForbidden();
});

it('200 with the contract widgets: total, estimated_value, average_probability, by_operational_site, trend (AC-043)', function () {
    Permission::findOrCreate('opportunities.viewAny');
    $actor = User::factory()->create();
    $actor->givePermissionTo('opportunities.viewAny');

    $site = OperationalSite::factory()->create();
    $site->addresses()->create([
        'line1' => 'Via Roma 1',
        'is_primary' => true,
        'city_id' => City::factory()->create(['name' => 'Aversa'])->id,
    ]);
    Opportunity::factory()->create(['operational_site_id' => $site->id, 'estimated_value' => 1000, 'success_probability' => 40]);
    Opportunity::factory()->create(['operational_site_id' => $site->id, 'estimated_value' => 2000, 'success_probability' => 60]);
    Opportunity::factory()->create(['operational_site_id' => null, 'estimated_value' => null, 'success_probability' => null]);

    Sanctum::actingAs($actor);

    $widgets = collect($this->getJson('/api/stats/opportunities')->assertOk()->json('data.widgets'))->keyBy('key');

    expect($widgets['total']['value'])->toBe(3)
        ->and((float) $widgets['estimated_value']['value'])->toBe(3000.0)
        ->and($widgets['estimated_value']['format'])->toBe('currency')
        ->and((float) $widgets['average_probability']['value'])->toBe(50.0)
        ->and($widgets['by_operational_site']['type'])->toBe('distribution')
        ->and($widgets['by_operational_site']['label'])->toBe('opportunities.stats.byOperationalSite')
        ->and($widgets['by_operational_site']['items'])->toBe([
            ['key' => (string) $site->id, 'label' => 'Via Roma 1 - Aversa', 'value' => 2, 'color' => null],
        ])
        ->and($widgets['by_operational_site']['total'])->toBe(3)
        ->and($widgets['trend']['type'])->toBe('trend')
        ->and($widgets['trend']['points'])->toHaveCount(12);
});

// REQUIREMENT CHANGE (client decision, stress-test solution S6): the former
// `by_registry` breakdown averaged ~1 opportunity per registry (no signal) at
// the cost of a 1M-row GROUP BY; it is REPLACED by `by_operational_site`.
it('no longer emits the per-registry breakdown', function () {
    Permission::findOrCreate('opportunities.viewAny');
    $actor = User::factory()->create();
    $actor->givePermissionTo('opportunities.viewAny');
    Opportunity::factory()->create();

    Sanctum::actingAs($actor);

    $keys = collect($this->getJson('/api/stats/opportunities')->assertOk()->json('data.widgets'))->pluck('key');

    expect($keys)->not->toContain('by_registry');
});

it('ranks sites by opportunity count, labels sites without a city by line1 alone and keeps site-less rows out of the items', function () {
    Permission::findOrCreate('opportunities.viewAny');
    $actor = User::factory()->create();
    $actor->givePermissionTo('opportunities.viewAny');

    $busy = OperationalSite::factory()->create();
    $busy->addresses()->create(['line1' => 'Corso Italia 2', 'is_primary' => true, 'city_id' => null]);
    $quiet = OperationalSite::factory()->create();
    $quiet->addresses()->create([
        'line1' => 'Via Verdi 3',
        'is_primary' => true,
        'city_id' => City::factory()->create(['name' => 'Aversa'])->id,
    ]);
    Opportunity::factory()->count(3)->create(['operational_site_id' => $busy->id]);
    Opportunity::factory()->create(['operational_site_id' => $quiet->id]);
    Opportunity::factory()->count(2)->create(['operational_site_id' => null]);

    Sanctum::actingAs($actor);

    $widget = collect($this->getJson('/api/stats/opportunities')->assertOk()->json('data.widgets'))
        ->firstWhere('key', 'by_operational_site');

    expect(collect($widget['items'])->map(fn (array $item) => [$item['label'], $item['value']])->all())
        ->toBe([['Corso Italia 2', 3], ['Via Verdi 3 - Aversa', 1]])
        ->and($widget['total'])->toBe(6);
});

it('average_probability is null (not 0) when no opportunity has one', function () {
    Permission::findOrCreate('opportunities.viewAny');
    $actor = User::factory()->create();
    $actor->givePermissionTo('opportunities.viewAny');

    Opportunity::factory()->create(['success_probability' => null]);
    Sanctum::actingAs($actor);

    $widgets = collect($this->getJson('/api/stats/opportunities')->assertOk()->json('data.widgets'))->keyBy('key');

    expect($widgets['average_probability']['value'])->toBeNull();
});
