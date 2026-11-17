<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\QualificaCatalog\DepartmentRoleCatalogue as Department;
use Database\Seeders\QualificaCatalog\DepartmentRoster;
use Database\Seeders\QualificaDepartmentRoleSeeder as Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/** Spec 0408: department roles from the questionnaires, listed people moved onto them, the others untouched. */
uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['operatore-base', 'tecnico-consulenza-senza-costi', 'coordinamento-operativo', 'admin', 'commerciale'] as $role) {
        Role::create(['name' => $role]);
    }
});

/** @return array<int, string> */
function departmentRoleNames(User $user): array
{
    return $user->refresh()->getRoleNames()->sort()->values()->all();
}

it('creates a base and a senior role per department, identical but for Finanza Agevolata', function () {
    $this->seed(Seeder::class);

    foreach (array_keys(Department::DEPARTMENTS) as $name) {
        $base = Role::findByName($name)->permissions->pluck('name')->sort()->values();
        $senior = Role::findByName(Department::senior($name))->permissions->pluck('name')->sort()->values();

        $name === Department::SUBSIDISED_FINANCE
            ? expect($senior->diff($base))->not->toBeEmpty()->and($base->diff($senior))->toBeEmpty()
            : expect($senior->all())->toBe($base->all());
    }

    $iso = Role::findByName(Department::ISO);
    $finance = Role::findByName(Department::SUBSIDISED_FINANCE);

    expect(Role::query()->where('name', 'like', Department::ROLE_PREFIX.'%')->count())->toBe(count(Department::DEPARTMENTS) * 2)
        ->and($iso->hasPermissionTo('work-orders.view'))->toBeTrue()
        ->and($iso->hasPermissionTo('opportunities.viewAny'))->toBeFalse()
        ->and($iso->hasPermissionTo('tasks.viewAny'))->toBeTrue()
        ->and($finance->hasPermissionTo('work-orders.viewCompetence'))->toBeTrue()
        ->and($finance->hasPermissionTo('opportunities.viewAny'))->toBeFalse()
        ->and(Role::findByName(Department::senior(Department::SUBSIDISED_FINANCE))->hasPermissionTo('opportunities.viewCompetence'))->toBeTrue();
});

it('shows the contractual user data to the HR department only', function () {
    $this->seed(Seeder::class);

    $hidden = fn (string $role): bool => Role::findByName($role)->fieldPermissions()
        ->where('resource', 'users')->where('field', 'employment.iban')->value('visible') === false;

    expect($hidden(Department::HUMAN_RESOURCES))->toBeFalse()
        ->and(Role::findByName(Department::HUMAN_RESOURCES)->hasPermissionTo('users.manageCompensation'))->toBeTrue()
        ->and(Role::findByName(Department::HUMAN_RESOURCES)->hasPermissionTo('users.impersonate'))->toBeFalse()
        ->and($hidden(Department::ISO))->toBeTrue();
});

it('moves the listed people onto their department roles, idempotently, and leaves the others alone', function () {
    $technician = User::factory()->create(['email' => 'francesco.ienco@qualificagroup.com']);
    $technician->assignRole(['operatore-base', 'tecnico-consulenza-senza-costi']);
    $planner = User::factory()->create(['email' => 'annarita.palumbo@qualificagroup.com']);
    $planner->assignRole(['operatore-base', 'admin', 'coordinamento-operativo']);
    $head = User::factory()->create(['email' => 'francesca.russodelprete@qualificagroup.com']);
    $head->assignRole(['operatore-base', 'admin']);
    $safetyHead = User::factory()->create(['email' => 'fernando.annunziata@qualificagroup.com']);
    $safetyHead->assignRole(['commerciale']);
    $twoDepartments = User::factory()->create(['email' => 'giuseppe.agus@qualificagroup.com']);
    $twoDepartments->assignRole(['operatore-base']);
    $unlisted = User::factory()->create(['email' => 'not.listed@qualificagroup.com']);
    $unlisted->assignRole(['operatore-base', 'coordinamento-operativo']);

    $this->seed(Seeder::class);
    $this->seed(Seeder::class);

    expect(departmentRoleNames($technician))->toBe([Department::ISO])
        ->and($technician->getDirectPermissions())->toBeEmpty()
        ->and(departmentRoleNames($planner))->toBe([Department::ISO])
        ->and($planner->hasDirectPermission('work-orders.viewCompetence'))->toBeTrue()
        ->and($planner->hasPermissionTo('invoices.create'))->toBeFalse()
        ->and(departmentRoleNames($head))->toBe(['admin', Department::senior(Department::ISO)])
        ->and(departmentRoleNames($safetyHead))->toBe(['commerciale', Department::senior(Department::SAFETY)])
        ->and($safetyHead->hasDirectPermission('commission-settlements.viewAny'))->toBeTrue()
        ->and(departmentRoleNames($twoDepartments))->toBe([Department::ACCREDITATION, Department::SUBSIDISED_FINANCE])
        ->and(departmentRoleNames($unlisted))->toBe(['coordinamento-operativo', 'operatore-base']);
});

it('assigns only department roles from the roster', function () {
    $roles = collect(DepartmentRoster::USERS)->flatMap(fn (array $entry): array => $entry['roles']);
    $known = collect(array_keys(Department::DEPARTMENTS))->flatMap(fn (string $role): array => [$role, Department::senior($role)]);

    expect($roles->diff($known))->toBeEmpty();
});
