<?php

use App\Models\BusinessFunction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

if (! function_exists('userWithUserAbilities')) {
    function userWithUserAbilities(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("users.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("users.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// view — GET /api/users/{user}
// ---------------------------------------------------------------------------

it('view: 200 with users.view', function () {
    $actor = userWithUserAbilities(['view']);
    $target = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/users/{$target->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.email', $target->email)
        // sensitive fields never exposed
        ->assertJsonMissingPath('data.password');
});

it('view: eager-loads and returns the nested employment tree', function () {
    $actor = userWithUserAbilities(['view']);
    $function = BusinessFunction::factory()->create(['name' => 'Engineering']);
    $target = User::factory()->create();
    $target->employment()->create([
        'is_manager' => false,
        'job_description' => 'Backend engineer',
        'business_function_id' => $function->id,
    ]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/users/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.employment.business_function.id', $function->id)
        ->assertJsonPath('data.employment.job_description', 'Backend engineer');
});

// AC-009 (multi-site Sede membership) has its own file: UserEmploymentViewTest.php.

it('view: 403 without users.view', function () {
    $actor = userWithUserAbilities([]);
    $target = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/users/{$target->id}")->assertForbidden();
});

it('view: 404 for a non-existent user', function () {
    $actor = userWithUserAbilities(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/users/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// create — POST /api/users
// ---------------------------------------------------------------------------

/**
 * A minimal valid individual personal_data block. The user's `name` is derived
 * from it (ADR 0012), so every create payload must carry one.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function individualProfile(array $overrides = []): array
{
    return array_merge([
        'type' => 'individual',
        'first_name' => 'New',
        'last_name' => 'Person',
    ], $overrides);
}

it('create: 201 + persistence + hashed password not exposed', function () {
    $actor = userWithUserAbilities(['create']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/users', [
        'email' => 'new.person@example.com',
        'locale' => 'it',
        'password' => 'Str0ng-P4ssw0rd!',
        'password_confirmation' => 'Str0ng-P4ssw0rd!',
        'personal_data' => individualProfile(),
    ])->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.email', 'new.person@example.com');

    // Password never echoed back.
    expect($response->json('data'))->not->toHaveKey('password');

    // Name is derived from the personal_data card, not client-supplied.
    $this->assertDatabaseHas('users', [
        'email' => 'new.person@example.com',
        'name' => 'New Person',
        'locale' => 'it',
    ]);

    // Stored password is hashed, not plaintext.
    $created = User::where('email', 'new.person@example.com')->first();
    expect($created->password)->not->toBe('Str0ng-P4ssw0rd!')
        ->and(Hash::check('Str0ng-P4ssw0rd!', $created->password))->toBeTrue();
});

it('create: is_active defaults to true when omitted, and is exposed by the resource', function () {
    $actor = userWithUserAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/users', [
        'email' => 'active.default@example.com',
        'locale' => 'it',
        'password' => 'Str0ng-P4ssw0rd!',
        'password_confirmation' => 'Str0ng-P4ssw0rd!',
        'personal_data' => individualProfile(),
    ])->assertCreated()
        ->assertJsonPath('data.is_active', true);

    $this->assertDatabaseHas('users', [
        'email' => 'active.default@example.com',
        'is_active' => true,
    ]);
});

it('create: persists an explicit is_active=false', function () {
    $actor = userWithUserAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/users', [
        'email' => 'inactive.new@example.com',
        'locale' => 'it',
        'is_active' => false,
        'password' => 'Str0ng-P4ssw0rd!',
        'password_confirmation' => 'Str0ng-P4ssw0rd!',
        'personal_data' => individualProfile(),
    ])->assertCreated()
        ->assertJsonPath('data.is_active', false);

    $this->assertDatabaseHas('users', [
        'email' => 'inactive.new@example.com',
        'is_active' => false,
    ]);
});

it('update: toggles is_active off', function () {
    $actor = userWithUserAbilities(['update']);
    $target = User::factory()->create(['is_active' => true]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    expect($target->fresh()->is_active)->toBeFalse();
});

it('create: derives users.name from an individual card (First Last)', function () {
    $actor = userWithUserAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/users', [
        'email' => 'first.last@example.com',
        'locale' => 'en',
        'password' => 'Str0ng-P4ssw0rd!',
        'password_confirmation' => 'Str0ng-P4ssw0rd!',
        'personal_data' => individualProfile(['first_name' => 'Grace', 'last_name' => 'Hopper']),
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Grace Hopper');

    $this->assertDatabaseHas('users', ['email' => 'first.last@example.com', 'name' => 'Grace Hopper']);
});

it('create: derives users.name from a company card (Company Name)', function () {
    $actor = userWithUserAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/users', [
        'email' => 'acme@example.com',
        'locale' => 'en',
        'password' => 'Str0ng-P4ssw0rd!',
        'password_confirmation' => 'Str0ng-P4ssw0rd!',
        'personal_data' => ['type' => 'company', 'company_name' => 'Acme Corp'],
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Acme Corp');

    $this->assertDatabaseHas('users', ['email' => 'acme@example.com', 'name' => 'Acme Corp']);
});

it('create: 422 when personal_data is missing (name source absent)', function () {
    $actor = userWithUserAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/users', [
        'email' => 'noprofile@example.com',
        'locale' => 'en',
        'password' => 'Str0ng-P4ssw0rd!',
        'password_confirmation' => 'Str0ng-P4ssw0rd!',
    ])->assertStatus(422)->assertJsonValidationErrors('personal_data');

    $this->assertDatabaseMissing('users', ['email' => 'noprofile@example.com']);
});

it('create: assigns an assignable role', function () {
    $editor = Role::create(['name' => 'editor']);
    $actor = userWithUserAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/users', [
        'email' => 'roled@example.com',
        'locale' => 'en',
        'password' => 'Str0ng-P4ssw0rd!',
        'password_confirmation' => 'Str0ng-P4ssw0rd!',
        'roles' => [$editor->id],
        'personal_data' => individualProfile(),
    ])->assertCreated();

    expect(User::where('email', 'roled@example.com')->first()->hasRole('editor'))->toBeTrue();
});

it('create: 403 without users.create', function () {
    $actor = userWithUserAbilities([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/users', [
        'email' => 'x@example.com',
        'locale' => 'en',
        'password' => 'Str0ng-P4ssw0rd!',
        'password_confirmation' => 'Str0ng-P4ssw0rd!',
        'personal_data' => individualProfile(),
    ])->assertForbidden();
});

it('create: 422 on duplicate email', function () {
    User::factory()->create(['email' => 'dup@example.com']);
    $actor = userWithUserAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/users', [
        'email' => 'dup@example.com',
        'locale' => 'en',
        'password' => 'Str0ng-P4ssw0rd!',
        'password_confirmation' => 'Str0ng-P4ssw0rd!',
        'personal_data' => individualProfile(),
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

it('create: 422 on invalid locale', function () {
    $actor = userWithUserAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/users', [
        'email' => 'badlocale@example.com',
        'locale' => 'fr', // not in en/it
        'password' => 'Str0ng-P4ssw0rd!',
        'password_confirmation' => 'Str0ng-P4ssw0rd!',
        'personal_data' => individualProfile(),
    ])->assertStatus(422)->assertJsonValidationErrors('locale');
});

it('create: 422 on weak password', function () {
    $actor = userWithUserAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/users', [
        'email' => 'weak@example.com',
        'locale' => 'en',
        'password' => '123',
        'password_confirmation' => '123',
        'personal_data' => individualProfile(),
    ])->assertStatus(422)->assertJsonValidationErrors('password');
});

it('create: 422 on password confirmation mismatch', function () {
    $actor = userWithUserAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/users', [
        'email' => 'mismatch@example.com',
        'locale' => 'en',
        'password' => 'Str0ng-P4ssw0rd!',
        'password_confirmation' => 'Different-P4ssw0rd!',
        'personal_data' => individualProfile(),
    ])->assertStatus(422)->assertJsonValidationErrors('password');
});

it('create: 422 when assigning a non-assignable role (super-admin) as non super-admin', function () {
    $superAdmin = Role::create(['name' => 'super-admin']);
    $actor = userWithUserAbilities(['create']); // not a super-admin
    Sanctum::actingAs($actor);

    $this->postJson('/api/users', [
        'email' => 'escalation@example.com',
        'locale' => 'en',
        'password' => 'Str0ng-P4ssw0rd!',
        'password_confirmation' => 'Str0ng-P4ssw0rd!',
        'roles' => [$superAdmin->id],
        'personal_data' => individualProfile(),
    ])->assertStatus(422)->assertJsonValidationErrors('roles.0');
});

// ---------------------------------------------------------------------------
// update — PUT/PATCH /api/users/{user}
// ---------------------------------------------------------------------------

it('update: 200 full update with PUT, name re-derived from the card', function () {
    $actor = userWithUserAbilities(['update']);
    $target = User::factory()->create(['name' => 'Before', 'locale' => 'en']);
    Sanctum::actingAs($actor);

    $this->putJson("/api/users/{$target->id}", [
        'email' => 'after@example.com',
        'locale' => 'it',
        'personal_data' => ['type' => 'individual', 'first_name' => 'After', 'last_name' => 'Name'],
    ])->assertOk()->assertJsonPath('data.name', 'After Name');

    $this->assertDatabaseHas('users', [
        'id' => $target->id,
        'name' => 'After Name',
        'email' => 'after@example.com',
        'locale' => 'it',
    ]);
});

it('update: changing the card re-derives and persists users.name', function () {
    $actor = userWithUserAbilities(['update']);
    $target = User::factory()->create(['name' => 'Old Name']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'personal_data' => ['type' => 'individual', 'first_name' => 'Brand', 'last_name' => 'New'],
    ])->assertOk()->assertJsonPath('data.name', 'Brand New');

    $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'Brand New']);
});

it('update: without personal_data leaves users.name untouched', function () {
    $actor = userWithUserAbilities(['update']);
    $target = User::factory()->create(['name' => 'Keep Name', 'email' => 'keep@example.com', 'locale' => 'en']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", ['locale' => 'it'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Keep Name');

    $this->assertDatabaseHas('users', [
        'id' => $target->id,
        'name' => 'Keep Name', // unchanged: no card submitted
        'email' => 'keep@example.com',
        'locale' => 'it',
    ]);
});

it('update: email unique ignores the user being edited', function () {
    $actor = userWithUserAbilities(['update']);
    $target = User::factory()->create(['email' => 'self@example.com']);
    Sanctum::actingAs($actor);

    // Submitting the same email back must NOT trigger a unique violation.
    $this->patchJson("/api/users/{$target->id}", [
        'email' => 'self@example.com',
    ])->assertOk();
});

it('update: 422 when email collides with another user', function () {
    $actor = userWithUserAbilities(['update']);
    User::factory()->create(['email' => 'taken@example.com']);
    $target = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", ['email' => 'taken@example.com'])
        ->assertStatus(422)->assertJsonValidationErrors('email');
});

it('update: 403 without users.update', function () {
    $actor = userWithUserAbilities([]);
    $target = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", ['name' => 'Nope'])->assertForbidden();
});

it('update: 404 for a non-existent user', function () {
    $actor = userWithUserAbilities(['update']);
    Sanctum::actingAs($actor);

    $this->patchJson('/api/users/999999', ['name' => 'Ghost'])->assertNotFound();
});

it('update: 422 when assigning a non-assignable role (super-admin) as non super-admin', function () {
    $superAdmin = Role::create(['name' => 'super-admin']);
    $actor = userWithUserAbilities(['update']);
    $target = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", ['roles' => [$superAdmin->id]])
        ->assertStatus(422)->assertJsonValidationErrors('roles.0');
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/users/{user}
// ---------------------------------------------------------------------------

it('delete: 204 and removes the user', function () {
    $actor = userWithUserAbilities(['delete']);
    $target = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/users/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('users', ['id' => $target->id]);
});

it('delete: 403 on self-delete even with users.delete', function () {
    $actor = userWithUserAbilities(['delete']);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/users/{$actor->id}")->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $actor->id]);
});

it('delete: 403 without users.delete', function () {
    $actor = userWithUserAbilities([]);
    $target = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/users/{$target->id}")->assertForbidden();
});

it('delete: 404 for a non-existent user', function () {
    $actor = userWithUserAbilities(['delete']);
    Sanctum::actingAs($actor);

    $this->deleteJson('/api/users/999999')->assertNotFound();
});

it('delete: 422 when deleting the last super-admin', function () {
    Role::create(['name' => 'super-admin']);

    // The actor is a super-admin (Gate::before grants delete), deleting the only
    // other super-admin... actually we need the TARGET to be the last super-admin.
    // Make a single super-admin target and a separate privileged actor.
    $actor = User::factory()->create();
    $actor->assignRole('super-admin');

    $target = User::factory()->create();
    $target->assignRole('super-admin');

    // Now there are 2 super-admins; remove the actor's role so target is the last.
    $actor->removeRole('super-admin');
    // Re-grant delete via explicit permission so the (now non-super-admin) actor can act.
    Permission::findOrCreate('users.delete');
    $actor->givePermissionTo('users.delete');

    Sanctum::actingAs($actor);

    $this->deleteJson("/api/users/{$target->id}")
        ->assertStatus(422);

    $this->assertDatabaseHas('users', ['id' => $target->id]);
});
