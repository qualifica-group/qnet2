<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Opportunities\OpportunityVisibilityScope;
use App\Services\Quotes\QuoteVisibilityScope;
use App\Services\WorkOrders\WorkOrderVisibilityScope;
use Database\Seeders\QualificaCatalog\OperatorRoleCatalogue;
use Database\Seeders\QualificaCatalog\SeniorSalesRoleCatalogue;
use Database\Seeders\QualificaRoleResetSeeder;
use Database\Seeders\QualificaRoleSeeder;
use Database\Seeders\QualificaSeniorSalesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('0344 AC-001: grants the senior sales role its modules in full and only viewAny on the support resources', function () {
    $this->seed(QualificaRoleSeeder::class);
    $role = Role::findByName(SeniorSalesRoleCatalogue::ROLE);

    foreach (SeniorSalesRoleCatalogue::MODULES as $resource) {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            expect($role->hasPermissionTo("{$resource}.{$ability}"))->toBeTrue("{$resource}.{$ability}");
        }
    }

    foreach (['opportunities.viewAll', 'quotes.viewAll', 'work-orders.viewAll', 'work-orders.manageCosts',
        'work-orders.managePayments', 'work-orders.viewInvoices', 'work-orders.viewContractData', 'work-orders.sendEmail',
        'registries.viewDocuments', 'product-categories.viewAny', 'operational-sites.viewAny'] as $permission) {
        expect($role->hasPermissionTo($permission))->toBeTrue($permission);
    }

    foreach (['contracts.viewAny', 'proforma-requests.viewAny', 'product-categories.view', 'operational-sites.view',
        'operational-sites.update', 'referent-types.view', 'companies.view', 'quote-workflows.view'] as $permission) {
        expect($role->hasPermissionTo($permission))->toBeFalse($permission);
    }

    expect($role->fieldPermissions()->get(['resource', 'field', 'visible'])->toArray())
        ->toBe([['resource' => 'opportunities', 'field' => 'lead_id', 'visible' => false]]);
});

it('0344 AC-002: adds the role on top of the mansione, to a deactivated account too, and skips a missing one', function () {
    $this->seed(QualificaRoleSeeder::class);
    [$missing, $inactive] = SeniorSalesRoleCatalogue::USERS;
    $people = collect(array_slice(SeniorSalesRoleCatalogue::USERS, 1))->map(function (string $email) use ($inactive): User {
        $user = User::factory()->create(['email' => $email, 'is_active' => $email !== $inactive]);
        $user->assignRole(OperatorRoleCatalogue::COMMERCIAL_ROLE);

        return $user;
    });

    $this->seed(QualificaSeniorSalesSeeder::class);
    $this->seed(QualificaSeniorSalesSeeder::class);

    expect(User::query()->where('email', $missing)->exists())->toBeFalse();

    foreach ($people as $user) {
        expect($user->fresh()->getRoleNames()->sort()->values()->all())
            ->toBe([OperatorRoleCatalogue::COMMERCIAL_ROLE, SeniorSalesRoleCatalogue::ROLE]);
    }
});

it('0344 AC-003: lets a commerciale see the opportunities, quotes and work orders of everyone only with the role', function () {
    $this->seed(QualificaRoleSeeder::class);
    $opportunity = Opportunity::factory()->create();
    $quote = Quote::factory()->create();
    $workOrder = WorkOrder::factory()->create();
    $sales = User::factory()->create();
    $sales->assignRole(OperatorRoleCatalogue::COMMERCIAL_ROLE);

    $sees = fn (User $user): array => [
        OpportunityVisibilityScope::scopeToActor(Opportunity::query(), $user)->whereKey($opportunity->id)->exists(),
        QuoteVisibilityScope::scopeToActor(Quote::query(), $user)->whereKey($quote->id)->exists(),
        WorkOrderVisibilityScope::scopeToActor(WorkOrder::query(), $user)->whereKey($workOrder->id)->exists(),
    ];

    expect($sees($sales))->toBe([false, false, false]);

    $sales->assignRole(SeniorSalesRoleCatalogue::ROLE);

    expect($sees($sales->fresh()))->toBe([true, true, true]);
});

it('0344 AC-004: rebuilds the role and reassigns it in the role reset', function () {
    $email = SeniorSalesRoleCatalogue::USERS[0];
    $user = User::factory()->create(['email' => $email, 'is_active' => true]);

    $this->seed(QualificaRoleResetSeeder::class);

    expect(Role::findByName(SeniorSalesRoleCatalogue::ROLE)->permissions)->not->toBeEmpty()
        ->and($user->fresh()->getRoleNames()->all())->toContain(SeniorSalesRoleCatalogue::ROLE);
});
