<?php

use App\Models\Opportunity;
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

it('ids[] hydrates an opportunity present even though it does not match the search', function () {
    $actor = opportunityForSelectUserWith(['viewAny']);
    $hydrated = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/opportunities/for-select?search=NoMatch&ids[]={$hydrated->id}")->assertOk();

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids->all())->toContain($hydrated->id);
});
