<?php

use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['navigation.items' => [
        ['key' => 'hr-section', 'label' => 'navigation.hr', 'icon' => null, 'route' => null, 'type' => 'section', 'children' => [
            ['key' => 'absences', 'label' => 'navigation.absences', 'icon' => null, 'route' => '/absences', 'permission' => 'absences.view'],
            ['key' => 'invoices', 'label' => 'navigation.invoices', 'icon' => null, 'route' => '/invoices', 'permission' => 'invoices.view'],
        ]],
        ['key' => 'dashboard', 'label' => 'navigation.dashboard', 'icon' => null, 'route' => '/dashboard', 'permission' => null],
    ]]);
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    foreach (['absences.view', 'invoices.view'] as $name) {
        Permission::findOrCreate($name, 'web');
    }
});

function workspaceUser(array $permissions = []): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

function workspaceAdminUser(): User
{
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    return $user;
}

function flatKeys(array $tree): array
{
    $keys = [];
    foreach ($tree as $item) {
        $keys[] = $item['key'];
        $keys = array_merge($keys, flatKeys($item['children']));
    }

    return $keys;
}

it('AC-006 prunes the tree to the assigned items the user can see, keeping their section', function () {
    Workspace::factory()->withKeys(['absences', 'invoices'])->create(['slug' => 'hr']);
    Sanctum::actingAs(workspaceUser(['absences.view']));

    $data = $this->getJson('/api/navigation?workspace=hr')->assertOk()->json('data');

    expect(flatKeys($data))->toBe(['hr-section', 'absences'])
        ->and(array_keys($data[0]))->toBe(['key', 'label', 'icon', 'route', 'type', 'children']);
});

it('AC-007 returns 404 for an unknown workspace or one with nothing visible', function () {
    Workspace::factory()->withKeys(['absences', 'invoices'])->create(['slug' => 'hr']);
    Sanctum::actingAs(workspaceUser());

    $this->getJson('/api/navigation?workspace=hr')->assertNotFound();
    $this->getJson('/api/navigation?workspace=ghost')->assertNotFound();
});

it('AC-008 keeps today behaviour while no workspace exists', function () {
    Sanctum::actingAs(workspaceUser(['absences.view']));

    expect(flatKeys($this->getJson('/api/navigation')->assertOk()->json('data')))
        ->toBe(['hr-section', 'absences', 'dashboard']);

    $this->getJson('/api/workspaces/available')->assertOk()
        ->assertJsonPath('data', ['workspaces' => [], 'full_view_available' => true]);
});

it('AC-009 gives the full tree only to the super-admin once a workspace exists', function () {
    Workspace::factory()->withKeys(['absences'])->create(['slug' => 'hr']);

    Sanctum::actingAs(workspaceUser(['absences.view']));
    $this->getJson('/api/navigation')->assertOk()->assertJsonPath('data', []);
    $this->getJson('/api/workspaces/available')->assertJsonPath('data.full_view_available', false);

    Sanctum::actingAs(workspaceAdminUser());
    expect(flatKeys($this->getJson('/api/navigation')->json('data')))->toContain('hr-section', 'dashboard');
    $this->getJson('/api/workspaces/available')->assertJsonPath('data.full_view_available', true);
});

it('AC-010 lists only visible workspaces ordered by sort_order and name, with the first visible route as home', function () {
    Workspace::factory()->withKeys(['invoices', 'absences'])->create(['slug' => 'b-app', 'name' => 'B', 'sort_order' => 1]);
    Workspace::factory()->withKeys(['dashboard'])->create(['slug' => 'a-app', 'name' => 'A', 'sort_order' => 2]);
    Workspace::factory()->withKeys(['invoices'])->create(['slug' => 'hidden', 'name' => 'C', 'sort_order' => 0]);
    Sanctum::actingAs(workspaceUser(['absences.view']));

    $this->getJson('/api/workspaces/available')->assertOk()
        ->assertJsonCount(2, 'data.workspaces')
        ->assertJsonPath('data.workspaces.0', ['slug' => 'b-app', 'name' => 'B', 'description' => null, 'icon' => null, 'color' => null, 'home_route' => '/absences'])
        ->assertJsonPath('data.workspaces.1.slug', 'a-app')
        ->assertJsonPath('data.workspaces.1.home_route', '/dashboard');
});

it('AC-011 ignores keys no longer in config in navigation and available', function () {
    Workspace::factory()->withKeys(['removed-page', 'dashboard'])->create(['slug' => 'misc']);
    Sanctum::actingAs(workspaceUser());

    expect(flatKeys($this->getJson('/api/navigation?workspace=misc')->assertOk()->json('data')))->toBe(['dashboard']);
    $this->getJson('/api/workspaces/available')->assertOk()->assertJsonPath('data.workspaces.0.home_route', '/dashboard');
});
