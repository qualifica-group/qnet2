<?php

use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('registryUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function registryUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("registries.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("registries.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// auth + authorization
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->getJson('/api/registries/for-select')->assertUnauthorized();
});

it('allows actors without registries.viewAny (200 — ADR 0011 amended)', function () {
    $actor = registryUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/registries/for-select')->assertOk();
});

it('allows actors with registries.viewAny (200) and returns the paginated envelope', function () {
    $actor = registryUserWith(['viewAny']);
    Registry::factory()->count(3)->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/registries/for-select')
        ->assertOk()
        ->assertJsonStructure([
            'items' => [['id', 'label']],
            'export_link',
            'pagination' => ['total', 'offset', 'limit', 'total_pages'],
        ]);
});

// ---------------------------------------------------------------------------
// item shape + search
// ---------------------------------------------------------------------------

it('maps a registry to { id, label: name, meta }', function () {
    // Requirement changed by spec 0040 BR-4: every item now unconditionally
    // carries `meta` (commercial/reporter defaults) — see the AC-053 block
    // below for its shape/null cases.
    $actor = registryUserWith(['viewAny']);
    $target = Registry::factory()->create(['name' => 'Acme Supplies']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/registries/for-select?search=Acme Supplies')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item)->toMatchArray(['id' => $target->id, 'label' => 'Acme Supplies'])
        ->and(array_keys($item))->toEqualCanonicalizing(['id', 'label', 'meta']);
});

it('searches by name', function () {
    $actor = registryUserWith(['viewAny']);
    $match = Registry::factory()->create(['name' => 'Alphonse Target']);
    Registry::factory()->create(['name' => 'Someone Else']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/registries/for-select?search=Alphonse')->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($match->id);
});

// ---------------------------------------------------------------------------
// ids[] hydration + pagination
// ---------------------------------------------------------------------------

it('appends ids[] even when filtered out by search and does NOT inflate total', function () {
    $actor = registryUserWith(['viewAny']);
    $searchMatch = Registry::factory()->create(['name' => 'Zephyr Searchable']);
    $selected = Registry::factory()->create(['name' => 'Quentin Selected']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/registries/for-select?search=Zephyr&ids[]={$selected->id}")->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($searchMatch->id)
        ->and($ids)->toContain($selected->id)
        ->and($response->json('pagination.total'))->toBe(1);
});

it('rejects a limit above 100 (422)', function () {
    $actor = registryUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/registries/for-select?limit=101')
        ->assertStatus(422)->assertJsonValidationErrors('limit');
});

// ---------------------------------------------------------------------------
// AC-053 — meta.commercial / meta.reporter (spec 0040 BR-4) + meta.supervisor
// (directive 2026-07-29: the Opportunity form inherits all three)
// ---------------------------------------------------------------------------

it('exposes meta.commercial, meta.reporter and meta.supervisor when set', function () {
    $actor = registryUserWith(['viewAny']);
    $commercial = Referent::factory()->create(['name' => 'Carla Commercial']);
    $reporter = Referent::factory()->create(['name' => 'Renzo Reporter']);
    $supervisor = User::factory()->create(['name' => 'Sara Supervisor']);
    $target = Registry::factory()->create([
        'name' => 'Meta Target',
        'commercial_id' => $commercial->id,
        'reporter_id' => $reporter->id,
        'supervisor_id' => $supervisor->id,
    ]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/registries/for-select?search=Meta Target')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item['meta'])->toMatchArray([
        'commercial' => ['id' => $commercial->id, 'name' => 'Carla Commercial'],
        'reporter' => ['id' => $reporter->id, 'name' => 'Renzo Reporter'],
        'supervisor' => ['id' => $supervisor->id, 'name' => 'Sara Supervisor'],
    ]);
});

it('exposes meta.commercial/meta.reporter/meta.supervisor as null when unset', function () {
    $actor = registryUserWith(['viewAny']);
    $target = Registry::factory()->create([
        'name' => 'No Defaults',
        'commercial_id' => null,
        'reporter_id' => null,
        'supervisor_id' => null,
    ]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/registries/for-select?search=No Defaults')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item['meta'])->toMatchArray(['commercial' => null, 'reporter' => null, 'supervisor' => null]);
});

// ---------------------------------------------------------------------------
// AC-092 — meta.managers (spec 0040 A-5): account managers ordered by position
// ---------------------------------------------------------------------------

it('exposes meta.managers ordered by position with {id,name,position}', function () {
    $actor = registryUserWith(['viewAny']);
    $first = User::factory()->create(['name' => 'First Manager']);
    $third = User::factory()->create(['name' => 'Third Manager']);
    $target = Registry::factory()->create(['name' => 'Managed Registry']);
    // Attach out of order to prove the resource orders by pivot position.
    $target->managers()->attach($third->id, ['position' => 3]);
    $target->managers()->attach($first->id, ['position' => 1]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/registries/for-select?search=Managed Registry')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item['meta']['managers'])->toBe([
        ['id' => $first->id, 'name' => 'First Manager', 'position' => 1],
        ['id' => $third->id, 'name' => 'Third Manager', 'position' => 3],
    ]);
});

it('exposes meta.managers as [] when the registry has no account managers', function () {
    $actor = registryUserWith(['viewAny']);
    $target = Registry::factory()->create(['name' => 'Unmanaged Registry']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/registries/for-select?ids[]={$target->id}")->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item['meta']['managers'])->toBe([]);
});

// ---------------------------------------------------------------------------
// is_supplier filter (product supplier picker) — the flag stays OUT of the
// shared ForSelectQuery DTO; RegistryForSelectController reads it straight
// off the request and threads it as RegistryService::forSelect()'s 2nd arg.
// ---------------------------------------------------------------------------

it('is_supplier=1 returns only suppliers', function () {
    $actor = registryUserWith(['viewAny']);
    $supplier = Registry::factory()->create(['name' => 'Supplier Registry', 'is_supplier' => true]);
    $nonSupplier = Registry::factory()->create(['name' => 'Client Registry', 'is_supplier' => false]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/registries/for-select?is_supplier=1')->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($supplier->id)
        ->and($ids)->not->toContain($nonSupplier->id);
});

it('regression: an absent is_supplier returns every registry (suppliers and non-suppliers alike)', function () {
    $actor = registryUserWith(['viewAny']);
    $supplier = Registry::factory()->create(['name' => 'Supplier Registry Unfiltered', 'is_supplier' => true]);
    $nonSupplier = Registry::factory()->create(['name' => 'Client Registry Unfiltered', 'is_supplier' => false]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/registries/for-select?search=Unfiltered')->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($supplier->id)
        ->and($ids)->toContain($nonSupplier->id);
});

it('is_supplier=0 is identical to omitting the flag (returns every registry)', function () {
    $actor = registryUserWith(['viewAny']);
    $supplier = Registry::factory()->create(['name' => 'Supplier Registry Zero', 'is_supplier' => true]);
    $nonSupplier = Registry::factory()->create(['name' => 'Client Registry Zero', 'is_supplier' => false]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/registries/for-select?is_supplier=0&search=Zero')->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($supplier->id)
        ->and($ids)->toContain($nonSupplier->id);
});
