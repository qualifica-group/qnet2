<?php

use App\Models\EmploymentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('requires authentication', function () {
    $this->getJson('/api/auth/me/abilities')->assertUnauthorized();
});

it('returns the full permission map with booleans and the user roles', function () {
    Permission::create(['name' => 'users.view']);
    Permission::create(['name' => 'users.delete']);
    Role::create(['name' => 'admin']);

    $user = User::factory()->create();
    $user->assignRole('admin');
    $user->givePermissionTo('users.view');

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/auth/me/abilities')
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($response->json('data.permissions'))->toEqual([
        'users.delete' => false,
        'users.view' => true,
    ]);
    expect($response->json('data.roles'))->toEqual(['admin']);
});

it('grants a permission inherited through a role', function () {
    $permission = Permission::create(['name' => 'users.update']);
    $role = Role::create(['name' => 'manager']);
    $role->givePermissionTo($permission);

    $user = User::factory()->create();
    $user->assignRole('manager');

    Sanctum::actingAs($user);

    expect($this->getJson('/api/auth/me/abilities')->json('data.permissions'))
        ->toEqual(['users.update' => true]);
});

it('returns all defined permissions as granted for the super-admin role', function () {
    Permission::create(['name' => 'users.view']);
    Permission::create(['name' => 'users.delete']);
    Role::create(['name' => 'super-admin']);

    $user = User::factory()->create();
    $user->assignRole('super-admin');

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/auth/me/abilities')
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($response->json('data.permissions'))->toEqual([
        'users.delete' => true,
        'users.view' => true,
    ]);
    expect($response->json('data.roles'))->toEqual(['super-admin']);
});

it('0309 AC-001: has_subordinates is true only with an active descendant', function () {
    $manager = User::factory()->create();
    Sanctum::actingAs($manager);

    $this->getJson('/api/auth/me/abilities')->assertOk()->assertJsonPath('data.has_subordinates', false);

    $gone = User::factory()->create(['is_active' => false]);
    EmploymentProfile::factory()->create(['user_id' => $gone->id])->reportsTo()->attach($manager->id);

    $this->getJson('/api/auth/me/abilities')->assertJsonPath('data.has_subordinates', false);

    $report = User::factory()->create();
    EmploymentProfile::factory()->create(['user_id' => $report->id])->reportsTo()->attach($manager->id);

    $this->getJson('/api/auth/me/abilities')->assertJsonPath('data.has_subordinates', true);
});

it('0309 AC-001: has_subordinates is the real value for a super-admin too', function () {
    Role::create(['name' => 'super-admin']);
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');
    Sanctum::actingAs($admin);

    $this->getJson('/api/auth/me/abilities')->assertOk()->assertJsonPath('data.has_subordinates', false);

    $report = User::factory()->create();
    EmploymentProfile::factory()->create(['user_id' => $report->id])->reportsTo()->attach($admin->id);

    $this->getJson('/api/auth/me/abilities')->assertJsonPath('data.has_subordinates', true);
});
