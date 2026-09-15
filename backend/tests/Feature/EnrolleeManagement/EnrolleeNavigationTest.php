<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0130, AC-011 — the `enrollee-management` nav item (config/navigation/
// opportunities.php) is gated SOLELY by `enrollee-management.view`, siblings
// `request-management` (not a child of it, D-1: a separate module) and
// carries no children of its own (no FCR queue, unlike request-management).

uses(RefreshDatabase::class);

it('the enrollee-management item is present under opportunities-group only with enrollee-management.view (AC-011)', function () {
    Permission::findOrCreate('enrollee-management.view');
    $actor = User::factory()->create();
    $actor->givePermissionTo('enrollee-management.view');

    Sanctum::actingAs($actor);

    $groups = collect($this->getJson('/api/navigation')->assertOk()->json('data'));
    $opportunitiesGroup = $groups->firstWhere('key', 'opportunities-group');

    expect($opportunitiesGroup)->not->toBeNull();

    $child = collect($opportunitiesGroup['children'])->firstWhere('key', 'enrollee-management');

    expect($child)->not->toBeNull()
        ->and($child['route'])->toBe('/enrollee-management')
        ->and($child['label'])->toBe('navigation.enrolleeManagement')
        ->and($child['children'] ?? [])->toBe([]);
});

it('the enrollee-management item is absent without enrollee-management.view, even holding the FULL request-management.* set (AC-011)', function () {
    Permission::findOrCreate('enrollee-management.view');
    Permission::findOrCreate('request-management.view');
    $actor = User::factory()->create();
    $actor->givePermissionTo('request-management.view');

    Sanctum::actingAs($actor);

    $groups = collect($this->getJson('/api/navigation')->assertOk()->json('data'));
    $opportunitiesGroup = $groups->firstWhere('key', 'opportunities-group');

    expect($opportunitiesGroup)->not->toBeNull(); // request-management itself is visible

    expect(collect($opportunitiesGroup['children'])->firstWhere('key', 'enrollee-management'))->toBeNull();
});

it('the enrollee-management item is absent when the actor has no permission at all (AC-011)', function () {
    $actor = User::factory()->create();
    Sanctum::actingAs($actor);

    $groups = collect($this->getJson('/api/navigation')->assertOk()->json('data'));
    $opportunitiesGroup = $groups->firstWhere('key', 'opportunities-group');

    if ($opportunitiesGroup !== null) {
        expect(collect($opportunitiesGroup['children'])->firstWhere('key', 'enrollee-management'))->toBeNull();
    } else {
        expect($opportunitiesGroup)->toBeNull();
    }
});
