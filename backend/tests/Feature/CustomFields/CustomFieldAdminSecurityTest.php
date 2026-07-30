<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// spec 0021 — T9: AC-020 (permissions:sync creates custom-fields.*, the
// navigation node is gated by custom-fields.view, GET /custom-fields/entities
// lists the custom-fieldable modules and requires viewAny).
uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-020 — permissions:sync creates the 7 standard permissions
// ---------------------------------------------------------------------------

it('permissions:sync creates all 7 custom-fields.* permissions', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        expect(Permission::where('name', "custom-fields.{$ability}")->exists())->toBeTrue();
    }
});

// ---------------------------------------------------------------------------
// AC-020 — navigation node gated by custom-fields.view
// ---------------------------------------------------------------------------

it('navigation: the custom-fields node only shows with custom-fields.view', function () {
    Permission::findOrCreate('custom-fields.view');

    $withoutView = User::factory()->create();
    Sanctum::actingAs($withoutView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->not->toContain('custom-fields');

    $withView = User::factory()->create();
    $withView->givePermissionTo('custom-fields.view');
    Sanctum::actingAs($withView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->toContain('custom-fields');
});

// ---------------------------------------------------------------------------
// AC-020 — GET /api/custom-fields/entities
// ---------------------------------------------------------------------------

it('entities: 200 lists the custom-fieldable modules', function () {
    Permission::findOrCreate('custom-fields.viewAny');
    $actor = User::factory()->create();
    $actor->givePermissionTo('custom-fields.viewAny');
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/custom-fields/entities')->assertOk();

    $entityTypes = collect($response->json('data'))->pluck('entity_type')->all();
    expect($entityTypes)->toContain('companies');
});

it('entities: 403 without custom-fields.viewAny', function () {
    $actor = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/custom-fields/entities')->assertForbidden();
});
