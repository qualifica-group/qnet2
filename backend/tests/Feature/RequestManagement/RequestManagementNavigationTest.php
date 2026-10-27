<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// REQUIREMENT CHANGED (spec 0318): the opportunities-group became the top-level "Commerciale"
// section (key `commercial`); the items checked here are its direct children as before.

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-065 — "Gestione Richieste" nav item, real config/navigation.php, inside
// the existing "opportunities-group" collapsible parent, gated by
// request-management.view.
// ---------------------------------------------------------------------------

it('the request-management item is present under opportunities-group only with request-management.view (AC-065)', function () {
    Permission::findOrCreate('request-management.view');
    $actor = User::factory()->create();
    $actor->givePermissionTo('request-management.view');

    Sanctum::actingAs($actor);

    $groups = collect($this->getJson('/api/navigation')->assertOk()->json('data'));
    $opportunitiesGroup = $groups->firstWhere('key', 'commercial');

    expect($opportunitiesGroup)->not->toBeNull();

    $child = collect($opportunitiesGroup['children'])->firstWhere('key', 'request-management');

    expect($child)->not->toBeNull()
        ->and($child['route'])->toBe('/request-management')
        ->and($child['label'])->toBe('navigation.requestManagement');
});

it('the request-management item is absent without request-management.view (AC-065)', function () {
    $actor = User::factory()->create();
    Sanctum::actingAs($actor);

    $groups = collect($this->getJson('/api/navigation')->assertOk()->json('data'));
    $opportunitiesGroup = $groups->firstWhere('key', 'commercial');

    // Every other child requires its own missing permission too, so the
    // whole group is dropped when the actor has none of them.
    if ($opportunitiesGroup !== null) {
        expect(collect($opportunitiesGroup['children'])->firstWhere('key', 'request-management'))->toBeNull();
    } else {
        expect($opportunitiesGroup)->toBeNull();
    }
});
