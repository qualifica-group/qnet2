<?php

use App\Models\Role;
use App\Models\User;
use App\Services\NavigationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-019 — navigation `role` gate: additive, non-regression on permissions
// ---------------------------------------------------------------------------

it('includes an item gated by role ONLY for a user holding that role', function () {
    config(['navigation.items' => [
        ['key' => 'migrations', 'label' => 'navigation.migrations', 'route' => '/migrations', 'permission' => null, 'role' => 'super-admin'],
    ]]);

    Role::query()->firstOrCreate(['name' => 'super-admin']);
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    $ordinary = User::factory()->create();

    expect(app(NavigationService::class)->for($superAdmin))->toHaveCount(1)
        ->and(app(NavigationService::class)->for($ordinary))->toHaveCount(0);
});

it('exposes the "Migrazioni" item to a super-admin via GET /api/navigation, hidden for others', function () {
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    Sanctum::actingAs($superAdmin);
    $adminSection = collect($this->getJson('/api/navigation')->json('data'))->firstWhere('key', 'develop');
    $migrationsItem = collect(data_get($adminSection, 'children', []))->firstWhere('key', 'migrations');
    expect($migrationsItem)->not->toBeNull()
        ->and($migrationsItem['route'])->toBe('/dev/migrations');

    $ordinary = User::factory()->create();
    Sanctum::actingAs($ordinary);
    $adminSection = collect($this->getJson('/api/navigation')->json('data'))->firstWhere('key', 'develop');
    $hidden = collect(data_get($adminSection, 'children', []))->firstWhere('key', 'migrations');
    expect($hidden)->toBeNull();
});

it('does not affect items with no role restriction (non-regression)', function () {
    config(['navigation.items' => [
        ['key' => 'dashboard', 'label' => 'navigation.dashboard', 'route' => '/dashboard', 'permission' => null],
    ]]);

    expect(app(NavigationService::class)->for(User::factory()->create()))->toHaveCount(1);
});

it('generates NO permission for the role-gated item (SyncPermissions untouched)', function () {
    config(['navigation.items' => [
        ['key' => 'migrations', 'label' => 'navigation.migrations', 'route' => '/migrations', 'permission' => null, 'role' => 'super-admin'],
    ]]);

    expect(app(NavigationService::class)->permissions())->toBe([]);
});
