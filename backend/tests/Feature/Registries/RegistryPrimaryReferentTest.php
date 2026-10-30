<?php

use App\Models\Referent;
use App\Models\Registry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0330: the registry's "Referente principale".

uses(RefreshDatabase::class);

/**
 * @param  array<int, string>  $abilities
 */
function primaryReferentActor(array $abilities = ['view', 'viewAny', 'create', 'update']): User
{
    foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
        Permission::findOrCreate("registries.{$ability}");
    }

    $actor = User::factory()->create();
    $actor->givePermissionTo(array_map(static fn (string $ability): string => "registries.{$ability}", $abilities));

    return $actor;
}

/**
 * @param  array<int, int>  $referentIds
 */
function registryWithReferents(array $referentIds, ?int $primaryId): Registry
{
    $registry = Registry::factory()->create();
    $registry->referents()->sync($referentIds);
    $registry->forceFill(['primary_referent_id' => $primaryId])->save();

    return $registry;
}

function runPrimaryReferentBackfill(): void
{
    $migration = require database_path('migrations/2026_11_10_100100_backfill_registries_primary_referent_id.php');
    $migration->up();
}

it('AC-001: the backfill elects the lowest pivot id and is idempotent', function () {
    [$a, $b, $c] = Referent::factory()->count(3)->create();
    $none = Registry::factory()->create();
    $one = Registry::factory()->create();
    $one->referents()->sync([$b->id]);
    $three = Registry::factory()->create();
    // Insertion order c, a, b: the lowest pivot id wins, not the lowest referent id.
    $three->referents()->attach($c->id);
    $three->referents()->attach($a->id);
    $three->referents()->attach($b->id);

    runPrimaryReferentBackfill();
    runPrimaryReferentBackfill();

    expect($none->fresh()->primary_referent_id)->toBeNull()
        ->and($one->fresh()->primary_referent_id)->toBe($b->id)
        ->and($three->fresh()->primary_referent_id)->toBe($c->id);
});

it('AC-002: create without primary_referent_id elects the first submitted referent', function () {
    [$a, $b] = Referent::factory()->count(2)->create();
    Sanctum::actingAs(primaryReferentActor());

    $this->postJson('/api/registries', [
        'is_supplier' => false,
        'referent_ids' => [$b->id, $a->id],
        'personal_data' => minimalRegistryProfilePayload(),
    ])->assertCreated()
        ->assertJsonPath('data.primary_referent_id', $b->id)
        ->assertJsonPath('data.primary_referent.id', $b->id)
        ->assertJsonPath('data.primary_referent.name', $b->name)
        ->assertJsonPath('data.referent_ids', [$b->id, $a->id]);
});

it('create honours an explicit primary among the submitted referents', function () {
    [$a, $b] = Referent::factory()->count(2)->create();
    Sanctum::actingAs(primaryReferentActor());

    $this->postJson('/api/registries', [
        'is_supplier' => false,
        'referent_ids' => [$a->id, $b->id],
        'primary_referent_id' => $b->id,
        'personal_data' => minimalRegistryProfilePayload(),
    ])->assertCreated()->assertJsonPath('data.primary_referent_id', $b->id);
});

it('create without referents leaves the primary null', function () {
    Sanctum::actingAs(primaryReferentActor());

    $this->postJson('/api/registries', ['is_supplier' => false, 'personal_data' => minimalRegistryProfilePayload()])
        ->assertCreated()
        ->assertJsonPath('data.primary_referent_id', null)
        ->assertJsonPath('data.primary_referent', null);
});

