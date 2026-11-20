<?php

use App\Models\Referent;
use App\Models\Registry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0408: the registry's "Referente secondario" (Referente 2).

uses(RefreshDatabase::class);

function secondaryReferentActor(): User
{
    foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
        Permission::findOrCreate("registries.{$ability}");
    }

    $actor = User::factory()->create();
    $actor->givePermissionTo(['registries.viewAny', 'registries.view', 'registries.create', 'registries.update']);

    return $actor;
}

/**
 * @param  array<int, int>  $referentIds
 */
function registryWithReferentRoles(array $referentIds, ?int $primaryId, ?int $secondaryId): Registry
{
    $registry = Registry::factory()->create();
    $registry->referents()->sync($referentIds);
    $registry->forceFill(['primary_referent_id' => $primaryId, 'secondary_referent_id' => $secondaryId])->save();

    return $registry;
}

it('AC-001: create stores the secondario among the submitted referents', function () {
    [$a, $b] = Referent::factory()->count(2)->create();
    Sanctum::actingAs(secondaryReferentActor());

    $this->postJson('/api/registries', [
        'is_supplier' => false,
        'referent_ids' => [$a->id, $b->id],
        'primary_referent_id' => $a->id,
        'secondary_referent_id' => $b->id,
        'personal_data' => minimalRegistryProfilePayload(),
    ])->assertCreated()
        ->assertJsonPath('data.primary_referent_id', $a->id)
        ->assertJsonPath('data.secondary_referent_id', $b->id)
        ->assertJsonPath('data.secondary_referent.id', $b->id)
        ->assertJsonPath('data.secondary_referent.name', $b->name);
});

it('AC-001: update sets and clears the secondario', function () {
    [$a, $b] = Referent::factory()->count(2)->create();
    $registry = registryWithReferentRoles([$a->id, $b->id], $a->id, null);
    Sanctum::actingAs(secondaryReferentActor());

    $this->patchJson("/api/registries/{$registry->id}", ['secondary_referent_id' => $b->id])
        ->assertOk()->assertJsonPath('data.secondary_referent_id', $b->id);
    $this->patchJson("/api/registries/{$registry->id}", ['secondary_referent_id' => null])
        ->assertOk()->assertJsonPath('data.secondary_referent_id', null)
        ->assertJsonPath('data.primary_referent_id', $a->id);
});

