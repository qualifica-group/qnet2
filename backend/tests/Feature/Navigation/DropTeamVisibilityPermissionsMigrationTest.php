<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

const DROPPED_TEAM_PERMISSIONS = [
    'tasks.viewTeam',
    'absence-requests.calendarTeam',
    'staff-trainings.viewTeam',
    'user-skills.viewTeam',
];

it('0309 AC-002: the migration deletes the four permissions with their role grants and permissions:sync does not recreate them', function () {
    $role = Role::create(['name' => 'legacy-role']);
    foreach (DROPPED_TEAM_PERMISSIONS as $name) {
        $role->givePermissionTo(Permission::create(['name' => $name]));
    }
    Permission::create(['name' => 'tasks.view']);
    $user = User::factory()->create();
    $user->givePermissionTo('tasks.view');

    (require database_path('migrations/2026_10_23_100000_drop_team_visibility_permissions.php'))->up();

    expect(Permission::query()->whereIn('name', DROPPED_TEAM_PERMISSIONS)->count())->toBe(0)
        ->and($role->fresh()->permissions()->count())->toBe(0)
        ->and($user->fresh()->can('tasks.view'))->toBeTrue();

    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::query()->whereIn('name', DROPPED_TEAM_PERMISSIONS)->count())->toBe(0);
});
