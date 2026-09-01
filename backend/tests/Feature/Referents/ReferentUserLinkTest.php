<?php

use App\Models\Referent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('referentUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function referentUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("referents.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("referents.{$ability}");
        }

        return $user;
    }
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
if (! function_exists('minimalReferentProfilePayload')) {
    function minimalReferentProfilePayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'individual',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
        ], $overrides);
    }
}

// ---------------------------------------------------------------------------
// AC-001 — link a user from the referent card, rereads on GET
// ---------------------------------------------------------------------------

it('update: links a user to a referent, and the link rereads on GET', function () {
    $actor = referentUserWith(['update', 'view']);
    $target = Referent::factory()->create();
    $linkedUser = User::factory()->create(['name' => 'Ada Lovelace']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/referents/{$target->id}", ['user_id' => $linkedUser->id])
        ->assertOk()
        ->assertJsonPath('data.user_id', $linkedUser->id)
        ->assertJsonPath('data.user.id', $linkedUser->id)
        ->assertJsonPath('data.user.name', 'Ada Lovelace');

    $this->getJson("/api/referents/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.user_id', $linkedUser->id)
        ->assertJsonPath('data.user.name', 'Ada Lovelace');

    $this->assertDatabaseHas('referents', ['id' => $target->id, 'user_id' => $linkedUser->id]);
});

it('create: persists the user_id link', function () {
    $actor = referentUserWith(['create']);
    $linkedUser = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/referents', [
        'contact_scope' => 'internal',
        'user_id' => $linkedUser->id,
        'personal_data' => minimalReferentProfilePayload([
            'contacts' => [['type' => 'phone', 'value' => '+39 333 1234567', 'is_primary' => true]],
        ]),
    ])->assertCreated()->assertJsonPath('data.user_id', $linkedUser->id);
});

it('create: 422 when user_id does not reference an existing user', function () {
    $actor = referentUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/referents', [
        'contact_scope' => 'internal',
        'user_id' => 999999,
        'personal_data' => minimalReferentProfilePayload([
            'contacts' => [['type' => 'phone', 'value' => '+39 333 1234567', 'is_primary' => true]],
        ]),
    ])->assertStatus(422)->assertJsonValidationErrors('user_id');
});

// ---------------------------------------------------------------------------
// AC-002 — an already-linked user, 422 naming the occupying referent
// ---------------------------------------------------------------------------

it('update: 422 referents.user_already_linked when the user is already linked to another referent', function () {
    $actor = referentUserWith(['update']);
    $linkedUser = User::factory()->create();
    $occupying = Referent::factory()->forUser($linkedUser)->create(['name' => 'Occupying Referent']);
    $target = Referent::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/referents/{$target->id}", ['user_id' => $linkedUser->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('user_id');

    expect($response->json('errors.user_id.0'))->toContain('Occupying Referent');
    $this->assertDatabaseHas('referents', ['id' => $target->id, 'user_id' => null]);
    $this->assertDatabaseHas('referents', ['id' => $occupying->id, 'user_id' => $linkedUser->id]);
});

it('create: 422 referents.user_already_linked when the user is already linked to another referent', function () {
    $actor = referentUserWith(['create']);
    $linkedUser = User::factory()->create();
    Referent::factory()->forUser($linkedUser)->create(['name' => 'Occupying Referent']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/referents', [
        'contact_scope' => 'internal',
        'user_id' => $linkedUser->id,
        'personal_data' => minimalReferentProfilePayload([
            'contacts' => [['type' => 'phone', 'value' => '+39 333 1234567', 'is_primary' => true]],
        ]),
    ])->assertStatus(422)->assertJsonValidationErrors('user_id');

    expect($response->json('errors.user_id.0'))->toContain('Occupying Referent');
});

it("update: keeping the referent's OWN link is a no-op, not a collision", function () {
    $actor = referentUserWith(['update']);
    $linkedUser = User::factory()->create();
    $target = Referent::factory()->forUser($linkedUser)->create(['notes' => 'Old']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/referents/{$target->id}", ['user_id' => $linkedUser->id, 'notes' => 'New'])
        ->assertOk()
        ->assertJsonPath('data.user_id', $linkedUser->id)
        ->assertJsonPath('data.notes', 'New');
});

it('update: the missing-link 422 never masks the 403 of an actor who may not update', function () {
    $actor = referentUserWith([]);
    $linkedUser = User::factory()->create();
    Referent::factory()->forUser($linkedUser)->create();
    $target = Referent::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/referents/{$target->id}", ['user_id' => $linkedUser->id])->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-003 — user_id: null unlinks, nothing else touched
// ---------------------------------------------------------------------------

it('update: user_id null unlinks without touching anything else', function () {
    $actor = referentUserWith(['update']);
    $linkedUser = User::factory()->create();
    $target = Referent::factory()->forUser($linkedUser)->create(['notes' => 'Keep me', 'contact_scope' => 'internal']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/referents/{$target->id}", ['user_id' => null])
        ->assertOk()
        ->assertJsonPath('data.user_id', null)
        ->assertJsonPath('data.user', null)
        ->assertJsonPath('data.notes', 'Keep me')
        ->assertJsonPath('data.contact_scope', 'internal');

    $this->assertDatabaseHas('referents', ['id' => $target->id, 'user_id' => null]);
});

it('update: omitting user_id entirely leaves an existing link untouched (PATCH partial)', function () {
    $actor = referentUserWith(['update']);
    $linkedUser = User::factory()->create();
    $target = Referent::factory()->forUser($linkedUser)->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/referents/{$target->id}", ['notes' => 'Touched'])
        ->assertOk()
        ->assertJsonPath('data.user_id', $linkedUser->id);

    $this->assertDatabaseHas('referents', ['id' => $target->id, 'user_id' => $linkedUser->id]);
});

// ---------------------------------------------------------------------------
// AC-004 — deleting the linked user leaves the referent standing, unlinked
// (D-1, nullOnDelete)
// ---------------------------------------------------------------------------

it('deleting the linked user leaves the referent in place with user_id null', function () {
    $linkedUser = User::factory()->create();
    $target = Referent::factory()->forUser($linkedUser)->create();

    $linkedUser->delete();

    $this->assertDatabaseHas('referents', ['id' => $target->id, 'user_id' => null]);
});

// ---------------------------------------------------------------------------
// AC-005 — field permission: editable:false -> 422, visible:false -> omitted
// ---------------------------------------------------------------------------

it("update: user_id editable:false for the actor's role -> 422, no write", function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("referents.{$ability}");
    }

    $role = Role::create(['name' => 'referent-user-link-locked']);
    $role->givePermissionTo(['referents.view', 'referents.update']);
    $role->fieldPermissions()->create([
        'resource' => 'referents',
        'field' => 'user_id',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $linkedUser = User::factory()->create();
    $target = Referent::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/referents/{$target->id}", ['user_id' => $linkedUser->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('user_id');

    $this->assertDatabaseHas('referents', ['id' => $target->id, 'user_id' => null]);
});

it("show: user_id/user are omitted together when not visible for the actor's role", function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("referents.{$ability}");
    }

    $role = Role::create(['name' => 'referent-user-link-hidden']);
    $role->givePermissionTo(['referents.view', 'referents.update']);
    $role->fieldPermissions()->create([
        'resource' => 'referents',
        'field' => 'user_id',
        'visible' => false,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $linkedUser = User::factory()->create(['name' => 'Ada Lovelace']);
    $target = Referent::factory()->forUser($linkedUser)->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/referents/{$target->id}")
        ->assertOk()
        ->assertJsonMissingPath('data.user_id')
        ->assertJsonMissingPath('data.user');
});
