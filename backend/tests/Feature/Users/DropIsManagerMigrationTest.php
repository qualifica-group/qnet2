<?php

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function dropIsManagerMigration(): object
{
    return require database_path('migrations/2026_10_21_101000_drop_is_manager_from_employment_profiles_table.php');
}

it('0276 AC-001: employment_profiles has no is_manager, and down() recreates it with default false', function () {
    expect(Schema::hasColumn('employment_profiles', 'is_manager'))->toBeFalse();

    dropIsManagerMigration()->down();
    expect(Schema::hasColumn('employment_profiles', 'is_manager'))->toBeTrue();

    DB::table('users')->insert(['name' => 'U', 'email' => 'u@example.test', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('employment_profiles')->insert(['user_id' => DB::table('users')->value('id'), 'created_at' => now(), 'updated_at' => now()]);
    expect((bool) DB::table('employment_profiles')->value('is_manager'))->toBeFalse();

    dropIsManagerMigration()->up();
    expect(Schema::hasColumn('employment_profiles', 'is_manager'))->toBeFalse();
});

it('0276 D-1: up() deletes the employment.is_manager field-permission rows and nothing else', function () {
    $role = Role::create(['name' => 'drop-is-manager']);
    $role->fieldPermissions()->create(['resource' => 'users', 'field' => 'employment.is_manager', 'visible' => true, 'editable' => false, 'required' => false]);
    $role->fieldPermissions()->create(['resource' => 'users', 'field' => 'employment.hired_at', 'visible' => true, 'editable' => false, 'required' => false]);

    dropIsManagerMigration()->down();
    dropIsManagerMigration()->up();

    expect($role->fieldPermissions()->pluck('field')->all())->toBe(['employment.hired_at']);
});
