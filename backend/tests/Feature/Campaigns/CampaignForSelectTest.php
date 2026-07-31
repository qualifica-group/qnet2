<?php

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('campaignUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function campaignUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("campaigns.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("campaigns.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-050 — auth + shape
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->getJson('/api/campaigns/for-select')->assertUnauthorized();
});

it('allows actors without campaigns.viewAny (200 — ADR 0011 amended)', function () {
    $actor = campaignUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/campaigns/for-select')->assertOk();
});

it('200: envelope {items, export_link, pagination} with {id, label, subtitle} items, filtered by search (AC-050)', function () {
    $actor = campaignUserWith(['viewAny']);
    $match = Campaign::factory()->create(['name' => 'Spring Outreach']);
    Campaign::factory()->create(['name' => 'Winter Push']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/campaigns/for-select?search=Spring')->assertOk();

    expect($response->json('export_link'))->toBeNull()
        ->and($response->json('pagination'))->toHaveKeys(['total', 'offset', 'limit', 'total_pages']);

    $items = $response->json('items');
    expect($items)->toHaveCount(1);
    expect($items[0])->toMatchArray([
        'id' => $match->id,
        'label' => $match->name,
        'subtitle' => $match->code,
    ]);
});

// ---------------------------------------------------------------------------
// AC-051 — ids[] hydration bypasses search
// ---------------------------------------------------------------------------

it('ids[] hydrates a campaign present even though it does not match the search (AC-051)', function () {
    $actor = campaignUserWith(['viewAny']);
    $hydrated = Campaign::factory()->create(['name' => 'Legacy Deal']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/campaigns/for-select?search=NoMatch&ids[]={$hydrated->id}")->assertOk();

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids->all())->toContain($hydrated->id);
});
