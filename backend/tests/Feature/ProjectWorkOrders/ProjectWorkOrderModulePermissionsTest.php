<?php

use App\Enums\AssignmentTargetEnum;
use App\Enums\WorkOrderType;
use App\Models\User;
use App\Services\WorkOrders\WorkOrderNotable;
use App\Support\Notifications\RecordLinkResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

// "Commesse" and "Commesse di Progetto" are two modules with their own viewing
// permissions and their own page links (user decision 2026-11-25, WorkOrderTypeAccess).

uses(RefreshDatabase::class);

/** A user holding exactly these permissions, created on the fly. */
function moduleUser(string ...$permissions): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return $user;
}

/** @return array<int, int> */
function gridIds(string $domain, array $payload = []): array
{
    return array_column(test()->postJson("/api/tables/{$domain}/rows", ['startRow' => 0, 'endRow' => 50, ...$payload])->assertOk()->json('items'), 'id');
}

it('opens the project grid with project-work-orders.viewAny only, never with work-orders.viewAny', function () {
    $project = typedWorkOrder(WorkOrderType::Project);
    typedWorkOrder(WorkOrderType::Processing);

    Sanctum::actingAs(moduleUser('work-orders.viewAny', 'work-orders.view', 'work-orders.viewAll'));
    $this->getJson('/api/tables/project-work-orders/columns')->assertForbidden();

    Sanctum::actingAs(moduleUser('project-work-orders.viewAny', 'project-work-orders.view', 'work-orders.viewAll'));
    $this->getJson('/api/tables/project-work-orders/columns')->assertOk();
    expect(gridIds('project-work-orders'))->toBe([$project->id]);
});

it('lists in the work-orders grid only the types whose module the actor may list, Contratto tab included', function () {
    $processing = typedWorkOrder(WorkOrderType::Processing);
    $project = typedWorkOrder(WorkOrderType::Project, ['quote_id' => $processing->quote_id]);

    Sanctum::actingAs(moduleUser('work-orders.viewAny', 'work-orders.view', 'work-orders.viewAll'));
    expect(gridIds('work-orders'))->toBe([$processing->id])
        ->and(gridIds('work-orders', ['work_order_type' => 'project']))->toBe([])
        ->and(gridIds('work-orders', ['quoteId' => $processing->quote_id]))->toBe([$processing->id]);

    Sanctum::actingAs(moduleUser('work-orders.viewAny', 'work-orders.view', 'work-orders.viewAll', 'project-work-orders.viewAny', 'project-work-orders.view'));
    expect(gridIds('work-orders', ['quoteId' => $processing->quote_id]))->toEqualCanonicalizing([$processing->id, $project->id])
        ->and(gridIds('work-orders', ['work_order_type' => 'processing']))->toBe([$processing->id]);
});

it('opens a project commessa with project-work-orders.view and a processing one with work-orders.view', function () {
    $processing = typedWorkOrder(WorkOrderType::Processing);
    $project = typedWorkOrder(WorkOrderType::Project);

    Sanctum::actingAs(moduleUser('work-orders.view', 'work-orders.viewAll'));
    $this->getJson("/api/work-orders/{$processing->id}")->assertOk();
    $this->getJson("/api/work-orders/{$project->id}")->assertForbidden();

    Sanctum::actingAs(moduleUser('project-work-orders.view', 'work-orders.viewAll'));
    $this->getJson("/api/work-orders/{$project->id}")->assertOk();
    $this->getJson("/api/work-orders/{$processing->id}")->assertForbidden();
});

it('keeps a project commessa out of every action of whoever lacks project-work-orders.view', function () {
    $project = typedWorkOrder(WorkOrderType::Project, ['title' => 'Prima']);
    $processing = typedWorkOrder(WorkOrderType::Processing, ['title' => 'Prima']);
    Sanctum::actingAs(moduleUser('work-orders.view', 'work-orders.update', 'work-orders.viewAll'));

    $this->patchJson("/api/work-orders/{$project->id}", ['title' => 'Dopo'])->assertForbidden();
    $this->patchJson("/api/work-orders/{$processing->id}", ['title' => 'Dopo'])->assertOk();

    expect($project->fresh()->title)->toBe('Prima')->and($processing->fresh()->title)->toBe('Dopo');
});

it('points notifications and note mentions to the page of the commessa type', function () {
    $processing = typedWorkOrder(WorkOrderType::Processing);
    $project = typedWorkOrder(WorkOrderType::Project);
    $both = moduleUser('work-orders.view', 'project-work-orders.view', 'work-orders.viewAll');
    $processingOnly = moduleUser('work-orders.view', 'work-orders.viewAll');
    $notable = app(WorkOrderNotable::class);

    expect(RecordLinkResolver::pathFor($both, AssignmentTargetEnum::WorkOrder, $processing->id))->toBe("/work-orders/{$processing->id}")
        ->and(RecordLinkResolver::pathFor($both, AssignmentTargetEnum::WorkOrder, $project->id))->toBe("/project-work-orders/{$project->id}")
        ->and(RecordLinkResolver::pathFor($processingOnly, AssignmentTargetEnum::WorkOrder, $project->id))->toBeNull()
        ->and($notable->deepLinkPath($project, $both, null))->toBe("/project-work-orders/{$project->id}?tab=notes")
        ->and($notable->deepLinkPath($project, $processingOnly, null))->toBeNull()
        ->and($notable->modulePermission($project))->toBe('project-work-orders.view')
        ->and($notable->readersQuery($project)->pluck('id')->all())->toContain($both->id)->not->toContain($processingOnly->id);
});

it('offers project-work-orders.viewAny and view in the Role form, under Lavorazioni', function () {
    $this->artisan('permissions:sync');
    Sanctum::actingAs(moduleUser('roles.viewAny'));

    $area = collect($this->getJson('/api/authorization/permission-catalogue')->assertOk()->json('data.areas'))->firstWhere('key', 'operations');
    $module = collect($area['resources'])->firstWhere('resource', 'project-work-orders');

    expect($module['label_key'])->toBe('navigation.projectWorkOrders')
        ->and(array_column($module['permissions'], 'name'))->toBe(['project-work-orders.viewAny', 'project-work-orders.view']);
});

it('gives the project twins to every role and user holding the work orders viewing permissions', function () {
    $role = Role::findOrCreate('tecnico', 'web');
    $role->givePermissionTo(Permission::findOrCreate('work-orders.view', 'web'));
    $direct = moduleUser('work-orders.viewAny');
    $none = moduleUser('quotes.view');
    Permission::findOrCreate('project-work-orders.viewAny', 'web');

    (require database_path('migrations/2026_12_26_100000_add_project_work_orders_view_permissions.php'))->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($role->fresh()->hasPermissionTo('project-work-orders.view'))->toBeTrue()
        ->and($role->fresh()->hasPermissionTo('project-work-orders.viewAny'))->toBeFalse()
        ->and($direct->fresh()->hasDirectPermission('project-work-orders.viewAny'))->toBeTrue()
        ->and($none->fresh()->hasDirectPermission('project-work-orders.viewAny'))->toBeFalse()
        ->and(Permission::query()->where('name', 'project-work-orders.viewAny')->count())->toBe(1);
});
