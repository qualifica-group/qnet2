<?php

use App\Models\Role;
use App\Models\User;
use App\RequestManagement\RequestModule;
use Database\Seeders\QualificaCatalog\CommercialBackOfficeRoleCatalogue;
use Database\Seeders\QualificaCatalog\LegacyRoleCatalogue as Legacy;
use Database\Seeders\QualificaCatalog\LegacyRoleRoster;
use Database\Seeders\QualificaCatalog\OperatorRoleCatalogue;
use Database\Seeders\QualificaLegacyRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('maps every legacy role of the roster to known roles and gives every row at least one', function () {
    $known = [...array_keys(Legacy::ROLES), OperatorRoleCatalogue::ADMIN_ROLE];

    foreach (LegacyRoleRoster::USERS as $email => $legacyRoles) {
        $roles = [];

        foreach ($legacyRoles as $legacyRole) {
            expect(Legacy::LEGACY_ROLES)->toHaveKey($legacyRole);
            $roles = [...$roles, ...Legacy::LEGACY_ROLES[$legacyRole]];
        }

        expect($roles)->not->toBeEmpty($email)
            ->and(array_diff($roles, $known))->toBeEmpty($email);
    }
});

it('seeds every legacy-derived role with its matrix and the Lead row only', function () {
    $this->seed(QualificaLegacyRoleSeeder::class);

    foreach (array_keys(Legacy::ROLES) as $name) {
        $role = Role::findByName($name);

        expect($role->permissions)->not->toBeEmpty($name)
            ->and($role->fieldPermissions()->get(['resource', 'field', 'visible'])->toArray())
            ->toBe([['resource' => 'opportunities', 'field' => 'lead_id', 'visible' => false]]);
    }

    $operations = Role::findByName(Legacy::OPERATIONS_ROLE);
    expect($operations->hasPermissionTo('work-orders.viewAll'))->toBeTrue()
        ->and($operations->hasPermissionTo('contracts.program'))->toBeTrue()
        ->and($operations->hasPermissionTo('invoices.view'))->toBeFalse();

    // "limited" in the legacy: their own records only.
    $trainingOffice = Role::findByName(Legacy::TRAINING_OFFICE_ROLE);
    expect($trainingOffice->hasPermissionTo('opportunities.create'))->toBeTrue()
        ->and($trainingOffice->hasPermissionTo('opportunities.viewAll'))->toBeFalse()
        ->and($trainingOffice->hasPermissionTo('work-orders.viewSite'))->toBeFalse();

    expect(Role::findByName(Legacy::ACCOUNTING_ROLE)->hasPermissionTo('purchase-invoices.pay'))->toBeTrue()
        ->and(Role::findByName(Legacy::PURCHASING_ROLE)->hasPermissionTo('purchase-orders.approve'))->toBeFalse()
        ->and(Role::findByName(Legacy::PURCHASE_APPROVAL_ROLE)->hasPermissionTo('purchase-orders.approve'))->toBeTrue()
        ->and(Role::findByName(Legacy::TIME_ENTRY_SUPERVISOR_ROLE)->hasPermissionTo('time-entries.manageAll'))->toBeFalse();
});

it('lets only the user office write users, with the whole user form', function () {
    $this->seed(QualificaLegacyRoleSeeder::class);

    foreach (array_keys(Legacy::ROLES) as $name) {
        $writes = Role::findByName($name)->hasPermissionTo('users.update');

        expect($writes)->toBe($name === Legacy::USER_OFFICE_ROLE, $name);
    }

    $userOffice = Role::findByName(Legacy::USER_OFFICE_ROLE);
    expect($userOffice->hasPermissionTo('users.create'))->toBeTrue()
        ->and($userOffice->hasPermissionTo('users.delete'))->toBeFalse()
        ->and($userOffice->hasPermissionTo('personal_data.update'))->toBeTrue();
});

