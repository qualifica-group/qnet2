<?php

use App\Enums\WorkOrderType;
use App\Models\User;
use App\Models\WorkOrderChatMessage;
use App\Models\WorkOrderChatRead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

// Spec 0352, B4: `project-work-orders` domain and the `work_order_type` row scope (AC-014, AC-015).

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $payload
 * @return array<int, array<string, mixed>>
 */
function projectRows(mixed $test, array $payload = []): array
{
    return $test->postJson('/api/tables/project-work-orders/rows', ['startRow' => 0, 'endRow' => 50, ...$payload])
        ->assertOk()->json('items');
}

it('AC-014: lists only project work orders (viewAll sees all of them)', function () {
    $project = typedWorkOrder(WorkOrderType::Project);
    typedWorkOrder(WorkOrderType::Processing);
    Sanctum::actingAs(projectWorkOrderViewer());

    $items = projectRows($this);

    expect(array_column($items, 'id'))->toBe([$project->id])
        ->and($items[0]['type'])->toBe('project');
});

it('AC-014: keeps the work-orders visibility perimeter (team only without viewAll)', function () {
    $actor = projectWorkOrderViewer(viewAll: false);
    $mine = typedWorkOrder(WorkOrderType::Project);
    $mine->supervisors()->attach($actor->id);
    typedWorkOrder(WorkOrderType::Project);
    typedWorkOrder(WorkOrderType::Processing)->supervisors()->attach($actor->id);
    Sanctum::actingAs($actor);

    expect(array_column(projectRows($this), 'id'))->toBe([$mine->id]);
});

it('AC-014: is forbidden without work-orders.viewAny', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/tables/project-work-orders/rows', ['startRow' => 0, 'endRow' => 50])->assertForbidden();
});

it('AC-014: exposes chat_unread_count as a sortable, non filterable column', function () {
    Sanctum::actingAs(projectWorkOrderViewer());

    $column = collect($this->getJson('/api/tables/project-work-orders/columns')->assertOk()->json('data.columns'))
        ->firstWhere('id', 'chat_unread_count');

    expect($column)->not->toBeNull()
        ->and($column['label'])->toBe('workOrders.columns.chat_unread_count')
        ->and($column['sortable'])->toBeTrue()
        ->and($column['filterable'])->toBeFalse();
});

it('AC-014: counts other authors non-deleted messages newer than the read mark, per user', function () {
    $actor = projectWorkOrderViewer();
    $workOrder = typedWorkOrder(WorkOrderType::Project);
    $workOrder->participants()->attach($actor->id, ['position' => 1]);
    $other = User::factory()->create();

    $first = WorkOrderChatMessage::factory()->create(['work_order_id' => $workOrder->id, 'user_id' => $other->id]);
    WorkOrderChatMessage::factory()->count(2)->create(['work_order_id' => $workOrder->id, 'user_id' => $other->id]);
    WorkOrderChatMessage::factory()->create(['work_order_id' => $workOrder->id, 'user_id' => $actor->id]);
    WorkOrderChatMessage::factory()->create(['work_order_id' => $workOrder->id, 'user_id' => $other->id])->delete();
    Sanctum::actingAs($actor);

    expect(projectRows($this)[0]['chat_unread_count'])->toBe(3);

    WorkOrderChatRead::factory()->create(['work_order_id' => $workOrder->id, 'user_id' => $actor->id, 'last_read_message_id' => $first->id]);

    expect(projectRows($this)[0]['chat_unread_count'])->toBe(2);

    // Another user's read mark does not move mine.
    WorkOrderChatRead::factory()->create(['work_order_id' => $workOrder->id, 'user_id' => $other->id, 'last_read_message_id' => PHP_INT_MAX]);

    expect(projectRows($this)[0]['chat_unread_count'])->toBe(2);
});

