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
    foreach (['operatore-base', 'tecnico-consulenza-senza-costi', 'coordinamento-operativo', 'admin', 'commerciale', 'contabilita', 'super-admin'] as $role) {
        Role::create(['name' => $role]);
    }
});

/** @return array<int, string> */
function departmentRoleNames(User $user): array
{
    return $user->refresh()->getRoleNames()->sort()->values()->all();
}

it('creates a base and a senior role per department, the senior one widened only where the questionnaire says so', function () {
    $this->seed(Seeder::class);

    foreach (Department::DEPARTMENTS as $name => $department) {
        $base = Role::findByName($name)->permissions->pluck('name')->sort()->values();
        $senior = Role::findByName(Department::senior($name))->permissions->pluck('name')->sort()->values();

        $department['senior_grants'] !== []
            ? expect($senior->diff($base))->not->toBeEmpty()->and($base->diff($senior))->toBeEmpty()
            : expect($senior->all())->toBe($base->all());
    }

    $iso = Role::findByName(Department::ISO);
    $finance = Role::findByName(Department::SUBSIDISED_FINANCE);

    expect(Role::query()->where('name', 'like', Department::ROLE_PREFIX.'%')->count())->toBe(count(Department::DEPARTMENTS) * 2 + count(Department::ADD_ON_ROLES))
        ->and($iso->hasPermissionTo('work-orders.view'))->toBeTrue()
        ->and($iso->hasPermissionTo('opportunities.viewAny'))->toBeFalse()
        ->and($iso->hasPermissionTo('tasks.viewAny'))->toBeTrue()
        ->and($finance->hasPermissionTo('work-orders.viewCompetence'))->toBeTrue()
        ->and($finance->hasPermissionTo('opportunities.viewAny'))->toBeFalse()
        ->and(Role::findByName(Department::senior(Department::SUBSIDISED_FINANCE))->hasPermissionTo('opportunities.viewCompetence'))->toBeTrue()
        ->and(Role::findByName(Department::senior(Department::ISO))->hasPermissionTo('contracts.validate'))->toBeTrue()
        ->and(Role::findByName(Department::PLANNING)->hasPermissionTo('contracts.validate'))->toBeTrue()
        ->and(Role::findByName(Department::PLANNING)->hasPermissionTo('work-orders.viewDocuments'))->toBeTrue();
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

it('leaves the listed people only the roles of the questionnaires, idempotently, and the others alone', function () {
    $technician = User::factory()->create(['email' => 'francesco.ienco@qualificagroup.com']);
    $technician->assignRole(['operatore-base', 'tecnico-consulenza-senza-costi']);
    $planner = User::factory()->create(['email' => 'annarita.palumbo@qualificagroup.com']);
    $planner->assignRole(['operatore-base', 'admin', 'coordinamento-operativo']);
    $head = User::factory()->create(['email' => 'francesca.russodelprete@qualificagroup.com']);
    $head->assignRole(['operatore-base', 'admin']);
    $safetyHead = User::factory()->create(['email' => 'fernando.annunziata@qualificagroup.com']);
    $safetyHead->assignRole(['commerciale']);
    $severalDepartments = User::factory()->create(['email' => 'giuseppe.agus@qualificagroup.com']);
    $severalDepartments->assignRole(['operatore-base', 'contabilita']);
    $superAdmin = User::factory()->create(['email' => 'pina.derosa@qualificagroup.com']);
    $superAdmin->assignRole(['super-admin', 'commerciale']);
    $unlisted = User::factory()->create(['email' => 'not.listed@qualificagroup.com']);
    $unlisted->assignRole(['operatore-base', 'coordinamento-operativo']);

    $this->seed(Seeder::class);
    $this->seed(Seeder::class);

    expect(departmentRoleNames($technician))->toBe([Department::ISO])
        ->and($technician->getDirectPermissions())->toBeEmpty()
        ->and(departmentRoleNames($planner))->toBe([Department::ISO, Department::PLANNING])
        ->and($planner->getDirectPermissions())->toBeEmpty()
        ->and($planner->hasPermissionTo('work-orders.viewCompetence'))->toBeTrue()
        ->and($planner->hasPermissionTo('invoices.create'))->toBeFalse()
        ->and(departmentRoleNames($head))->toBe(['admin', Department::senior(Department::ISO)])
        ->and($head->getDirectPermissions())->toBeEmpty()
        ->and($head->hasPermissionTo('contracts.validate'))->toBeTrue()
        ->and(departmentRoleNames($safetyHead))->toBe([Department::SAFETY_HEAD, Department::senior(Department::SAFETY)])
        ->and($safetyHead->getDirectPermissions())->toBeEmpty()
        ->and($safetyHead->hasPermissionTo('commission-settlements.viewAny'))->toBeTrue()
        ->and(departmentRoleNames($severalDepartments))->toBe([Department::ACCREDITATION, Department::SUBSIDISED_FINANCE, Department::PROJECT_DESIGN])
        ->and(departmentRoleNames($superAdmin))->toBe([Department::TENDERS, 'super-admin'])
        ->and(departmentRoleNames($unlisted))->toBe(['coordinamento-operativo', 'operatore-base'])
        ->and(Role::query()->whereIn('name', ['commerciale', 'contabilita', 'coordinamento-operativo'])->count())->toBe(3);
});

it('assigns only department roles from the roster', function () {
    $roles = collect(DepartmentRoster::USERS)->flatMap(fn (array $entry): array => $entry['roles']);
    $known = collect(array_keys(Department::DEPARTMENTS))->flatMap(fn (string $role): array => [$role, Department::senior($role)])
        ->merge(array_keys(Department::ADD_ON_ROLES));

    expect($roles->diff($known))->toBeEmpty();
});

it('gives the add-on role only what the questionnaire grants to that one person', function () {
    $this->seed(Seeder::class);

    expect(Role::findByName(Department::SAFETY_HEAD)->permissions->pluck('name')->sort()->values()->all())->toBe([
        'commission-accruals.export', 'commission-accruals.view', 'commission-accruals.viewAny',
        'commission-settlements.export', 'commission-settlements.view', 'commission-settlements.viewAny',
        'work-orders.export', 'work-orders.import',
    ])->and(Role::findByName(Department::senior(Department::SAFETY))->hasPermissionTo('commission-settlements.viewAny'))->toBeFalse();
});

it('maps the APL and accounting answers onto the visibility tiers', function () {
    $this->seed(Seeder::class);

    $apl = Role::findByName(Department::APL);
    $aplHead = Role::findByName(Department::senior(Department::APL));
    $accounting = Role::findByName(Department::ACCOUNTING);
    $coordinator = Role::findByName(Department::senior(Department::ACCOUNTING));

    expect($apl->hasPermissionTo('opportunities.viewAll'))->toBeTrue()
        ->and($apl->hasPermissionTo('work-orders.viewSite'))->toBeTrue()
        ->and($apl->hasPermissionTo('work-orders.viewAll'))->toBeFalse()
        ->and($apl->hasPermissionTo('tasks.viewSite'))->toBeTrue()
        ->and($apl->hasPermissionTo('contracts.viewAny'))->toBeFalse()
        ->and($aplHead->hasPermissionTo('work-orders.viewAll'))->toBeTrue()
        ->and($aplHead->hasPermissionTo('contracts.viewAll'))->toBeTrue()
        ->and($accounting->hasPermissionTo('work-orders.viewAll'))->toBeTrue()
        ->and($accounting->hasPermissionTo('work-orders.update'))->toBeFalse()
        ->and($accounting->hasPermissionTo('treasury-movements.reconcile'))->toBeTrue()
        ->and($accounting->hasPermissionTo('commission-settlements.approve'))->toBeFalse()
        ->and($accounting->hasPermissionTo('time-entries.viewAll'))->toBeFalse()
        ->and($coordinator->hasPermissionTo('time-entries.viewAll'))->toBeTrue();
});
