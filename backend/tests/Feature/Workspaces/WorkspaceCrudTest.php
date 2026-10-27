<?php

use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Support\BadgeTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['navigation.items' => [
        ['key' => 'hr-section', 'label' => 'navigation.hr', 'icon' => null, 'route' => null, 'type' => 'section', 'children' => [
            ['key' => 'absences', 'label' => 'navigation.absences', 'icon' => 'calendar', 'route' => '/absences', 'permission' => null],
            ['key' => 'invoices', 'label' => 'navigation.invoices', 'icon' => null, 'route' => '/invoices', 'permission' => null],
        ]],
        ['key' => 'dashboard', 'label' => 'navigation.dashboard', 'icon' => null, 'route' => '/dashboard', 'permission' => null],
    ]]);
});

function workspaceSuperAdmin(): User
{
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    return $user;
}

function workspacePayload(array $overrides = []): array
{
    return array_merge([
        'slug' => 'hr',
        'name' => 'HR',
        'icon' => BadgeTokens::icons()[0],
        'color' => 'teal',
        'sort_order' => 2,
        'navigation_keys' => ['invoices', 'absences'],
    ], $overrides);
}

it('AC-001 creates a workspace with its items and returns the resource', function () {
    Sanctum::actingAs(workspaceSuperAdmin());

    $response = $this->postJson('/api/workspaces', workspacePayload())->assertCreated()
        ->assertJsonPath('data.slug', 'hr')
        ->assertJsonPath('data.sort_order', 2)
        // global menu order, not the order sent
        ->assertJsonPath('data.navigation_keys', ['absences', 'invoices']);

    $id = $response->json('data.id');
    expect(Workspace::query()->count())->toBe(1)
        ->and(Workspace::query()->find($id)->items()->pluck('navigation_key')->sort()->values()->all())->toBe(['absences', 'invoices']);
});

it('AC-002 forbids management and catalogue to a non super-admin with every permission, 401 anonymous', function () {
    $workspace = Workspace::factory()->withKeys(['absences'])->create();
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::query()->get());

    $calls = [
        ['getJson', '/api/workspaces'],
        ['postJson', '/api/workspaces', workspacePayload()],
        ['getJson', "/api/workspaces/{$workspace->id}"],
        ['putJson', "/api/workspaces/{$workspace->id}", workspacePayload(['slug' => 'other'])],
        ['deleteJson', "/api/workspaces/{$workspace->id}"],
        ['getJson', '/api/workspaces/navigation-catalogue'],
    ];

    foreach ($calls as $call) {
        $this->{$call[0]}($call[1], $call[2] ?? [])->assertUnauthorized();
    }

    Sanctum::actingAs($user);
    foreach ($calls as $call) {
        $this->{$call[0]}($call[1], $call[2] ?? [])->assertForbidden();
    }

    expect(Workspace::query()->count())->toBe(1)
        ->and($workspace->fresh()->name)->toBe($workspace->name);
});

it('AC-003 rejects invalid payloads with 422 and writes nothing', function (array $overrides, string $field) {
    Workspace::factory()->create(['slug' => 'taken']);
    Sanctum::actingAs(workspaceSuperAdmin());

    $this->postJson('/api/workspaces', workspacePayload($overrides))
        ->assertUnprocessable()->assertJsonValidationErrors($field);

    expect(Workspace::query()->count())->toBe(1);
})->with([
    'bad slug format' => [['slug' => 'Not Valid'], 'slug'],
    'duplicate slug' => [['slug' => 'taken'], 'slug'],
    'empty name' => [['name' => ''], 'name'],
    'empty keys' => [['navigation_keys' => []], 'navigation_keys'],
    'duplicate keys' => [['navigation_keys' => ['absences', 'absences']], 'navigation_keys.1'],
    'key outside catalogue' => [['navigation_keys' => ['hr-section']], 'navigation_keys.0'],
    'unknown key' => [['navigation_keys' => ['ghost']], 'navigation_keys.0'],
]);

it('AC-003 keeps the stored data when an update is invalid', function () {
    $workspace = Workspace::factory()->withKeys(['absences'])->create(['slug' => 'hr']);
    Sanctum::actingAs(workspaceSuperAdmin());

    $this->putJson("/api/workspaces/{$workspace->id}", workspacePayload(['navigation_keys' => ['ghost']]))
        ->assertUnprocessable();

    expect($workspace->items()->pluck('navigation_key')->all())->toBe(['absences']);
});

it('AC-004 replaces the key set on update (own slug allowed) and removes everything on delete', function () {
    $workspace = Workspace::factory()->withKeys(['absences', 'invoices'])->create(['slug' => 'hr']);
    Sanctum::actingAs(workspaceSuperAdmin());

    $this->putJson("/api/workspaces/{$workspace->id}", workspacePayload(['navigation_keys' => ['invoices', 'dashboard']]))
        ->assertOk()->assertJsonPath('data.navigation_keys', ['invoices', 'dashboard']);

    expect($workspace->items()->pluck('navigation_key')->sort()->values()->all())->toBe(['dashboard', 'invoices']);

    $this->deleteJson("/api/workspaces/{$workspace->id}")->assertOk()->assertJsonPath('data', null);
    $this->assertDatabaseCount('workspaces', 0);
    $this->assertDatabaseCount('workspace_navigation_items', 0);
});

