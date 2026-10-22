<?php

use App\Authorization\OpportunitiesAuthorization;
use App\Models\Opportunity;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\QualificaCatalog\CommercialBackOfficeRoleCatalogue;
use Database\Seeders\QualificaCatalog\OperatorRoleCatalogue;
use Database\Seeders\QualificaCommercialBackOfficeSeeder;
use Database\Seeders\QualificaRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function commercialBackOfficeUser(): User
{
    $user = User::factory()->create();
    $user->assignRole(OperatorRoleCatalogue::BASE_ROLE, CommercialBackOfficeRoleCatalogue::ROLE);

    return $user;
}

it('0299 AC-001: grants the back office its modules in full and only viewAny on the support resources', function () {
    $this->seed(QualificaRoleSeeder::class);
    $role = Role::findByName(CommercialBackOfficeRoleCatalogue::ROLE);

    foreach (CommercialBackOfficeRoleCatalogue::MODULES as $resource) {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            expect($role->hasPermissionTo("{$resource}.{$ability}"))->toBeTrue("{$resource}.{$ability}");
        }
    }

    foreach (['work-orders.viewAll', 'work-orders.manageCosts', 'work-orders.managePayments', 'work-orders.viewInvoices',
        'work-orders.viewContractData', 'work-orders.sendEmail', 'opportunities.viewDocuments', 'registries.viewDocuments',
        'product-categories.viewAny', 'operational-sites.viewAny'] as $permission) {
        expect($role->hasPermissionTo($permission))->toBeTrue($permission);
    }

    foreach (['product-categories.view', 'operational-sites.view', 'operational-sites.update', 'referent-types.view',
        'companies.view', 'quote-workflows.view', 'contracts.view', 'request-management.view', 'tasks.viewAny'] as $permission) {
        expect($role->hasPermissionTo($permission))->toBeFalse($permission);
    }

    expect($role->fieldPermissions()->exists())->toBeFalse();
});

it('0299 AC-002: adds the role to the listed people on top of their own and skips a missing account', function () {
    $this->seed(QualificaRoleSeeder::class);
    [$missing] = CommercialBackOfficeRoleCatalogue::USERS;
    $people = collect(array_slice(CommercialBackOfficeRoleCatalogue::USERS, 1))->map(function (string $email): User {
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole(OperatorRoleCatalogue::BASE_ROLE);

        return $user;
    });

    $this->seed(QualificaCommercialBackOfficeSeeder::class);
    $this->seed(QualificaCommercialBackOfficeSeeder::class);

    expect(User::query()->where('email', $missing)->exists())->toBeFalse();

    foreach ($people as $user) {
        expect($user->fresh()->getRoleNames()->sort()->values()->all())
            ->toBe([CommercialBackOfficeRoleCatalogue::ROLE, OperatorRoleCatalogue::BASE_ROLE]);
    }
});

it('0299 AC-003: shows the five modules in the menu next to Task, Segnatempo and Assenze, nothing else', function () {
    $this->seed(QualificaRoleSeeder::class);

    expect(visibleRoutes(commercialBackOfficeUser()))->toEqualCanonicalizing([
        '/dashboard', '/opportunities', '/quotes', '/work-orders', '/tasks', '/time-entries', '/absences',
        '/registries', '/referents',
    ]);
});

it('0299 AC-003: opens the reads the opportunity and quote forms need beyond the pickers', function () {
    $this->seed(QualificaRoleSeeder::class);
    Sanctum::actingAs(commercialBackOfficeUser());

    $this->getJson('/api/product-categories/tree')->assertOk();

    foreach (['opportunities', 'quotes', 'work-orders', 'registries', 'referents'] as $resource) {
        $this->getJson("/api/meta/{$resource}")->assertOk();
    }

    $fields = app(OpportunitiesAuthorization::class)
        ->fieldPermissions(auth()->user(), Opportunity::factory()->create());

    expect($fields['operational_site_id']->editable)->toBeTrue();
});
