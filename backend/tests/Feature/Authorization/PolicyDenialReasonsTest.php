<?php

use App\Enums\FilterViewVisibility;
use App\Models\Contract;
use App\Models\ImportMappingTemplate;
use App\Models\Note;
use App\Models\Opportunity;
use App\Models\PurchaseRequest;
use App\Models\Quote;
use App\Models\TableFilterView;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// Spec 0328 (D-6, AC-003, AC-004, AC-006): a Policy that refuses for a reason beyond the permission
// says WHY (reason "denied"); a missing permission is named (reason "missing_permission").
uses(RefreshDatabase::class);

function denialActor(array $permissions = []): User
{
    foreach ($permissions as $name) {
        Permission::findOrCreate($name, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);
    Sanctum::actingAs($user);

    return $user;
}

/**
 * Each case: HTTP method, a builder returning the URL of a record OUTSIDE the actor's perimeter, the permission
 * the actor holds in the perimeter branch, and the lang key of the reason expected there.
 */
dataset('perimeter denials', [
    'opportunity update (AC-003)' => ['patch', fn (User $actor) => '/api/opportunities/'.Opportunity::factory()->create()->id, 'opportunities.update', 'opportunity_out_of_scope'],
    'opportunity delete' => ['delete', fn (User $actor) => '/api/opportunities/'.Opportunity::factory()->create()->id, 'opportunities.delete', 'opportunity_out_of_scope'],
    'quote show' => ['get', fn (User $actor) => '/api/quotes/'.Quote::factory()->create()->id, 'quotes.view', 'quote_out_of_scope'],
    'contract show' => ['get', fn (User $actor) => '/api/contracts/'.Contract::factory()->create()->id, 'contracts.view', 'contract_out_of_scope'],
    'work order delete' => ['delete', fn (User $actor) => '/api/work-orders/'.WorkOrder::factory()->create()->id, 'work-orders.delete', 'work_order_out_of_scope'],
    'work order costs' => ['get', fn (User $actor) => '/api/work-orders/'.WorkOrder::factory()->create()->id.'/costs', 'work-orders.viewCosts', 'work_order_out_of_scope'],
    'purchase request delete' => ['delete', fn (User $actor) => '/api/purchase-requests/'.PurchaseRequest::factory()->create()->id, 'purchase-requests.delete', 'purchase_request_out_of_scope'],
    'task delete (scope)' => ['delete', fn (User $actor) => '/api/tasks/'.Task::factory()->create()->id, 'tasks.delete', 'task_out_of_scope'],
    'task block (scope)' => ['post', fn (User $actor) => '/api/tasks/'.Task::factory()->create()->id.'/block', 'tasks.block', 'task_out_of_scope'],
    'time entry show (owner)' => ['get', fn (User $actor) => '/api/time-entries/'.TimeEntry::factory()->create()->id, 'time-entries.view', 'time_entry_not_owner'],
    'user self delete' => ['delete', fn (User $actor) => '/api/users/'.$actor->id, 'users.delete', 'user_self_delete'],
    'user super-admin delete' => ['delete', function (User $actor) {
        $target = User::factory()->create();
        $target->assignRole(Role::findOrCreate('super-admin', 'web'));

        return '/api/users/'.$target->id;
    }, 'users.delete', 'user_super_admin_protected'],
    'note update (author only)' => ['patch', fn (User $actor) => '/api/notes/'.Note::factory()->create()->id, null, 'note_author_only_edit'],
    'note delete (author only)' => ['delete', fn (User $actor) => '/api/notes/'.Note::factory()->create()->id, null, 'note_author_only_delete'],
    'shared filter view of another user' => ['delete', fn (User $actor) => '/api/tables/users/filter-views/'.TableFilterView::factory()->create(['visibility' => FilterViewVisibility::Shared->value])->id, 'users.viewAny', 'table_filter_view_not_owner'],
    'import mapping template of another user' => ['delete', fn (User $actor) => '/api/imports/leads/mapping-templates/'.ImportMappingTemplate::factory()->create()->id, 'leads.import', 'import_mapping_template_not_owner'],
]);

it('AC-004: the perimeter branch answers "denied" with its own reason', function (string $method, Closure $url, ?string $permission, string $reason) {
    $actor = denialActor($permission === null ? [] : [$permission]);

    $this->json(strtoupper($method), $url($actor), ['body' => '<p>x</p>'], ['Accept-Language' => 'it'])
        ->assertForbidden()
        ->assertExactJson([
            'success' => false,
            'message' => trans("authorization.{$reason}", [], 'it'),
            'authorization' => ['reason' => 'denied', 'permission' => null],
        ]);
})->with('perimeter denials');

dataset('permission denials', [
    'opportunity update' => ['patch', fn () => '/api/opportunities/'.Opportunity::factory()->create()->id, 'opportunities.update'],
    'quote show' => ['get', fn () => '/api/quotes/'.Quote::factory()->create()->id, 'quotes.view'],
    'contract show' => ['get', fn () => '/api/contracts/'.Contract::factory()->create()->id, 'contracts.view'],
    'work order delete' => ['delete', fn () => '/api/work-orders/'.WorkOrder::factory()->create()->id, 'work-orders.delete'],
    'work order costs' => ['get', fn () => '/api/work-orders/'.WorkOrder::factory()->create()->id.'/costs', 'work-orders.viewCosts'],
    'purchase request delete' => ['delete', fn () => '/api/purchase-requests/'.PurchaseRequest::factory()->create()->id, 'purchase-requests.delete'],
    'task delete' => ['delete', fn () => '/api/tasks/'.Task::factory()->create()->id, 'tasks.delete'],
    'task block' => ['post', fn () => '/api/tasks/'.Task::factory()->create()->id.'/block', 'tasks.block'],
    'time entry show' => ['get', fn () => '/api/time-entries/'.TimeEntry::factory()->create()->id, 'time-entries.view'],
    'user delete' => ['delete', fn () => '/api/users/'.User::factory()->create()->id, 'users.delete'],
]);

it('AC-004: the permission branch answers "missing_permission" naming the permission', function (string $method, Closure $url, string $permission) {
    Permission::findOrCreate($permission, 'web');
    denialActor();

    $this->json(strtoupper($method), $url(), ['body' => '<p>x</p>'], ['Accept-Language' => 'it'])
        ->assertForbidden()
        ->assertJsonPath('authorization.reason', 'missing_permission')
        ->assertJsonPath('authorization.permission', $permission);
})->with('permission denials');

it('AC-005: the perimeter reason beats a visibility permission denied earlier in the request', function () {
    denialActor(['opportunities.update']);
    Permission::findOrCreate('opportunities.viewAll', 'web');
    $target = Opportunity::factory()->create();

    $this->patchJson("/api/opportunities/{$target->id}", [], ['Accept-Language' => 'it'])
        ->assertForbidden()
        ->assertJsonPath('authorization.permission', null)
        ->assertJsonPath('message', trans('authorization.opportunity_out_of_scope', [], 'it'));
});

it('AC-006: the perimeter reason is translated to English', function () {
    denialActor(['opportunities.update']);
    $target = Opportunity::factory()->create();

    $this->patchJson("/api/opportunities/{$target->id}", [], ['Accept-Language' => 'en'])
        ->assertForbidden()
        ->assertJsonPath('message', trans('authorization.opportunity_out_of_scope', [], 'en'));
});

it('keeps the same truth table: an in-perimeter actor is allowed', function () {
    $actor = denialActor(['opportunities.delete']);
    $own = Opportunity::factory()->create(['supervisor_id' => $actor->id]);

    $this->deleteJson("/api/opportunities/{$own->id}")->assertSuccessful();
});
