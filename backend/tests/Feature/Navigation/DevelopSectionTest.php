<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function navSection(string $key): ?array
{
    return collect(test()->getJson('/api/navigation')->json('data'))->firstWhere('key', $key);
}

function navChildKeys(?array $section): array
{
    return collect(data_get($section, 'children', []))->pluck('key')->all();
}

it('gives a super-admin the four develop children in order, with the new /dev routes', function () {
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    Permission::query()->firstOrCreate(['name' => 'api-clients.view', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    $user->givePermissionTo('api-clients.view');
    Sanctum::actingAs($user);

    $develop = navSection('develop');

    expect(navChildKeys($develop))->toBe(['migrations', 'system-health', 'api-integrations', 'api-docs'])
        ->and(collect($develop['children'])->pluck('route')->all())
        ->toBe(['/dev/migrations', '/dev/system-health', '/dev/api-clients', '/dev/api-docs']);
});

it('shows develop with only the API items to a user holding api-clients.view', function () {
    Permission::query()->firstOrCreate(['name' => 'api-clients.view', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->givePermissionTo('api-clients.view');
    Sanctum::actingAs($user);

    expect(navChildKeys(navSection('develop')))->toBe(['api-integrations', 'api-docs']);
});

it('hides the develop section from a user without permissions', function () {
    Sanctum::actingAs(User::factory()->create());

    expect(navSection('develop'))->toBeNull();
});

it('no longer lists the developer items under administration', function () {
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    Permission::query()->firstOrCreate(['name' => 'api-clients.view', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    $user->givePermissionTo('api-clients.view');
    Sanctum::actingAs($user);

    expect(navChildKeys(navSection('administration')))
        ->not->toContain('migrations', 'system-health', 'api-integrations');
});

it('places develop after administration', function () {
    $order = array_column(config('navigation.items'), 'key');

    expect(array_search('develop', $order, true))->toBe(array_search('administration', $order, true) + 1);
});
