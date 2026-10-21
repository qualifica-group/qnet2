<?php

use App\Authorization\UsersAuthorization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\QualificaCatalog\HrOfficeRoleCatalogue;
use Database\Seeders\QualificaCatalog\OperatorRoleCatalogue;
use Database\Seeders\QualificaCatalog\OrgChartFunctions;
use Database\Seeders\QualificaHrOfficeSeeder;
use Database\Seeders\QualificaRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return list<string>
 */
function hrOfficeEmails(): array
{
    $function = OrgChartFunctions::FUNCTIONS[HrOfficeRoleCatalogue::FUNCTION];

    return [...$function['heads'], ...$function['members']];
}

/**
 * @return array<string, array{visible: bool, editable: bool}>
 */
function sensitiveRowsOf(string $roleName): array
{
    return Role::findByName($roleName)->fieldPermissions()
        ->where('resource', 'users')
        ->whereIn('field', HrOfficeRoleCatalogue::SENSITIVE_FIELDS['users'])
        ->get()
        ->mapWithKeys(fn ($row): array => [$row->field => ['visible' => (bool) $row->visible, 'editable' => (bool) $row->editable]])
        ->all();
}

it('0283 AC-001: grants the HR office its modules, delete included, but not impersonation', function () {
    $this->seed(QualificaRoleSeeder::class);
    $role = Role::findByName(HrOfficeRoleCatalogue::ROLE);

    foreach (['users', 'job-roles', 'job-activities', 'business-functions', 'absence-types'] as $resource) {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            expect($role->hasPermissionTo("{$resource}.{$ability}"))->toBeTrue("{$resource}.{$ability}");
        }
    }

    foreach (['org-chart.view', 'absence-requests.manageAll', 'users.manageCompensation', 'time-entries.viewAll',
        'time-entries.exportMonthly', 'companies.viewAny', 'companies.view', 'personal_data.update', 'tasks.viewAny'] as $permission) {
        expect($role->hasPermissionTo($permission))->toBeTrue($permission);
    }

    foreach (['users.impersonate', 'companies.update', 'time-entries.manageAll', 'tasks.viewAll'] as $permission) {
        expect($role->hasPermissionTo($permission))->toBeFalse($permission);
    }
});

it('0283 AC-011: grants the HR office the training and skills modules too', function () {
    $this->seed(QualificaRoleSeeder::class);
    $role = Role::findByName(HrOfficeRoleCatalogue::ROLE);

    foreach (['staff-courses', 'skills'] as $resource) {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            expect($role->hasPermissionTo("{$resource}.{$ability}"))->toBeTrue("{$resource}.{$ability}");
        }
    }

    foreach (['staff-trainings', 'user-skills'] as $resource) {
        foreach (['viewAny', 'viewTeam', 'viewAll', 'manage'] as $ability) {
            expect($role->hasPermissionTo("{$resource}.{$ability}"))->toBeTrue("{$resource}.{$ability}");
        }
    }
});

it('0283 AC-002: hides the contractual data from every mansione and grants it to the HR office and admin', function () {
    $this->seed(QualificaRoleSeeder::class);
    $this->seed(QualificaRoleSeeder::class);

    $hidden = array_fill_keys(HrOfficeRoleCatalogue::SENSITIVE_FIELDS['users'], ['visible' => false, 'editable' => false]);
    $granted = array_fill_keys(HrOfficeRoleCatalogue::SENSITIVE_FIELDS['users'], ['visible' => true, 'editable' => true]);

    foreach (array_keys(OperatorRoleCatalogue::ROLES) as $mansione) {
        expect(sensitiveRowsOf($mansione))->toEqualCanonicalizing($hidden);
    }

    expect(sensitiveRowsOf(HrOfficeRoleCatalogue::ROLE))->toEqualCanonicalizing($granted)
        ->and(sensitiveRowsOf(OperatorRoleCatalogue::ADMIN_ROLE))->toEqualCanonicalizing($granted)
        ->and(Role::findByName(HrOfficeRoleCatalogue::ROLE)->fieldPermissions()->where('field', 'roles')->first())
        ->visible->toBeTrue()->editable->toBeFalse()
        // The request-scoped restrictions survive next to the new rows.
        ->and(Role::findByName(OperatorRoleCatalogue::COMMERCIAL_ROLE)->fieldPermissions()->where('field', 'operational_site_id')->exists())
        ->toBeTrue();
});

it('0283 AC-003/AC-004: adds the HR role to the office on top of its own, and the union opens the contractual data', function () {
    $this->seed(QualificaRoleSeeder::class);
    [$head, $member, $missing] = hrOfficeEmails();
    $people = collect([$head, $member])->map(function (string $email): User {
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole(OperatorRoleCatalogue::BASE_ROLE);

        return $user;
    });

    $this->seed(QualificaHrOfficeSeeder::class);
    $this->seed(QualificaHrOfficeSeeder::class);

    expect(User::query()->where('email', $missing)->exists())->toBeFalse();

    foreach ($people as $user) {
        $user = $user->fresh();
        $fields = app(UsersAuthorization::class)->fieldPermissions($user, User::factory()->create());

        expect($user->getRoleNames()->sort()->values()->all())->toBe([OperatorRoleCatalogue::BASE_ROLE, HrOfficeRoleCatalogue::ROLE])
            ->and($fields['employment.contracts']->editable)->toBeTrue()
            ->and($fields['employment.iban']->visible)->toBeTrue()
            ->and($fields['roles']->editable)->toBeFalse();
    }

    $supervisor = User::factory()->create();
    $supervisor->assignRole(OperatorRoleCatalogue::SUPERVISOR_ROLE);
    $fields = app(UsersAuthorization::class)->fieldPermissions($supervisor, null);

    foreach (HrOfficeRoleCatalogue::SENSITIVE_FIELDS['users'] as $field) {
        expect($fields[$field]->visible)->toBeFalse($field);
    }
});
