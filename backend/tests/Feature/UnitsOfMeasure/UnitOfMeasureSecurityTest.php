<?php

use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('unitOfMeasureUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function unitOfMeasureUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("units-of-measure.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("units-of-measure.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-021 — 403 without the matching permission, on every endpoint
// ---------------------------------------------------------------------------

it('GET show: 403 without units-of-measure.view (AC-021)', function () {
    $actor = unitOfMeasureUserWith([]);
    $target = UnitOfMeasure::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/units-of-measure/{$target->id}")->assertForbidden();
});

it('POST store: 403 without units-of-measure.create, no row created (AC-021)', function () {
    $actor = unitOfMeasureUserWith([]);
    Sanctum::actingAs($actor);

    $countBefore = UnitOfMeasure::count();

    $this->postJson('/api/units-of-measure', ['name' => 'Nope', 'symbol' => 'no', 'code' => 'nope'])->assertForbidden();

    expect(UnitOfMeasure::count())->toBe($countBefore);
});

it('PATCH update: 403 without units-of-measure.update, no change persisted (AC-021)', function () {
    $actor = unitOfMeasureUserWith([]);
    $target = UnitOfMeasure::factory()->create(['description' => 'Untouched']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/units-of-measure/{$target->id}", ['description' => 'Nope'])->assertForbidden();

    $this->assertDatabaseHas('units_of_measure', ['id' => $target->id, 'description' => 'Untouched']);
});

it('DELETE destroy: 403 without units-of-measure.delete, row NOT removed (AC-021)', function () {
    $actor = unitOfMeasureUserWith([]);
    $target = UnitOfMeasure::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/units-of-measure/{$target->id}")->assertForbidden();

    $this->assertDatabaseHas('units_of_measure', ['id' => $target->id]);
});

it('for-select: 200 even without units-of-measure.viewAny (AC-021, ADR 0011)', function () {
    $actor = unitOfMeasureUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/units-of-measure/for-select')->assertOk();
});

it('for-select: requires authentication (401) (AC-021)', function () {
    $this->getJson('/api/units-of-measure/for-select')->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// AC-020 — permissions:sync creates exactly the 8 standard permissions
// ---------------------------------------------------------------------------

it('permissions:sync creates all 8 units-of-measure.* permissions and no more (AC-020)', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
        expect(Permission::where('name', "units-of-measure.{$ability}")->exists())->toBeTrue();
    }

    expect(Permission::where('name', 'like', 'units-of-measure.%')->count())->toBe(8);
});

// ---------------------------------------------------------------------------
// navigation node gated by units-of-measure.view
// ---------------------------------------------------------------------------

it('navigation: the units-of-measure node only shows with units-of-measure.view', function () {
    Permission::findOrCreate('units-of-measure.view');

    $withoutView = User::factory()->create();
    Sanctum::actingAs($withoutView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->not->toContain('units-of-measure');

    $withView = User::factory()->create();
    $withView->givePermissionTo('units-of-measure.view');
    Sanctum::actingAs($withView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->toContain('units-of-measure');
});

// ---------------------------------------------------------------------------
// AC-030 — permission-catalogue: the module appears under Prodotti
// ---------------------------------------------------------------------------

it('permission-catalogue: units-of-measure appears under the products area with its 8 permissions and 4 fields (AC-030)', function () {
    $this->artisan('permissions:sync');
    Permission::findOrCreate('roles.viewAny');
    $actor = User::factory()->create();
    $actor->givePermissionTo('roles.viewAny');
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/authorization/permission-catalogue')->assertOk();

    $productsArea = collect($response->json('data.areas'))->firstWhere('key', 'products-group');
    expect($productsArea)->not->toBeNull();

    $module = collect($productsArea['resources'])->firstWhere('resource', 'units-of-measure');
    expect($module)->not->toBeNull()
        ->and($module['permissions'])->toHaveCount(8)
        ->and(collect($module['fields'])->pluck('key')->all())->toBe(['code', 'name', 'symbol', 'description']);
});