it('AC-005 lists the global catalogue with ancestor path, in menu order', function () {
    Sanctum::actingAs(workspaceSuperAdmin());

    $this->getJson('/api/workspaces/navigation-catalogue')->assertOk()
        ->assertJsonPath('data', [
            ['key' => 'absences', 'label' => 'navigation.absences', 'icon' => 'calendar', 'route' => '/absences', 'path' => ['navigation.hr']],
            ['key' => 'invoices', 'label' => 'navigation.invoices', 'icon' => null, 'route' => '/invoices', 'path' => ['navigation.hr']],
            ['key' => 'dashboard', 'label' => 'navigation.dashboard', 'icon' => null, 'route' => '/dashboard', 'path' => []],
        ]);
});

it('AC-011 ignores keys no longer in config when reading the resource', function () {
    $workspace = Workspace::factory()->withKeys(['absences', 'removed-page'])->create();
    Sanctum::actingAs(workspaceSuperAdmin());

    $this->getJson("/api/workspaces/{$workspace->id}")->assertOk()
        ->assertJsonPath('data.navigation_keys', ['absences']);
});

it('AC-012 writes activity log entries on create, update and delete', function () {
    Sanctum::actingAs(workspaceSuperAdmin());

    $id = $this->postJson('/api/workspaces', workspacePayload())->json('data.id');
    expect(Activity::query()->where('log_name', 'workspaces')->where('event', 'created')->count())->toBe(1);

    $this->putJson("/api/workspaces/{$id}", workspacePayload(['name' => 'Human Resources']))->assertOk();
    expect(Activity::query()->where('log_name', 'workspaces')->where('event', 'updated')->count())->toBe(1);

    $this->putJson("/api/workspaces/{$id}", workspacePayload(['name' => 'Human Resources', 'navigation_keys' => ['dashboard']]))->assertOk();
    expect(Activity::query()->where('log_name', 'workspaces')->where('event', 'updated')->count())->toBe(2);

    $this->deleteJson("/api/workspaces/{$id}")->assertOk();
    expect(Activity::query()->where('log_name', 'workspaces')->where('event', 'deleted')->count())->toBe(1);
});

it('AC-022 rejects icon or color outside the allow-lists on create and update, writing nothing', function (array $overrides, string $field) {
    $workspace = Workspace::factory()->withKeys(['absences'])->create(['slug' => 'hr', 'name' => 'Original']);
    Sanctum::actingAs(workspaceSuperAdmin());

    $this->postJson('/api/workspaces', workspacePayload(['slug' => 'new', ...$overrides]))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->putJson("/api/workspaces/{$workspace->id}", workspacePayload($overrides))
        ->assertUnprocessable()->assertJsonValidationErrors($field);

    expect(Workspace::query()->count())->toBe(1)->and($workspace->fresh()->name)->toBe('Original');
})->with([
    'icon outside list' => [['icon' => 'not-an-icon'], 'icon'],
    'color outside palette' => [['color' => 'chartreuse'], 'color'],
]);

it('AC-022 stores valid or null icon and color and exposes them in resource and available', function () {
    Sanctum::actingAs(workspaceSuperAdmin());
    $icon = BadgeTokens::icons()[0];

    $id = $this->postJson('/api/workspaces', workspacePayload(['icon' => $icon, 'color' => 'teal', 'navigation_keys' => ['dashboard']]))
        ->assertCreated()->assertJsonPath('data.icon', $icon)->assertJsonPath('data.color', 'teal')->json('data.id');

    $this->getJson('/api/workspaces/available')
        ->assertJsonPath('data.workspaces.0.icon', $icon)->assertJsonPath('data.workspaces.0.color', 'teal');

    $this->putJson("/api/workspaces/{$id}", workspacePayload(['icon' => null, 'color' => null, 'navigation_keys' => ['dashboard']]))
        ->assertOk()->assertJsonPath('data.icon', null)->assertJsonPath('data.color', null);
    $this->getJson('/api/workspaces/available')->assertJsonPath('data.workspaces.0.color', null);
});

it('AC-026 rejects a description over 160 characters on create and update, writing nothing', function () {
    $workspace = Workspace::factory()->withKeys(['absences'])->create(['slug' => 'hr', 'name' => 'Original']);
    Sanctum::actingAs(workspaceSuperAdmin());
    $tooLong = str_repeat('a', 161);

    $this->postJson('/api/workspaces', workspacePayload(['slug' => 'new', 'description' => $tooLong]))
        ->assertUnprocessable()->assertJsonValidationErrors('description');
    $this->putJson("/api/workspaces/{$workspace->id}", workspacePayload(['description' => $tooLong]))
        ->assertUnprocessable()->assertJsonValidationErrors('description');

    expect(Workspace::query()->count())->toBe(1)->and($workspace->fresh()->name)->toBe('Original');
});

it('AC-026 stores a valid description, turns blank into null and exposes it in resource and available', function () {
    Sanctum::actingAs(workspaceSuperAdmin());

    $id = $this->postJson('/api/workspaces', workspacePayload(['description' => str_repeat('a', 160), 'navigation_keys' => ['dashboard']]))
        ->assertCreated()->assertJsonPath('data.description', str_repeat('a', 160))->json('data.id');
    $this->getJson('/api/workspaces/available')->assertJsonPath('data.workspaces.0.description', str_repeat('a', 160));

    $this->putJson("/api/workspaces/{$id}", workspacePayload(['description' => '   ', 'navigation_keys' => ['dashboard']]))
        ->assertOk()->assertJsonPath('data.description', null);
    $this->getJson('/api/workspaces/available')->assertJsonPath('data.workspaces.0.description', null);
});
