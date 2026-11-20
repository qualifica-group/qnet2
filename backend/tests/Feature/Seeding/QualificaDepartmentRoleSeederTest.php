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
        if ($department['standalone_senior'] ?? false) {
            continue;
        }

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
        // REQUIREMENT CHANGED (D-14): Annunziata is in the Commerciale questionnaire too.
        ->and(departmentRoleNames($safetyHead))->toBe([Department::COMMERCIAL, Department::SAFETY_HEAD, Department::senior(Department::SAFETY)])
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

// REQUIREMENT CHANGED (spec 0408 D-14): the Back Office answered its questionnaire, no more admin's matrix.
it('maps the back office questionnaire onto operators and supervisor', function () {
    $supervisor = User::factory()->create(['email' => 'martina.mosca@qualificagroup.com']);
    $supervisor->assignRole(['operatore-base', 'commerciale']);
    $head = User::factory()->create(['email' => 'michela.fabozzi@qualificagroup.com']);
    $head->assignRole(['commerciale']);

    $this->seed(Seeder::class);

    $operator = Role::findByName(Department::BACK_OFFICE);
    $iban = Role::findByName(Department::BACK_OFFICE)->fieldPermissions()
        ->where('resource', 'users')->where('field', 'employment.iban')->value('visible');

    expect($operator->hasPermissionTo('contracts.validate'))->toBeTrue()
        ->and($operator->hasPermissionTo('work-orders.viewAll'))->toBeTrue()
        ->and($operator->hasPermissionTo('commission-settlements.viewAny'))->toBeTrue()
        ->and($operator->hasPermissionTo('invoices.create'))->toBeFalse()
        ->and($operator->hasPermissionTo('commission-configurations.create'))->toBeTrue()
        ->and($operator->hasPermissionTo('commission-configurations.import'))->toBeFalse()
        ->and($operator->hasPermissionTo('quotes.viewCosts'))->toBeTrue()
        ->and($operator->hasPermissionTo('quotes.notifyTeam'))->toBeFalse()
        ->and($operator->hasPermissionTo('quotes.transferRegistry'))->toBeTrue()
        ->and(Role::findByName(Department::senior(Department::BACK_OFFICE))->hasPermissionTo('quotes.transferRegistry'))->toBeTrue()
        ->and(Role::findByName(Department::COMMERCIAL)->hasPermissionTo('quotes.transferRegistry'))->toBeFalse()
        ->and($operator->hasPermissionTo('leads.viewAny'))->toBeFalse()
        ->and($operator->hasPermissionTo('tasks.viewAll'))->toBeFalse()
        // Every quote, yet only the requests they are involved in: their answer, not the 2026-10-27 rule (D-14).
        ->and($operator->hasPermissionTo('request-management.viewAny'))->toBeTrue()
        ->and($operator->hasPermissionTo('request-management.viewAll'))->toBeFalse()
        ->and($operator->hasPermissionTo('request-management.viewCompetence'))->toBeFalse()
        ->and($operator->hasPermissionTo('enrollee-management.viewAny'))->toBeFalse()
        ->and($operator->hasPermissionTo('request-management.import'))->toBeFalse()
        ->and($operator->hasPermissionTo('users.impersonate'))->toBeFalse()
        ->and($iban)->toBeFalse()
        ->and(departmentRoleNames($supervisor))->toBe([Department::BACK_OFFICE_TEAM_NOTIFICATIONS, Department::senior(Department::BACK_OFFICE)])
        ->and($supervisor->hasPermissionTo('tasks.manageAll'))->toBeTrue()
        ->and($supervisor->hasPermissionTo('request-management.import'))->toBeTrue()
        ->and($supervisor->hasPermissionTo('request-management.viewAll'))->toBeTrue()
        ->and($supervisor->hasPermissionTo('enrollee-management.viewAll'))->toBeTrue()
        ->and($supervisor->hasPermissionTo('products.update'))->toBeTrue()
        ->and($supervisor->hasPermissionTo('treasury-movements.viewAny'))->toBeFalse()
        ->and($supervisor->hasPermissionTo('work-orders.notifyTeam'))->toBeTrue()
        ->and($supervisor->hasPermissionTo('contracts.notifyTeam'))->toBeTrue()
        ->and(departmentRoleNames($head))->toBe(['admin', Department::senior(Department::BACK_OFFICE), Department::senior(Department::COMMERCIAL)])
        // The team notifications are Mosca's note, not the level's (spec 0413 D-5).
        ->and(Role::findByName(Department::senior(Department::BACK_OFFICE))->hasPermissionTo('quotes.notifyTeam'))->toBeFalse();
});

