<?php

use App\Models\Opportunity;
use App\Models\Referent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * GET /api/opportunities/for-select (ADR 0011, spec 0059/MT-10): feeds the
 * `rewarded-referents` "opportunity" advanced filter. Mirrors
 * LeadForSelectTest verbatim.
 */
uses(RefreshDatabase::class);

if (! function_exists('opportunityForSelectUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function opportunityForSelectUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("opportunities.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("opportunities.{$ability}");
        }

        return $user;
    }
}

it('requires authentication (401)', function () {
    $this->getJson('/api/opportunities/for-select')->assertUnauthorized();
});

it('forbids actors without opportunities.viewAny (403)', function () {
    $actor = opportunityForSelectUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/opportunities/for-select')->assertForbidden();
});

it('200: envelope {items, export_link, pagination} with {id, label} items, filtered by search', function () {
    $actor = opportunityForSelectUserWith(['viewAny']);
    $match = Opportunity::factory()->create();
    $match->forceFill(['name' => 'OPP_Ada'])->save();
    $other = Opportunity::factory()->create();
    $other->forceFill(['name' => 'OPP_Zed'])->save();
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/opportunities/for-select?search=Ada')->assertOk();

    expect($response->json('export_link'))->toBeNull()
        ->and($response->json('pagination'))->toHaveKeys(['total', 'offset', 'limit', 'total_pages']);

    $items = $response->json('items');
    expect($items)->toHaveCount(1);
    expect($items[0])->toMatchArray([
        'id' => $match->id,
        'label' => 'OPP_Ada',
    ]);
});

// ---------------------------------------------------------------------------
// meta.commercial / meta.reporter / meta.supervisor (spec 0065 D-3, directive
// 2026-07-29): the three roles a new Offerta inherits from its Opportunita'.
// ---------------------------------------------------------------------------

it('exposes meta.commercial, meta.reporter and meta.supervisor when set', function () {
    $actor = opportunityForSelectUserWith(['viewAny']);
    $commercial = Referent::factory()->create(['name' => 'Carla Commercial']);
    $reporter = Referent::factory()->create(['name' => 'Renzo Reporter']);
    $supervisor = User::factory()->create(['name' => 'Sara Supervisor']);
    $target = Opportunity::factory()->create([
        'commercial_id' => $commercial->id,
        'reporter_id' => $reporter->id,
        'supervisor_id' => $supervisor->id,
    ]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/opportunities/for-select?ids[]={$target->id}")->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item['meta'])->toMatchArray([
        'commercial' => ['id' => $commercial->id, 'name' => 'Carla Commercial'],
        'reporter' => ['id' => $reporter->id, 'name' => 'Renzo Reporter'],
        'supervisor' => ['id' => $supervisor->id, 'name' => 'Sara Supervisor'],
    ]);
});

it('exposes meta.commercial/meta.reporter/meta.supervisor as null when unset', function () {
    $actor = opportunityForSelectUserWith(['viewAny']);
    $target = Opportunity::factory()->create([
        'commercial_id' => null,
        'reporter_id' => null,
        'supervisor_id' => null,
    ]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/opportunities/for-select?ids[]={$target->id}")->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item['meta'])->toMatchArray(['commercial' => null, 'reporter' => null, 'supervisor' => null]);
});

it('ids[] hydrates an opportunity present even though it does not match the search', function () {
    $actor = opportunityForSelectUserWith(['viewAny']);
    $hydrated = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/opportunities/for-select?search=NoMatch&ids[]={$hydrated->id}")->assertOk();

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids->all())->toContain($hydrated->id);
});