it('AC-003: create rejects a primary outside the submitted referents (422, nothing saved)', function () {
    [$a, $other] = Referent::factory()->count(2)->create();
    Sanctum::actingAs(primaryReferentActor());

    $this->postJson('/api/registries', [
        'is_supplier' => false,
        'referent_ids' => [$a->id],
        'primary_referent_id' => $other->id,
        'personal_data' => minimalRegistryProfilePayload(),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['primary_referent_id' => "The main referent must be one of the registry's referents."]);

    expect(Registry::query()->count())->toBe(0);
});

it('AC-003: update rejects a primary that is neither submitted nor linked, and an unknown referent', function () {
    [$a, $other] = Referent::factory()->count(2)->create();
    $registry = registryWithReferents([$a->id], $a->id);
    Sanctum::actingAs(primaryReferentActor());

    $this->patchJson("/api/registries/{$registry->id}", ['primary_referent_id' => $other->id])
        ->assertUnprocessable()->assertJsonValidationErrors('primary_referent_id');
    $this->patchJson("/api/registries/{$registry->id}", ['primary_referent_id' => 999999])
        ->assertUnprocessable()->assertJsonValidationErrors('primary_referent_id');
    $this->patchJson("/api/registries/{$registry->id}", ['referent_ids' => [$a->id], 'primary_referent_id' => $other->id])
        ->assertUnprocessable()->assertJsonValidationErrors('primary_referent_id');

    expect($registry->fresh()->primary_referent_id)->toBe($a->id);
});

it('AC-004: replacing the referents re-elects the first one still linked; emptying them nulls it', function () {
    [$a, $b, $c] = Referent::factory()->count(3)->create();
    $registry = registryWithReferents([$a->id, $b->id], $a->id);
    Sanctum::actingAs(primaryReferentActor());

    $this->patchJson("/api/registries/{$registry->id}", ['referent_ids' => [$b->id, $c->id]])
        ->assertOk()
        ->assertJsonPath('data.primary_referent_id', $b->id);

    $this->patchJson("/api/registries/{$registry->id}", ['referent_ids' => []])
        ->assertOk()
        ->assertJsonPath('data.primary_referent_id', null)
        ->assertJsonPath('data.primary_referent', null);
});

it('keeps the current primary when the referents are not touched', function () {
    [$a, $b] = Referent::factory()->count(2)->create();
    $registry = registryWithReferents([$a->id, $b->id], $b->id);
    Sanctum::actingAs(primaryReferentActor());

    $this->patchJson("/api/registries/{$registry->id}", ['vat_group' => 'VG-1'])
        ->assertOk()
        ->assertJsonPath('data.primary_referent_id', $b->id);
});

it('AC-005: an explicit primary wins, a later null falls back to the first referent', function () {
    [$a, $b] = Referent::factory()->count(2)->create();
    $registry = registryWithReferents([$a->id, $b->id], $a->id);
    Sanctum::actingAs(primaryReferentActor());

    $this->patchJson("/api/registries/{$registry->id}", ['primary_referent_id' => $b->id])
        ->assertOk()->assertJsonPath('data.primary_referent_id', $b->id);
    $this->patchJson("/api/registries/{$registry->id}", ['primary_referent_id' => null])
        ->assertOk()->assertJsonPath('data.primary_referent_id', $a->id);
});

it('AC-006: a non-editable primary_referent_id is refused as referent_ids is', function () {
    foreach (['view', 'update'] as $ability) {
        Permission::findOrCreate("registries.{$ability}");
    }
    $role = Role::create(['name' => 'registry-primary-readonly-'.uniqid()]);
    $role->givePermissionTo(['registries.view', 'registries.update']);
    $role->fieldPermissions()->create(['resource' => 'registries', 'field' => 'primary_referent_id', 'visible' => true, 'editable' => false, 'required' => false]);
    $actor = User::factory()->create();
    $actor->assignRole($role);
    [$a, $b] = Referent::factory()->count(2)->create();
    $registry = registryWithReferents([$a->id, $b->id], $a->id);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/registries/{$registry->id}", ['primary_referent_id' => $b->id])
        ->assertUnprocessable()->assertJsonValidationErrors('primary_referent_id');

    expect($registry->fresh()->primary_referent_id)->toBe($a->id);
});

it('AC-007: deleting the primary referent re-elects the first one left, or nulls it', function () {
    foreach (['viewAny', 'view', 'delete'] as $ability) {
        Permission::findOrCreate("referents.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo(['referents.view', 'referents.delete']);
    Sanctum::actingAs($actor);
    [$a, $b, $c] = Referent::factory()->count(3)->create();
    $shared = registryWithReferents([$a->id, $b->id, $c->id], $a->id);
    $alone = registryWithReferents([$a->id], $a->id);

    $this->deleteJson("/api/referents/{$a->id}")->assertNoContent();

    expect($shared->fresh()->primary_referent_id)->toBe($b->id)
        ->and($alone->fresh()->primary_referent_id)->toBeNull();
});

it('AC-008: for-select exposes meta.primary_referent without per-item queries', function () {
    Sanctum::actingAs(primaryReferentActor());
    $referent = Referent::factory()->create();
    registryWithReferents([$referent->id], $referent->id);
    Registry::factory()->create();

    $items = $this->getJson('/api/registries/for-select')->assertOk()->json('items');
    $primaries = collect($items)->pluck('meta.primary_referent');

    expect($primaries->filter()->values()->all())->toBe([['id' => $referent->id, 'name' => $referent->name]])
        ->and($primaries->count())->toBe(2);

    $this->getJson('/api/registries/for-select')->assertOk();
    DB::enableQueryLog();
    $this->getJson('/api/registries/for-select')->assertOk();
    $fewRows = count(DB::getQueryLog());
    DB::flushQueryLog();

    Registry::factory()->count(5)->create()->each(fn (Registry $registry) => $registry->forceFill(['primary_referent_id' => $referent->id])->save());
    DB::flushQueryLog();
    $this->getJson('/api/registries/for-select')->assertOk();
    $manyRows = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($manyRows)->toBe($fewRows);
});
