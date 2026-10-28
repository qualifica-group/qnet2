<?php

use App\Models\EmploymentProductLine;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleAssignmentGuard;
use Database\Seeders\QualificaCatalog\LegacyRoleCatalogue;
use Database\Seeders\QualificaCatalog\LegacyRoleRoster;
use Database\Seeders\QualificaCatalog\OperatorRoleCatalogue;
use Database\Seeders\QualificaCatalog\OperatorRoster;
use Database\Seeders\QualificaCatalog\StaffRoster;
use Database\Seeders\QualificaCatalog\StockKeeperRoleCatalogue;
use Database\Seeders\QualificaRoleResetSeeder;
use Database\Seeders\QualificaRoleSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('wipes every role, permission and field row, then rebuilds the production catalogue', function () {
    $this->seed(QualificaRoleSeeder::class);
    $stale = Role::create(['name' => 'stale-role']);
    $stale->fieldPermissions()->create(['resource' => 'users', 'field' => 'email', 'visible' => false, 'editable' => false, 'required' => false]);
    $stalePermission = Permission::create(['name' => 'stale.permission']);
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole($stale);
    $user->givePermissionTo($stalePermission);
    $staleScope = EmploymentProductLine::factory()->create();

    $this->seed(QualificaRoleResetSeeder::class);

    expect(Role::query()->where('name', 'stale-role')->exists())->toBeFalse()
        ->and(EmploymentProductLine::query()->whereKey($staleScope->id)->exists())->toBeFalse()
        ->and(Permission::query()->where('name', 'stale.permission')->exists())->toBeFalse()
        ->and(DB::table('role_field_permissions')->where('resource', 'users')->where('field', 'email')->where('visible', false)->exists())->toBeFalse()
        ->and($user->fresh()->roles)->toBeEmpty()
        ->and($user->fresh()->permissions)->toBeEmpty()
        ->and(Role::findByName(RoleAssignmentGuard::PRIVILEGED_ROLE)->permissions)->not->toBeEmpty()
        ->and(Role::findByName(OperatorRoleCatalogue::ADMIN_ROLE)->permissions)->not->toBeEmpty();

    foreach (array_keys(LegacyRoleCatalogue::ROLES) as $name) {
        expect(Role::findByName($name)->permissions)->not->toBeEmpty($name);
    }
});

it('reassigns the roles of every roster to the existing accounts, without touching their password', function () {
    [$privileged] = TestUsersSeeder::TEST_USERS;
    [, , $operatorEmail, , $operatorRole] = OperatorRoster::OPERATORS[0];
    [, , $staffEmail] = collect(StaffRoster::USERS)
        ->first(fn (array $row): bool => ! array_key_exists($row[2], LegacyRoleRoster::USERS)
            && ! in_array($row[2], StockKeeperRoleCatalogue::USERS, true));
    $legacyEmail = array_key_first(LegacyRoleRoster::USERS);
    $keeperEmail = StockKeeperRoleCatalogue::USERS[0];

    $accounts = collect([$privileged['email'], $operatorEmail, $staffEmail, $legacyEmail, $keeperEmail, 'nobody@example.com'])
        ->unique()
        ->mapWithKeys(fn (string $email): array => [$email => User::factory()->create(['email' => $email, 'is_active' => true])]);
    $passwordBefore = $accounts[$operatorEmail]->password;

    $this->seed(QualificaRoleResetSeeder::class);

    $roleNames = fn (string $email): array => $accounts[$email]->fresh()->getRoleNames()->all();

    expect($roleNames($privileged['email']))->toBe([$privileged['role']])
        ->and($roleNames($operatorEmail))->toContain($operatorRole)
        ->and($roleNames($staffEmail))->toBe([OperatorRoleCatalogue::BASE_ROLE])
        ->and($roleNames($legacyEmail))->toContain(OperatorRoleCatalogue::BASE_ROLE)
        ->and(count($roleNames($legacyEmail)))->toBeGreaterThan(1)
        ->and($roleNames($keeperEmail))->toContain(StockKeeperRoleCatalogue::ROLE)
        ->and($roleNames('nobody@example.com'))->toBeEmpty()
        ->and($accounts[$operatorEmail]->fresh()->password)->toBe($passwordBefore)
        ->and(User::query()->whereIn('email', [$privileged['email'], $operatorEmail])->count())->toBe(2);
});

it('converges on a re-run', function () {
    [, , $operatorEmail, , $operatorRole] = OperatorRoster::OPERATORS[0];
    $operator = User::factory()->create(['email' => $operatorEmail, 'is_active' => true]);

    $this->seed(QualificaRoleResetSeeder::class);
    $roles = Role::query()->count();
    $permissions = Permission::query()->count();
    $this->seed(QualificaRoleResetSeeder::class);

    expect(Role::query()->count())->toBe($roles)
        ->and(Permission::query()->count())->toBe($permissions)
        ->and($operator->fresh()->getRoleNames()->all())->toContain($operatorRole);
});
