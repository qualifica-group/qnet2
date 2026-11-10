<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Spec 0365 D-1/D-2/D-3/D-11: the "Statistiche" menu section.
 */
uses(RefreshDatabase::class);

function statisticsMenuSection(string $key): ?array
{
    return collect(test()->getJson('/api/navigation')->assertOk()->json('data'))->firstWhere('key', $key);
}

function statisticsMenuUser(string ...$permissions): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission);
    }

    return User::factory()->create()->givePermissionTo($permissions);
}

// REQUIREMENT CHANGED (spec 0365, user directive 2026-11-06): Gestione Iscritti stays under Commerciale.
it('AC-001: groups the two statistics entries and keeps enrollee management in commercial', function () {
    Sanctum::actingAs(statisticsMenuUser(
        'agent-sales-report.view', 'request-statistics.view', 'enrollee-management.view', 'quotes.view',
    ));

    $statistics = statisticsMenuSection('statistics');
    $commercialKeys = collect(statisticsMenuSection('commercial')['children'])->pluck('key')->all();

    expect(collect($statistics['children'])->pluck('key')->all())
        ->toBe(['agent-sales-report', 'request-statistics'])
        ->and(collect($statistics['children'])->firstWhere('key', 'agent-sales-report'))
        ->toMatchArray(['route' => '/agent-sales-report', 'icon' => 'trending-up', 'label' => 'navigation.agentSalesReport'])
        ->and($commercialKeys)->toContain('quotes', 'enrollee-management')
        ->not->toContain('request-statistics');
});

it('AC-001: a holder of only agent-sales-report.view sees just that child', function () {
    Sanctum::actingAs(statisticsMenuUser('agent-sales-report.view'));

    expect(collect(statisticsMenuSection('statistics')['children'])->pluck('key')->all())->toBe(['agent-sales-report']);
});

it('AC-002: a user with neither statistics permission has no statistics section', function () {
    Sanctum::actingAs(statisticsMenuUser('quotes.view'));

    expect(statisticsMenuSection('statistics'))->toBeNull();
});

it('lists the permission in the Role form catalogue as a view-only module', function () {
    Artisan::call('permissions:sync');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Sanctum::actingAs(statisticsMenuUser('roles.viewAny'));

    $areas = collect($this->getJson('/api/authorization/permission-catalogue')->assertOk()->json('data.areas'));
    $module = $areas->flatMap(fn (array $area): array => $area['resources'])->firstWhere('resource', 'agent-sales-report');

    expect($module)->not->toBeNull()
        ->and(collect($module['permissions'])->pluck('ability')->all())->toBe(['view']);
});

it('D-3: the data migration creates the permission once on a populated catalogue and drops it on rollback', function () {
    DB::table('permissions')->where('name', 'agent-sales-report.view')->delete();
    Permission::findOrCreate('quotes.view');
    $migration = require database_path('migrations/2026_11_23_100100_create_agent_sales_report_view_permission.php');

    $migration->up();
    $migration->up();
    expect(DB::table('permissions')->where('name', 'agent-sales-report.view')->count())->toBe(1);

    $migration->down();
    expect(DB::table('permissions')->where('name', 'agent-sales-report.view')->count())->toBe(0);
});

it('D-3: the data migration does nothing on a database with no permissions yet', function () {
    DB::table('permissions')->delete();
    $migration = require database_path('migrations/2026_11_23_100100_create_agent_sales_report_view_permission.php');

    $migration->up();

    expect(DB::table('permissions')->count())->toBe(0);
});

it('D-11: the data migration adds the key to an existing sales workspace, idempotently', function () {
    $migration = require database_path('migrations/2026_11_23_100200_add_agent_sales_report_to_sales_workspace.php');

    $migration->up();
    expect(DB::table('workspace_navigation_items')->count())->toBe(0);

    $workspace = Workspace::query()->create(['slug' => 'sales', 'name' => 'Sales']);
    $migration->up();
    $migration->up();
    expect(DB::table('workspace_navigation_items')->where('workspace_id', $workspace->id)->where('navigation_key', 'agent-sales-report')->count())->toBe(1);

    $migration->down();
    expect(DB::table('workspace_navigation_items')->count())->toBe(0);
});
