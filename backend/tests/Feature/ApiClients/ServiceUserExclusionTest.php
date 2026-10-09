<?php

use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\ProformaRequest;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use App\RequestManagement\RequestManagementNotable;
use App\Services\LeadOperatorDistributor;
use App\Services\ProformaRequests\ProformaRequestNotable;
use App\Services\Tasks\TaskNotable;
use App\Services\WorkOrders\WorkOrderNotable;
use App\Stats\Users\UsersStatsDefinition;
use App\Stats\Widgets\StatWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('never offers the technical user as a recipient of a notes thread', function (Closure $notableAndRecord) {
    Permission::findOrCreate('request-management.view');
    Permission::findOrCreate('request-management.viewAll');
    [$notable, $record] = $notableAndRecord();
    [$client] = createApiClientWithKey();
    $superAdmin = User::factory()->create()->assignRole(Role::findOrCreate('super-admin', 'web'));

    $ids = $notable->mentionableUsersQuery($record)->pluck('users.id')->all();

    expect($ids)->toContain($superAdmin->id)->not->toContain($client->service_user_id);
})->with([
    'tasks' => [fn () => [new TaskNotable, Task::factory()->create()]],
    'proforma requests' => [fn () => [new ProformaRequestNotable, ProformaRequest::factory()->create()]],
    'work orders' => [fn () => [new WorkOrderNotable, WorkOrder::factory()->create()]],
    'request management' => [fn () => [app(RequestManagementNotable::class), Opportunity::factory()->create()]],
]);

it('does not count the technical user in the users stats', function () {
    User::factory()->create();
    createApiClientWithKey();

    $widgets = collect(app(UsersStatsDefinition::class)->widgets());
    $values = $widgets->whereInstanceOf(StatWidget::class)->mapWithKeys(fn (StatWidget $widget) => [$widget->key => $widget->value]);
    $byRole = $widgets->firstWhere('key', 'by_role');

    // The human user and the admin that createApiClientWithKey() creates.
    expect($values['active'])->toBe(2)
        ->and($byRole->total)->toBe(0);
});

it('hides the technical user from the role members and keeps its role on a member sync', function () {
    [$client] = createApiClientWithKey();
    $human = User::factory()->create();
    $role = Role::findByName('super-admin', 'web');
    $human->assignRole($role);
    Permission::findOrCreate('roles.view');
    Permission::findOrCreate('roles.update');
    Sanctum::actingAs(User::factory()->create()->givePermissionTo('roles.view', 'roles.update')->assignRole($role));

    $members = $this->getJson("/api/roles/{$role->id}")->assertOk()->json('data.users');
    expect($members)->toContain($human->id)->not->toContain($client->service_user_id);

    $this->patchJson("/api/roles/{$role->id}", ['users' => $members])->assertOk();

    expect($client->serviceUser->fresh()->hasRole('super-admin'))->toBeTrue();
});

it('never routes a lead to the technical user', function () {
    [$client] = createApiClientWithKey();
    $site = OperationalSite::factory()->create();
    $client->serviceUser->employment()->create(['is_assignable' => true])->operationalSites()->attach($site->id);

    expect(app(LeadOperatorDistributor::class)->operatorIdsBySite([$site->id]))->toBe([$site->id => []]);
});
