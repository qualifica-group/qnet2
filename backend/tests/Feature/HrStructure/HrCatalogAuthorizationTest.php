<?php

use App\Models\JobActivity;
use App\Models\JobRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

dataset('hr_catalogues', [
    'job-roles' => ['job-roles', fn () => JobRole::factory()->create()],
    'job-activities' => ['job-activities', fn () => JobActivity::factory()->create()],
]);

it('AC-007: every endpoint of the catalogue answers 403 without permissions', function (string $resource, Closure $make) {
    Sanctum::actingAs(User::factory()->create());
    $id = $make()->id;

    $this->getJson("/api/{$resource}/{$id}")->assertForbidden();
    $this->postJson("/api/{$resource}", ['name' => 'X'])->assertForbidden();
    $this->putJson("/api/{$resource}/{$id}", ['name' => 'X'])->assertForbidden();
    $this->deleteJson("/api/{$resource}/{$id}")->assertForbidden();
    $this->getJson("/api/tables/{$resource}/columns")->assertForbidden();
    $this->postJson("/api/tables/{$resource}/rows", ['startRow' => 0, 'endRow' => 10])->assertForbidden();
})->with('hr_catalogues');

it('AC-007: for-select is open to any authenticated user and 401 without login', function (string $resource) {
    $this->getJson("/api/{$resource}/for-select")->assertUnauthorized();

    Sanctum::actingAs(User::factory()->create());
    $this->getJson("/api/{$resource}/for-select")->assertOk();
})->with(['job-roles', 'job-activities']);

it('AC-007: each ability opens only its own endpoint', function (string $resource, Closure $make) {
    $id = $make()->id;

    Sanctum::actingAs(hrCatalogUserWith($resource, ['view']));
    $this->getJson("/api/{$resource}/{$id}")->assertOk();
    $this->postJson("/api/{$resource}", ['name' => 'X'])->assertForbidden();
    $this->putJson("/api/{$resource}/{$id}", ['name' => 'X'])->assertForbidden();
    $this->deleteJson("/api/{$resource}/{$id}")->assertForbidden();

    Sanctum::actingAs(hrCatalogUserWith($resource, ['create']));
    $this->postJson("/api/{$resource}", ['name' => 'Brand new'])->assertCreated();
    $this->getJson("/api/{$resource}/{$id}")->assertForbidden();
})->with('hr_catalogues');

it('AC-007: permissions:sync mints the four permissions per catalogue and super-admin holds them', function (string $resource) {
    $this->artisan('permissions:sync')->assertSuccessful();

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(Role::findOrCreate('super-admin', 'web'));
    Sanctum::actingAs($superAdmin);

    foreach (['view', 'create', 'update', 'delete'] as $ability) {
        $this->assertDatabaseHas('permissions', ['name' => "{$resource}.{$ability}"]);
        expect($superAdmin->can("{$resource}.{$ability}"))->toBeTrue();
    }

    $this->postJson("/api/{$resource}", ['name' => 'By admin'])->assertCreated();
})->with(['job-roles', 'job-activities']);

it('AC-016: the Configuration menu lists each catalogue only to who holds its .view', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    $keysFor = function (User $user): array {
        Sanctum::actingAs($user);

        // REQUIREMENT CHANGED (spec 0318): the catalogues sit one level deeper, in Configurazione > Personale.
        return navigationNodeKeys($this->getJson('/api/navigation')->assertOk()->json('data'))->all();
    };

    expect($keysFor(User::factory()->create()))
        ->not->toContain('job-roles', 'job-activities');

    $user = User::factory()->create();
    $user->givePermissionTo('job-roles.view');
    $visible = $keysFor($user);

    expect($visible)->toContain('job-roles')
        ->not->toContain('job-activities');
});
