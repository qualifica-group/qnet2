<?php

use App\Models\CompanySite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('permissions:sync creates the full company-sites.* CRUD catalogue', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::whereIn('name', [
        'company-sites.viewAny', 'company-sites.view', 'company-sites.create',
        'company-sites.update', 'company-sites.delete', 'company-sites.export', 'company-sites.import',
    ])->count())->toBe(7);
});

it('hides the /company-sites navigation node without company-sites.view', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/navigation')
        ->assertOk()
        ->assertJsonMissing(['key' => 'company-sites']);
});

it('shows the /company-sites navigation node with company-sites.view', function () {
    Permission::findOrCreate('company-sites.view');
    $actor = User::factory()->create();
    $actor->givePermissionTo('company-sites.view');
    Sanctum::actingAs($actor);

    $items = $this->getJson('/api/navigation')->assertOk()->json('data');

    expect(navigationNodeKeys($items))->toContain('company-sites');
});

it('a super-admin bypasses company-sites authorization via Gate::before', function () {
    Permission::findOrCreate('company-sites.view');
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('super-admin'));
    Sanctum::actingAs($admin);

    $target = CompanySite::factory()->create();

    $this->getJson("/api/company-sites/{$target->id}")->assertOk();
});
