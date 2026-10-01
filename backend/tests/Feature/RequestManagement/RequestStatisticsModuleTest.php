<?php

use App\Models\Role;
use App\Models\User;
use App\RequestManagement\RequestModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

// Spec 0185 — "Statistiche Gestione Richieste": the request dashboard and its
// report are a module of their own, gated by `request-statistics.view`, which
// replaces the former `{module}.report` ability.

uses(RefreshDatabase::class);

const STATISTICS_MIGRATION = 'migrations/2026_10_01_100000_move_request_report_permissions_to_request_statistics.php';

if (! function_exists('statisticsNavigationChild')) {
    /** @return array<string, mixed>|null */
    function statisticsNavigationChild(mixed $groups): ?array
    {
        $group = collect($groups)->firstWhere('key', 'opportunities-group');

        return $group === null ? null : collect($group['children'])->firstWhere('key', 'request-statistics');
    }
}

it('opens the dashboard with request-statistics.view alone (AC-001)', function () {
    Permission::findOrCreate(RequestModule::STATISTICS_PERMISSION);
    Sanctum::actingAs(User::factory()->create()->givePermissionTo(RequestModule::STATISTICS_PERMISSION));

    $this->getJson('/api/request-management/report/categories')->assertOk();
});

it('403s every statistics endpoint without request-statistics.view, whatever request-management grant is held (AC-002)', function (string $path) {
    $grants = ['request-management.viewAny', 'request-management.view', 'request-management.viewAll'];
    foreach ($grants as $grant) {
        Permission::findOrCreate($grant);
    }
    Sanctum::actingAs(User::factory()->create()->givePermissionTo($grants));

    $this->getJson("/api/request-management/report/{$path}")->assertForbidden();
    // The dashboard validates its query before authorizing: its own 403 is
    // RequestManagementDashboardRequestTest's, with a valid payload.
})->with(['categories', 'operators', 'sites']);

it('404s the enrollee-management dashboard (AC-003)', function () {
    Permission::findOrCreate(RequestModule::STATISTICS_PERMISSION);
    Sanctum::actingAs(User::factory()->create()->givePermissionTo(RequestModule::STATISTICS_PERMISSION));

    $this->getJson('/api/enrollee-management/report/dashboard')->assertNotFound();
});

it('permissions:sync mints request-statistics.view and no report ability any more (AC-004)', function () {
    Artisan::call('permissions:sync');

    expect(Permission::query()->where('name', RequestModule::STATISTICS_PERMISSION)->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'like', '%.report')->exists())->toBeFalse();
});

it('moves every holder of a former report grant onto request-statistics.view (AC-005)', function () {
    $requests = Role::findOrCreate('requests-reporter');
    $requests->givePermissionTo(Permission::findOrCreate('request-management.report'));
    $enrollees = Role::findOrCreate('enrollees-reporter');
    $enrollees->givePermissionTo(Permission::findOrCreate('enrollee-management.report'));
    $both = Role::findOrCreate('both-reporter');
    $both->givePermissionTo(['request-management.report', 'enrollee-management.report']);
    $untouched = Role::findOrCreate('viewer');
    $untouched->givePermissionTo(Permission::findOrCreate('request-management.viewAll'));
    $direct = User::factory()->create()->givePermissionTo('request-management.report');

    (require database_path(STATISTICS_MIGRATION))->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $newId = Permission::query()->where('name', RequestModule::STATISTICS_PERMISSION)->value('id');

    expect(Permission::query()->where('name', 'like', '%.report')->exists())->toBeFalse()
        ->and($requests->fresh()->hasPermissionTo(RequestModule::STATISTICS_PERMISSION))->toBeTrue()
        ->and($enrollees->fresh()->hasPermissionTo(RequestModule::STATISTICS_PERMISSION))->toBeTrue()
        ->and(DB::table('role_has_permissions')->where('role_id', $both->id)->where('permission_id', $newId)->count())->toBe(1)
        ->and($untouched->fresh()->hasPermissionTo(RequestModule::STATISTICS_PERMISSION))->toBeFalse()
        ->and($direct->fresh()->hasDirectPermission(RequestModule::STATISTICS_PERMISSION))->toBeTrue();
});

it('the migration reuses an already synced request-statistics.view', function () {
    Artisan::call('permissions:sync');
    $role = Role::findOrCreate('requests-reporter');
    $role->givePermissionTo(Permission::findOrCreate('request-management.report'));

    (require database_path(STATISTICS_MIGRATION))->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(Permission::query()->where('name', RequestModule::STATISTICS_PERMISSION)->count())->toBe(1)
        ->and($role->fresh()->hasPermissionTo(RequestModule::STATISTICS_PERMISSION))->toBeTrue();
});

it('shows the menu entry only to a holder of request-statistics.view', function () {
    Permission::findOrCreate(RequestModule::STATISTICS_PERMISSION);
    Permission::findOrCreate('request-management.view');

    Sanctum::actingAs(User::factory()->create()->givePermissionTo(RequestModule::STATISTICS_PERMISSION));
    $child = statisticsNavigationChild($this->getJson('/api/navigation')->assertOk()->json('data'));

    expect($child)->not->toBeNull()
        ->and($child['route'])->toBe('/request-statistics')
        ->and($child['label'])->toBe('navigation.requestStatistics');

    Sanctum::actingAs(User::factory()->create()->givePermissionTo('request-management.view'));

    expect(statisticsNavigationChild($this->getJson('/api/navigation')->assertOk()->json('data')))->toBeNull();
});

it('lists the module and its view permission in the Role form catalogue (AC-008)', function () {
    Artisan::call('permissions:sync');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Sanctum::actingAs(User::factory()->create()->givePermissionTo('roles.viewAny'));

    $areas = collect($this->getJson('/api/authorization/permission-catalogue')->assertOk()->json('data.areas'));
    $module = $areas->flatMap(fn (array $area): array => $area['resources'])->firstWhere('resource', 'request-statistics');

    expect($module)->not->toBeNull()
        ->and($module['label_key'])->toBe('navigation.requestStatistics')
        ->and(collect($module['permissions'])->pluck('ability')->all())->toBe(['view']);
});
