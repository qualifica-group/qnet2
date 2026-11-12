<?php

declare(strict_types=1);

use App\RequestManagement\RequestModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// Spec 0391 AC-011: the migration grants `request-statistics.update` to the roles
// that both see the statistics and manage the working statuses.

uses(RefreshDatabase::class);

function statisticsUpdateMigration(): object
{
    return require database_path('migrations/2026_12_08_100001_add_request_statistics_update_permission.php');
}

function statisticsRole(string $name, array $permissions): Role
{
    $role = Role::create(['name' => $name]);
    foreach ($permissions as $permission) {
        $role->givePermissionTo(Permission::findOrCreate($permission));
    }

    return $role;
}

it('AC-011: grants request-statistics.update only to roles holding both view and quote-workflows.update', function (): void {
    $both = statisticsRole('status-manager', [RequestModule::STATISTICS_PERMISSION, 'quote-workflows.update']);
    $viewOnly = statisticsRole('statistics-reader', [RequestModule::STATISTICS_PERMISSION]);
    $workflowsOnly = statisticsRole('workflow-editor', ['quote-workflows.update']);
    $neither = statisticsRole('other-role', ['tasks.view']);

    statisticsUpdateMigration()->up();

    expect($both->fresh()->hasPermissionTo(RequestModule::STATISTICS_CONFIGURE_PERMISSION))->toBeTrue()
        ->and($viewOnly->fresh()->hasPermissionTo(RequestModule::STATISTICS_CONFIGURE_PERMISSION))->toBeFalse()
        ->and($workflowsOnly->fresh()->hasPermissionTo(RequestModule::STATISTICS_CONFIGURE_PERMISSION))->toBeFalse()
        ->and($neither->fresh()->hasPermissionTo(RequestModule::STATISTICS_CONFIGURE_PERMISSION))->toBeFalse();
});

it('AC-011: creates the permission when missing and survives a second run without duplicates', function (): void {
    Permission::query()->where('name', RequestModule::STATISTICS_CONFIGURE_PERMISSION)->delete();
    $both = statisticsRole('status-manager', [RequestModule::STATISTICS_PERMISSION, 'quote-workflows.update']);

    statisticsUpdateMigration()->up();
    statisticsUpdateMigration()->up();

    expect(Permission::query()->where('name', RequestModule::STATISTICS_CONFIGURE_PERMISSION)->count())->toBe(1)
        ->and($both->fresh()->permissions()->where('name', RequestModule::STATISTICS_CONFIGURE_PERMISSION)->count())->toBe(1);
});

it('AC-011: creates nothing on an installation where no role holds both permissions', function (): void {
    Permission::query()->where('name', RequestModule::STATISTICS_CONFIGURE_PERMISSION)->delete();
    statisticsRole('statistics-reader', [RequestModule::STATISTICS_PERMISSION]);

    statisticsUpdateMigration()->up();

    expect(Permission::query()->where('name', RequestModule::STATISTICS_CONFIGURE_PERMISSION)->exists())->toBeFalse();
});
