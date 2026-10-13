<?php

use App\Authorization\AssignablePermissionCatalogue;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

const API_CLIENT_PERMISSIONS = ['api-clients.view', 'api-clients.create', 'api-clients.update', 'api-clients.delete'];

it('offers the api-clients permissions in the assignable role catalogue', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    $catalogue = app(AssignablePermissionCatalogue::class);

    foreach (API_CLIENT_PERMISSIONS as $name) {
        expect($catalogue->isAssignable($name))->toBeTrue();
    }

    expect($catalogue->names())->toContain(...API_CLIENT_PERMISSIONS);
});

it('assigns api-clients permissions to a non super-admin role and unlocks the admin endpoints', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    $manager = apiClientAdmin([]);
    foreach (['roles.create', 'roles.viewAny'] as $name) {
        Permission::findOrCreate($name);
        $manager->givePermissionTo($name);
    }
    Sanctum::actingAs($manager);

    $this->postJson('/api/roles', ['name' => 'integrator', 'permissions' => ['api-clients.view', 'api-clients.create']])
        ->assertCreated();

    expect(Role::findByName('integrator', 'web')->getPermissionNames()->sort()->values()->all())
        ->toBe(['api-clients.create', 'api-clients.view']);

    $user = apiClientAdmin([]);
    $user->assignRole(Role::findByName('integrator', 'web'));
    Sanctum::actingAs($user);

    $id = $this->postJson('/api/api-clients', ['name' => 'Via role'])->assertCreated()->json('data.client.id');
    $this->getJson("/api/api-clients/{$id}")->assertOk();
});
