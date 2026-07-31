<?php

use App\Models\Lead;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('leadForSelectUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function leadForSelectUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("leads.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("leads.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-085 — auth + shape (envelope ADR 0011, label = registry name)
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->getJson('/api/leads/for-select')->assertUnauthorized();
});

it('allows actors without leads.viewAny (200 — ADR 0011 amended)', function () {
    $actor = leadForSelectUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/leads/for-select')->assertOk();
});

it('200: envelope {items, export_link, pagination} with {id, label, subtitle} items, filtered by search (AC-085)', function () {
    $actor = leadForSelectUserWith(['viewAny']);
    $matchingRegistry = Registry::factory()->create(['name' => 'Ada Contact']);
    $match = Lead::factory()->create(['registry_id' => $matchingRegistry->id]);
    Lead::factory()->create(['registry_id' => Registry::factory()->create(['name' => 'Zed Other'])->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/leads/for-select?search=Ada')->assertOk();

    expect($response->json('export_link'))->toBeNull()
        ->and($response->json('pagination'))->toHaveKeys(['total', 'offset', 'limit', 'total_pages']);

    $items = $response->json('items');
    expect($items)->toHaveCount(1);
    expect($items[0])->toMatchArray([
        'id' => $match->id,
        'label' => 'Ada Contact',
        'subtitle' => $match->campaign->code,
    ]);
});

// ---------------------------------------------------------------------------
// AC-085 — ids[] hydration bypasses search
// ---------------------------------------------------------------------------

it('ids[] hydrates a lead present even though it does not match the search (AC-085)', function () {
    $actor = leadForSelectUserWith(['viewAny']);
    $hydrated = Lead::factory()->create(['registry_id' => Registry::factory()->create(['name' => 'Legacy Contact'])->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/leads/for-select?search=NoMatch&ids[]={$hydrated->id}")->assertOk();

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids->all())->toContain($hydrated->id);
});