it('AC-002: rejects a secondario that is not linked (422, nothing saved)', function () {
    [$a, $other] = Referent::factory()->count(2)->create();
    $registry = registryWithReferentRoles([$a->id], $a->id, null);
    Sanctum::actingAs(secondaryReferentActor());

    $this->patchJson("/api/registries/{$registry->id}", ['secondary_referent_id' => $other->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['secondary_referent_id' => "The secondary referent must be one of the registry's referents."]);

    $this->postJson('/api/registries', [
        'is_supplier' => false,
        'referent_ids' => [$a->id],
        'secondary_referent_id' => $other->id,
        'personal_data' => minimalRegistryProfilePayload(),
    ])->assertUnprocessable()->assertJsonValidationErrors('secondary_referent_id');

    expect($registry->fresh()->secondary_referent_id)->toBeNull()
        ->and(Registry::query()->count())->toBe(1);
});

it('AC-002: rejects a secondario equal to the submitted or current principale', function () {
    [$a, $b] = Referent::factory()->count(2)->create();
    $registry = registryWithReferentRoles([$a->id, $b->id], $a->id, null);
    Sanctum::actingAs(secondaryReferentActor());

    $this->patchJson("/api/registries/{$registry->id}", ['secondary_referent_id' => $a->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['secondary_referent_id' => 'The secondary referent must differ from the main referent.']);
    $this->patchJson("/api/registries/{$registry->id}", ['primary_referent_id' => $b->id, 'secondary_referent_id' => $b->id])
        ->assertUnprocessable()->assertJsonValidationErrors('secondary_referent_id');
    $this->patchJson("/api/registries/{$registry->id}", ['primary_referent_id' => $b->id, 'secondary_referent_id' => $a->id])
        ->assertOk()
        ->assertJsonPath('data.primary_referent_id', $b->id)
        ->assertJsonPath('data.secondary_referent_id', $a->id);
});

it('AC-003: unlinking the secondario or making it the principale clears it', function () {
    [$a, $b, $c] = Referent::factory()->count(3)->create();
    $unlinked = registryWithReferentRoles([$a->id, $b->id], $a->id, $b->id);
    $promoted = registryWithReferentRoles([$a->id, $b->id, $c->id], $a->id, $b->id);
    Sanctum::actingAs(secondaryReferentActor());

    $this->patchJson("/api/registries/{$unlinked->id}", ['referent_ids' => [$a->id]])
        ->assertOk()->assertJsonPath('data.secondary_referent_id', null);
    $this->patchJson("/api/registries/{$promoted->id}", ['primary_referent_id' => $b->id])
        ->assertOk()
        ->assertJsonPath('data.primary_referent_id', $b->id)
        ->assertJsonPath('data.secondary_referent_id', null);
});

it('AC-003: the secondario stays when the referents are not touched or it remains linked', function () {
    [$a, $b, $c] = Referent::factory()->count(3)->create();
    $registry = registryWithReferentRoles([$a->id, $b->id], $a->id, $b->id);
    Sanctum::actingAs(secondaryReferentActor());

    $this->patchJson("/api/registries/{$registry->id}", ['general_notes' => 'x'])
        ->assertOk()->assertJsonPath('data.secondary_referent_id', $b->id);
    $this->patchJson("/api/registries/{$registry->id}", ['referent_ids' => [$a->id, $b->id, $c->id]])
        ->assertOk()->assertJsonPath('data.secondary_referent_id', $b->id);
});

it('AC-003: deleting the secondario referent clears it', function () {
    foreach (['viewAny', 'view', 'delete'] as $ability) {
        Permission::findOrCreate("referents.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo(['referents.view', 'referents.delete']);
    Sanctum::actingAs($actor);
    [$a, $b] = Referent::factory()->count(2)->create();
    $registry = registryWithReferentRoles([$a->id, $b->id], $a->id, $b->id);

    $this->deleteJson("/api/referents/{$b->id}")->assertNoContent();

    expect($registry->fresh()->secondary_referent_id)->toBeNull()
        ->and($registry->fresh()->primary_referent_id)->toBe($a->id);
});

it('AC-004: a non-editable secondary_referent_id is refused', function () {
    foreach (['view', 'update'] as $ability) {
        Permission::findOrCreate("registries.{$ability}");
    }
    $role = Role::create(['name' => 'registry-secondary-readonly-'.uniqid()]);
    $role->givePermissionTo(['registries.view', 'registries.update']);
    $role->fieldPermissions()->create(['resource' => 'registries', 'field' => 'secondary_referent_id', 'visible' => true, 'editable' => false, 'required' => false]);
    $actor = User::factory()->create();
    $actor->assignRole($role);
    [$a, $b] = Referent::factory()->count(2)->create();
    $registry = registryWithReferentRoles([$a->id, $b->id], $a->id, null);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/registries/{$registry->id}", ['secondary_referent_id' => $b->id])
        ->assertUnprocessable()->assertJsonValidationErrors('secondary_referent_id');

    expect($registry->fresh()->secondary_referent_id)->toBeNull();
});

it('D-9: create without a secondario takes the second referent; an explicit null is kept', function () {
    [$a, $b, $c] = Referent::factory()->count(3)->create();
    Sanctum::actingAs(secondaryReferentActor());
    // Each registry needs its own phone number (unique across records).
    $payload = fn (string $phone, array $extra = []): array => [
        'is_supplier' => false,
        'referent_ids' => [$a->id, $b->id, $c->id],
        'personal_data' => minimalRegistryProfilePayload(['contacts' => [['type' => 'phone', 'value' => $phone, 'is_primary' => true]]]),
        ...$extra,
    ];

    $this->postJson('/api/registries', $payload('+39 02 1110001'))->assertCreated()
        ->assertJsonPath('data.primary_referent_id', $a->id)
        ->assertJsonPath('data.secondary_referent_id', $b->id);
    $this->postJson('/api/registries', $payload('+39 02 1110002', ['primary_referent_id' => $b->id]))->assertCreated()
        ->assertJsonPath('data.primary_referent_id', $b->id)
        ->assertJsonPath('data.secondary_referent_id', $a->id);
    $this->postJson('/api/registries', $payload('+39 02 1110003', ['secondary_referent_id' => null]))->assertCreated()
        ->assertJsonPath('data.secondary_referent_id', null);
    $this->postJson('/api/registries', $payload('+39 02 1110004', ['referent_ids' => [$a->id]]))->assertCreated()
        ->assertJsonPath('data.primary_referent_id', $a->id)
        ->assertJsonPath('data.secondary_referent_id', null);
});

it('D-9: adding a referent fills an empty secondario, unless the request sets it', function () {
    [$a, $b, $c] = Referent::factory()->count(3)->create();
    $filled = registryWithReferentRoles([$a->id], $a->id, null);
    $explicit = registryWithReferentRoles([$a->id], $a->id, null);
    $legacy = registryWithReferentRoles([$a->id, $b->id], $a->id, null);
    Sanctum::actingAs(secondaryReferentActor());

    $this->patchJson("/api/registries/{$filled->id}", ['referent_ids' => [$a->id, $b->id]])
        ->assertOk()->assertJsonPath('data.secondary_referent_id', $b->id);
    $this->patchJson("/api/registries/{$explicit->id}", ['referent_ids' => [$a->id, $b->id], 'secondary_referent_id' => null])
        ->assertOk()->assertJsonPath('data.secondary_referent_id', null);
    // Only a referent added by this write is a candidate: B, already linked, is not promoted.
    $this->patchJson("/api/registries/{$legacy->id}", ['general_notes' => 'x'])
        ->assertOk()->assertJsonPath('data.secondary_referent_id', null);
    $this->patchJson("/api/registries/{$legacy->id}", ['referent_ids' => [$a->id, $b->id, $c->id]])
        ->assertOk()->assertJsonPath('data.secondary_referent_id', $c->id);
});
