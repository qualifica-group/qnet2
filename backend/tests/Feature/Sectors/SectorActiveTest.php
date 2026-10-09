<?php

use App\Models\Registry;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('sectorActiveUserWith')) {
    /**
     * @param  array<int, string>  $abilities  "<resource>.<ability>" names
     */
    function sectorActiveUserWith(array $abilities): User
    {
        foreach (['sectors', 'registries'] as $resource) {
            foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
                Permission::findOrCreate("{$resource}.{$ability}");
            }
        }

        $user = User::factory()->create();
        $user->givePermissionTo($abilities);

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-001 / AC-002 — own flag on write and read
// ---------------------------------------------------------------------------

it('AC-001: a sector created without is_active is active', function () {
    Sanctum::actingAs(sectorActiveUserWith(['sectors.create']));

    $this->postJson('/api/sectors', ['name' => 'Energy'])
        ->assertCreated()
        ->assertJsonPath('data.is_active', true);

    expect(Sector::firstWhere('name', 'Energy')->is_active)->toBeTrue();
});

it('AC-001: PATCH is_active=false persists the own flag and leaves the children untouched', function () {
    $root = Sector::factory()->create();
    $child = Sector::factory()->childOf($root)->create();
    Sanctum::actingAs(sectorActiveUserWith(['sectors.update']));

    $this->patchJson("/api/sectors/{$root->id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    expect($root->fresh()->is_active)->toBeFalse()
        ->and($child->fresh()->is_active)->toBeTrue();
});

it('AC-001: a non-boolean is_active is rejected', function () {
    $sector = Sector::factory()->create();
    Sanctum::actingAs(sectorActiveUserWith(['sectors.update']));

    $this->patchJson("/api/sectors/{$sector->id}", ['is_active' => 'maybe'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('is_active');
});

it('AC-002: the tree exposes is_active and keeps the inactive branch', function () {
    $root = Sector::factory()->create(['name' => 'Root', 'is_active' => false]);
    $child = Sector::factory()->childOf($root)->create(['name' => 'Child']);
    Sanctum::actingAs(sectorActiveUserWith(['sectors.viewAny']));

    $tree = $this->getJson('/api/sectors/tree')->assertOk()->json('data');

    expect($tree[0]['id'])->toBe($root->id)
        ->and($tree[0]['is_active'])->toBeFalse()
        ->and($tree[0]['children'][0]['id'])->toBe($child->id)
        ->and($tree[0]['children'][0]['is_active'])->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-003 — for-select hides the effectively inactive branch
// ---------------------------------------------------------------------------

it('AC-003: for-select excludes an inactive sector and its descendants from items and total', function () {
    $root = Sector::factory()->create(['is_active' => false]);
    $child = Sector::factory()->childOf($root)->create();
    $grandchild = Sector::factory()->childOf($child)->create();
    $active = Sector::factory()->create();
    Sanctum::actingAs(sectorActiveUserWith([]));

    $response = $this->getJson('/api/sectors/for-select')->assertOk();

    expect(collect($response->json('items'))->pluck('id')->all())->toBe([$active->id])
        ->and($response->json('pagination.total'))->toBe(1)
        ->and([$root->id, $child->id, $grandchild->id])->each->not->toBeIn(collect($response->json('items'))->pluck('id')->all());
});

it('AC-003: for-select still hydrates an inactive sector requested via ids[]', function () {
    $inactive = Sector::factory()->create(['is_active' => false]);
    $active = Sector::factory()->create();
    Sanctum::actingAs(sectorActiveUserWith([]));

    $response = $this->getJson("/api/sectors/for-select?ids[]={$inactive->id}")->assertOk();

    expect(collect($response->json('items'))->pluck('id')->all())->toEqualCanonicalizing([$active->id, $inactive->id])
        ->and($response->json('pagination.total'))->toBe(1);
});

// ---------------------------------------------------------------------------
// AC-004 — no NEW link to an inactive sector, history exempt
// ---------------------------------------------------------------------------

it('AC-004: creating a registry with an effectively inactive sector is rejected', function () {
    $root = Sector::factory()->create(['is_active' => false]);
    $child = Sector::factory()->childOf($root)->create();
    Sanctum::actingAs(sectorActiveUserWith(['registries.create']));

    $this->postJson('/api/registries', [
        'sector_ids' => [$child->id],
        'is_supplier' => false,
        'personal_data' => minimalRegistryProfilePayload(),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sector_ids.0' => 'This sector is not active.']);
});

it('AC-004: an unknown sector id keeps the invalid message', function () {
    Sanctum::actingAs(sectorActiveUserWith(['registries.create']));

    $this->postJson('/api/registries', [
        'sector_ids' => [999999],
        'is_supplier' => false,
        'personal_data' => minimalRegistryProfilePayload(),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sector_ids.0' => 'The selected sector is invalid.']);
});

it('AC-004: a sector already linked and later deactivated can be sent back unchanged', function () {
    $registry = Registry::factory()->create();
    $sector = Sector::factory()->create();
    $registry->sectors()->sync([$sector->id]);
    $sector->update(['is_active' => false]);
    Sanctum::actingAs(sectorActiveUserWith(['registries.update']));

    $this->patchJson("/api/registries/{$registry->id}", ['sector_ids' => [$sector->id]])
        ->assertOk()
        ->assertJsonPath('data.sector_ids', [$sector->id]);
});

it('AC-004: adding another inactive sector on update is rejected', function () {
    $registry = Registry::factory()->create();
    $linked = Sector::factory()->create();
    $registry->sectors()->sync([$linked->id]);
    $inactive = Sector::factory()->create(['is_active' => false]);
    Sanctum::actingAs(sectorActiveUserWith(['registries.update']));

    $this->patchJson("/api/registries/{$registry->id}", ['sector_ids' => [$linked->id, $inactive->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sector_ids.1');
});

// ---------------------------------------------------------------------------
// AC-005 — grid column
// ---------------------------------------------------------------------------

it('AC-005: the grid exposes is_active and filters on the own flag', function () {
    $on = Sector::factory()->create();
    $off = Sector::factory()->create(['is_active' => false]);
    Sanctum::actingAs(sectorActiveUserWith(['sectors.viewAny']));

    $columns = collect($this->getJson('/api/tables/sectors/columns')->assertOk()->json('data.columns'))->keyBy('id');
    expect($columns['is_active']['filterType'])->toBe('boolean')
        ->and($columns['is_active']['sortable'])->toBeTrue();

    $rows = $this->postJson('/api/tables/sectors/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['is_active' => ['filterType' => 'boolean', 'filter' => false]],
    ])->assertOk()->json('items');

    expect(collect($rows)->pluck('id')->all())->toBe([$off->id])
        ->and($rows[0]['is_active'])->toBeFalse()
        ->and($on->id)->not->toBeIn(collect($rows)->pluck('id')->all());
});