it('gives the base role and the derived roles to whoever holds only the base role or none', function () {
    $this->seed(QualificaLegacyRoleSeeder::class);
    $withBase = User::factory()->create(['email' => 'andrea.arena@qualificagroup.com', 'is_active' => true]);
    $withBase->assignRole(OperatorRoleCatalogue::BASE_ROLE);
    $withNone = User::factory()->create(['email' => 'luca.ponticelli@qualificagroup.com', 'is_active' => true]);

    $this->seed(QualificaLegacyRoleSeeder::class);

    expect($withBase->fresh()->getRoleNames()->sort()->values()->all())
        ->toBe([Legacy::OPERATIONS_ROLE, OperatorRoleCatalogue::BASE_ROLE])
        ->and($withNone->fresh()->getRoleNames()->sort()->values()->all())
        ->toBe([
            Legacy::PURCHASE_APPROVAL_ROLE, Legacy::ACCOUNTING_ROLE, Legacy::OPERATIONS_ROLE,
            OperatorRoleCatalogue::BASE_ROLE, Legacy::TIME_ENTRY_SUPERVISOR_ROLE, Legacy::PURCHASING_ROLE,
        ]);
});

it('turns the legacy Admin into the admin role', function () {
    $user = User::factory()->create(['email' => 'nicolo.caruso@qualificagroup.com', 'is_active' => true]);

    $this->seed(QualificaLegacyRoleSeeder::class);

    expect($user->fresh()->hasRole(OperatorRoleCatalogue::ADMIN_ROLE))->toBeTrue();
});

it('leaves alone whoever holds a role of their own, and skips an inactive account', function () {
    $this->seed(QualificaLegacyRoleSeeder::class);
    $owner = User::factory()->create(['email' => 'andrea.arena@qualificagroup.com', 'is_active' => true]);
    $owner->assignRole(OperatorRoleCatalogue::BASE_ROLE, OperatorRoleCatalogue::COMMERCIAL_ROLE);
    $inactive = User::factory()->create(['email' => 'maria.bellini@qualificagroup.com', 'is_active' => false]);

    $this->seed(QualificaLegacyRoleSeeder::class);

    expect($owner->fresh()->getRoleNames()->sort()->values()->all())
        ->toBe([OperatorRoleCatalogue::COMMERCIAL_ROLE, OperatorRoleCatalogue::BASE_ROLE])
        ->and($inactive->fresh()->roles)->toBeEmpty();
});

it('converges on the roster when re-run', function () {
    $user = User::factory()->create(['email' => 'andrea.arena@qualificagroup.com', 'is_active' => true]);
    $this->seed(QualificaLegacyRoleSeeder::class);
    $user->assignRole(Legacy::SOCIAL_MEDIA_ROLE);

    $this->seed(QualificaLegacyRoleSeeder::class);

    expect($user->fresh()->getRoleNames()->sort()->values()->all())
        ->toBe([Legacy::OPERATIONS_ROLE, OperatorRoleCatalogue::BASE_ROLE]);
});

it('opens the request modules read-only to whoever sees opportunities and quotes, as wide as their quotes', function () {
    $this->seed(QualificaLegacyRoleSeeder::class);

    $expected = [
        Legacy::OPERATIONS_ROLE => 'viewAll',
        Legacy::ACCOUNTING_ROLE => 'viewAll',
        CommercialBackOfficeRoleCatalogue::ROLE => 'viewAll',
        Legacy::TRAINING_OFFICE_ROLE => null,
        Legacy::TECHNICIAN_ROLE => null,
    ];

    foreach ($expected as $name => $widening) {
        $role = Role::findByName($name);

        foreach (RequestModule::cases() as $module) {
            expect($role->hasPermissionTo($module->permission('viewAny')))->toBeTrue("{$name} {$module->value}")
                ->and($role->hasPermissionTo($module->permission('view')))->toBeTrue("{$name} {$module->value}")
                ->and($role->hasPermissionTo($module->permission('viewAll')))->toBe($widening === 'viewAll', "{$name} {$module->value}")
                ->and($role->hasPermissionTo($module->permission('update')))->toBeFalse("{$name} {$module->value}");
        }
    }

    // Neither opportunities nor quotes: nothing opened.
    expect(Role::findByName(Legacy::PURCHASING_ROLE)->hasPermissionTo('request-management.view'))->toBeFalse()
        ->and(Role::findByName(OperatorRoleCatalogue::MARKETING_ROLE)->hasPermissionTo('enrollee-management.view'))->toBeFalse();
});
