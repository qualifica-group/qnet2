<?php

use App\DataObjects\PersonalData\CreatePersonalData;
use App\DataObjects\Users\CreateUserData;
use App\DataObjects\Users\ProfileData;
use App\DataObjects\Users\UpdateUserData;
use App\Enums\PersonalDataTypeEnum;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Privilege escalation — HTTP layer (FormRequest) blocks super-admin.
// ---------------------------------------------------------------------------

it('store: a non super-admin with users.create cannot assign super-admin (422)', function () {
    $superAdmin = Role::create(['name' => 'super-admin']);
    $editor = Role::create(['name' => 'editor']);
    $actor = userWithUserAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/users', [
        'email' => 'climber@example.com',
        'locale' => 'en',
        'password' => 'Str0ng-P4ssw0rd!',
        'password_confirmation' => 'Str0ng-P4ssw0rd!',
        'roles' => [$editor->id, $superAdmin->id], // editor ok, super-admin forbidden
        'personal_data' => ['type' => 'individual', 'first_name' => 'Climb', 'last_name' => 'Er'],
    ])->assertStatus(422)->assertJsonValidationErrors('roles.1');

    expect(User::where('email', 'climber@example.com')->exists())->toBeFalse();
});

it('update: a non super-admin with users.update cannot assign super-admin (422)', function () {
    $superAdmin = Role::create(['name' => 'super-admin']);
    $actor = userWithUserAbilities(['update']);
    $target = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", ['roles' => [$superAdmin->id]])
        ->assertStatus(422)->assertJsonValidationErrors('roles.0');

    expect($target->fresh()->hasRole('super-admin'))->toBeFalse();
});

it('a super-admin actor CAN assign super-admin (positive control)', function () {
    $superAdmin = Role::create(['name' => 'super-admin']);
    $actor = User::factory()->create();
    $actor->assignRole('super-admin'); // Gate::before grants users.create
    Sanctum::actingAs($actor);

    $this->postJson('/api/users', [
        'email' => 'anointed@example.com',
        'locale' => 'en',
        'password' => 'Str0ng-P4ssw0rd!',
        'password_confirmation' => 'Str0ng-P4ssw0rd!',
        'roles' => [$superAdmin->id],
        'personal_data' => ['type' => 'individual', 'first_name' => 'An', 'last_name' => 'Ointed'],
    ])->assertCreated();

    expect(User::where('email', 'anointed@example.com')->first()->hasRole('super-admin'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Privilege escalation — SERVICE layer bypass (FormRequest skipped).
// UserService is the final authority and must strip super-admin itself.
// ---------------------------------------------------------------------------

it('service create() silently drops super-admin for a non super-admin actor (bypass blocked)', function () {
    Role::create(['name' => 'super-admin']);
    Role::create(['name' => 'editor']);
    $actor = userWithUserAbilities(['create']); // not a super-admin

    $created = app(UserService::class)->create($actor, CreateUserData::fromValidated([
        'email' => 'bypass@example.com',
        'locale' => 'en',
        'password' => 'Str0ng-P4ssw0rd!',
        'roles' => ['editor', 'super-admin'],
    ]), new ProfileData(
        card: new CreatePersonalData(
            type: PersonalDataTypeEnum::Individual,
            firstName: 'By',
            lastName: 'Pass',
        ),
    ));

    expect($created->hasRole('super-admin'))->toBeFalse()
        ->and($created->hasRole('editor'))->toBeTrue();
});

it('service update() silently drops super-admin for a non super-admin actor (bypass blocked)', function () {
    Role::create(['name' => 'super-admin']);
    $actor = userWithUserAbilities(['update']);
    $target = User::factory()->create();

    $updated = app(UserService::class)->update($actor, $target, UpdateUserData::fromValidated([
        'roles' => ['super-admin'],
    ]));

    expect($updated->fresh()->hasRole('super-admin'))->toBeFalse();
});

it('service assignableRoleNames excludes super-admin for a non super-admin actor', function () {
    Role::create(['name' => 'super-admin']);
    Role::create(['name' => 'editor']);
    Role::create(['name' => 'manager']);
    $actor = User::factory()->create();

    expect(app(UserService::class)->assignableRoleNames($actor))
        ->toEqualCanonicalizing(['editor', 'manager'])
        ->not->toContain('super-admin');
});

it('service assignableRoleNames includes super-admin for a super-admin actor', function () {
    Role::create(['name' => 'super-admin']);
    Role::create(['name' => 'editor']);
    $actor = User::factory()->create();
    $actor->assignRole('super-admin');

    expect(app(UserService::class)->assignableRoleNames($actor))
        ->toContain('super-admin', 'editor');
});

// ---------------------------------------------------------------------------
// Last super-admin guards at the service layer.
// ---------------------------------------------------------------------------

it('service update() blocks stripping the role from the last super-admin (422)', function () {
    Role::create(['name' => 'super-admin']);
    $actor = User::factory()->create();
    $actor->assignRole('super-admin'); // actor is super-admin so it may assign roles

    // The actor is itself the only super-admin; try to strip its own role via update.
    expect(fn () => app(UserService::class)->update($actor, $actor, UpdateUserData::fromValidated(['roles' => []])))
        ->toThrow(HttpException::class);

    expect($actor->fresh()->hasRole('super-admin'))->toBeTrue();
});

it('service delete() blocks deleting the last super-admin (422)', function () {
    Role::create(['name' => 'super-admin']);
    $target = User::factory()->create();
    $target->assignRole('super-admin');

    expect(fn () => app(UserService::class)->delete($target))
        ->toThrow(HttpException::class);

    expect(User::whereKey($target->id)->exists())->toBeTrue();
});