it('AC-014: is 0 outside the team, but a super-admin outside the team is counted', function () {
    $workOrder = typedWorkOrder(WorkOrderType::Project);
    WorkOrderChatMessage::factory()->count(2)->create(['work_order_id' => $workOrder->id]);

    Sanctum::actingAs(projectWorkOrderViewer());
    expect(projectRows($this)[0]['chat_unread_count'])->toBe(0);

    $admin = projectWorkOrderViewer();
    $admin->assignRole(Role::findOrCreate('super-admin', 'web'));
    Sanctum::actingAs($admin);
    expect(projectRows($this)[0]['chat_unread_count'])->toBe(2);
});

it('AC-014: sorts by chat_unread_count in both directions', function () {
    $actor = projectWorkOrderViewer();
    $low = typedWorkOrder(WorkOrderType::Project);
    $high = typedWorkOrder(WorkOrderType::Project);
    $none = typedWorkOrder(WorkOrderType::Project);
    foreach ([$low, $high, $none] as $workOrder) {
        $workOrder->supervisors()->attach($actor->id);
    }
    WorkOrderChatMessage::factory()->create(['work_order_id' => $low->id]);
    WorkOrderChatMessage::factory()->count(3)->create(['work_order_id' => $high->id]);
    Sanctum::actingAs($actor);

    $desc = array_column(projectRows($this, ['sortModel' => [['colId' => 'chat_unread_count', 'sort' => 'desc']]]), 'id');
    $asc = array_column(projectRows($this, ['sortModel' => [['colId' => 'chat_unread_count', 'sort' => 'asc']]]), 'id');

    expect($desc)->toBe([$high->id, $low->id, $none->id])
        ->and($asc)->toBe([$none->id, $low->id, $high->id]);
});

it('AC-014: rejects a filter on chat_unread_count (not in the filter allow-list)', function () {
    typedWorkOrder(WorkOrderType::Project);
    Sanctum::actingAs(projectWorkOrderViewer());

    $this->postJson('/api/tables/project-work-orders/rows', [
        'startRow' => 0, 'endRow' => 50,
        'filterModel' => ['chat_unread_count' => ['filterType' => 'number', 'type' => 'greaterThan', 'filter' => 0]],
    ])->assertStatus(422)->assertJsonValidationErrors('filterModel.chat_unread_count');
});

it('AC-015: work-orders with work_order_type=processing hides project, absent scope keeps every type', function () {
    $processing = typedWorkOrder(WorkOrderType::Processing);
    $project = typedWorkOrder(WorkOrderType::Project);
    Sanctum::actingAs(projectWorkOrderViewer());
    $body = ['startRow' => 0, 'endRow' => 50];

    $scoped = $this->postJson('/api/tables/work-orders/rows', [...$body, 'work_order_type' => 'processing'])->assertOk();
    $projectOnly = $this->postJson('/api/tables/work-orders/rows', [...$body, 'work_order_type' => 'project'])->assertOk();
    $unscoped = $this->postJson('/api/tables/work-orders/rows', $body)->assertOk();
    $nullScope = $this->postJson('/api/tables/work-orders/rows', [...$body, 'work_order_type' => null])->assertOk();

    expect(array_column($scoped->json('items'), 'id'))->toBe([$processing->id])
        ->and($scoped->json('pagination.total'))->toBe(1)
        ->and(array_column($projectOnly->json('items'), 'id'))->toBe([$project->id])
        ->and($unscoped->json('pagination.total'))->toBe(2)
        ->and($nullScope->json('pagination.total'))->toBe(2);
});

it('AC-015: an invalid work_order_type is a 422', function () {
    Sanctum::actingAs(projectWorkOrderViewer());

    $this->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 50, 'work_order_type' => 'bogus'])
        ->assertStatus(422)->assertJsonValidationErrors('work_order_type');
});

it('AC-015: the quoteId scope keeps showing project work orders (Contratto, Anagrafica tabs)', function () {
    $project = typedWorkOrder(WorkOrderType::Project);
    Sanctum::actingAs(projectWorkOrderViewer());

    $rows = $this->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 50, 'quoteId' => $project->quote_id])->assertOk();

    expect(array_column($rows->json('items'), 'id'))->toBe([$project->id]);
});