it('gives the commerciale supervisors their own answers, not a widening of the commerciali', function () {
    foreach (['notifiche-richieste-modifica', 'notifiche-trasferimenti-contatti', 'supervisore-commerciale'] as $role) {
        Role::create(['name' => $role]);
    }
    $agent = User::factory()->create(['email' => 'antonio.alvoni@qualificagroup.com']);
    $agent->assignRole(['commerciale']);
    $supervisor = User::factory()->create(['email' => 'rosa.falzarano@qualificagroup.com']);
    $supervisor->assignRole(['supervisore-commerciale', 'notifiche-richieste-modifica', 'notifiche-trasferimenti-contatti']);
    $apl = User::factory()->create(['email' => 'desire.romito@qualificagroup.com']);

    $this->seed(Seeder::class);

    $commercial = Role::findByName(Department::COMMERCIAL);
    $supervisorRole = Role::findByName(Department::senior(Department::COMMERCIAL));

    expect($commercial->hasPermissionTo('opportunities.viewAny'))->toBeTrue()
        ->and($commercial->hasPermissionTo('opportunities.viewAll'))->toBeFalse()
        ->and($commercial->hasPermissionTo('opportunities.create'))->toBeFalse()
        ->and($commercial->hasPermissionTo('quotes.update'))->toBeFalse()
        ->and($commercial->hasPermissionTo('work-orders.viewCosts'))->toBeTrue()
        ->and($commercial->hasPermissionTo('request-management.update'))->toBeTrue()
        ->and($commercial->hasPermissionTo('field-change-requests.create'))->toBeTrue()
        ->and($commercial->hasPermissionTo('field-change-requests.manage'))->toBeFalse()
        ->and($supervisorRole->hasPermissionTo('field-change-requests.manage'))->toBeTrue()
        ->and($commercial->hasPermissionTo('commission-accruals.export'))->toBeTrue()
        ->and($commercial->hasPermissionTo('commission-settlements.export'))->toBeFalse()
        ->and($supervisorRole->hasPermissionTo('quotes.viewAny'))->toBeFalse()
        ->and($commercial->hasPermissionTo('quotes.viewCosts'))->toBeTrue()
        ->and(Role::findByName(Department::SAFETY)->hasPermissionTo('quotes.viewCosts'))->toBeFalse()
        ->and($supervisorRole->hasPermissionTo('work-orders.viewCosts'))->toBeFalse()
        ->and($supervisorRole->hasPermissionTo('leads.update'))->toBeTrue()
        ->and($supervisorRole->hasPermissionTo('absence-requests.calendarAll'))->toBeTrue()
        ->and(departmentRoleNames($agent))->toBe([Department::COMMERCIAL, Department::COMMERCIAL_FISCAL_PROFILES])
        ->and($agent->hasPermissionTo('agent-fiscal-profiles.viewAny'))->toBeTrue()
        ->and(departmentRoleNames($supervisor))->toBe(['notifiche-richieste-modifica', 'notifiche-trasferimenti-contatti', Department::senior(Department::COMMERCIAL)])
        ->and(departmentRoleNames($apl))->toBe([Department::APL, Department::COMMERCIAL]);
});

// Spec 0408 D-15: question 88 means working the requests, not reading them only.
it('lets whoever works the requests act on them and the "Tutte" level manage them all', function () {
    $this->seed(Seeder::class);

    $can = fn (string $role, string $permission): bool => Role::findByName($role)->hasPermissionTo($permission);
    $backOffice = Department::BACK_OFFICE;
    $backOfficeHead = Department::senior(Department::BACK_OFFICE);
    $commercialHead = Department::senior(Department::COMMERCIAL);

    foreach ([$backOffice, Department::COMMERCIAL, Department::APL] as $role) {
        foreach (['create', 'update', 'export', 'viewDocuments', 'viewActivity', 'appendTeamMember', 'assignManagerGa1', 'transferContact', 'transferRegistry'] as $ability) {
            expect($can($role, "request-management.{$ability}"))->toBeTrue("{$role} request-management.{$ability}");
        }
        expect($can($role, 'request-management.delete'))->toBeFalse()
            ->and($can($role, 'request-management.assignOperator'))->toBeFalse()
            ->and($can($role, 'request-management.updateSource'))->toBeFalse()
            ->and($can($role, 'sources.viewAny'))->toBeTrue()
            ->and($can($role, 'referents.create'))->toBeTrue();
    }

    foreach ([$backOfficeHead, $commercialHead] as $role) {
        foreach (['create', 'update', 'delete', 'assignOperator', 'updateSource', 'viewDocuments', 'viewActivity', 'viewAll'] as $ability) {
            expect($can($role, "request-management.{$ability}"))->toBeTrue("{$role} request-management.{$ability}");
        }
        foreach (['update', 'delete', 'export', 'viewDocuments', 'viewActivity', 'viewAll', 'assignOperator'] as $ability) {
            expect($can($role, "enrollee-management.{$ability}"))->toBeTrue("{$role} enrollee-management.{$ability}");
        }
        expect($can($role, 'business-functions.viewAny'))->toBeTrue();
    }

    // Read-only levels see the documents of what they see, and act on nothing.
    foreach ([Department::SAFETY, Department::ACCOUNTING, Department::TRAVEL] as $role) {
        expect($can($role, 'request-management.viewDocuments'))->toBeTrue("{$role} request-management.viewDocuments")
            ->and($can($role, 'request-management.create'))->toBeFalse()
            ->and($can($role, 'request-management.update'))->toBeFalse();
    }
    expect($can(Department::ACCOUNTING, 'enrollee-management.viewDocuments'))->toBeTrue()
        ->and($can(Department::ACCOUNTING, 'enrollee-management.update'))->toBeFalse()
        ->and($can($backOffice, 'enrollee-management.viewAny'))->toBeFalse();
});
