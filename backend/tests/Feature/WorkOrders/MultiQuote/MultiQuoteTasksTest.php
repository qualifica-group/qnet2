<?php

use App\Models\Registry;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\TaskType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrders\WorkOrderTaskGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function multiQuoteTaskBody(array $overrides = []): array
{
    return [
        'title' => 'Task su commessa multi-offerta',
        'requester_id' => User::factory()->create()->id,
        'assignee_ids' => [User::factory()->create()->id],
        'end_date' => '2026-12-31',
        ...$overrides,
    ];
}

it('AC-013: tasks generated from a template on a multi-offer commessa carry no registry and no opportunity', function () {
    [$workOrder] = multiQuoteReadFixture();
    $workOrder->supervisors()->attach(User::factory()->create()->id);
    $template = TaskTemplate::factory()->create();
    TaskTemplateItem::factory()->forTemplate($template)->atPosition(0)->create();

    $tasks = app(WorkOrderTaskGenerator::class)->generate($workOrder, $template, User::factory()->create());

    expect($tasks)->toHaveCount(1)
        ->and($tasks[0]->registry_id)->toBeNull()
        ->and($tasks[0]->opportunity_id)->toBeNull()
        ->and($tasks[0]->work_order_id)->toBe($workOrder->id);
});

it('AC-013: a task linked to a multi-offer commessa accepts one of the source customers', function () {
    [$workOrder, $quotes] = multiQuoteReadFixture();
    Sanctum::actingAs(taskActorWith(['create']));
    $registryId = $quotes[1]->opportunity->registry_id;

    $this->postJson('/api/tasks', multiQuoteTaskBody(['work_order_id' => $workOrder->id, 'registry_id' => $registryId]))
        ->assertCreated();

    expect(Task::query()->where('work_order_id', $workOrder->id)->value('registry_id'))->toBe($registryId);
});

it('AC-013: a task linked to a multi-offer commessa rejects a foreign customer', function () {
    [$workOrder] = multiQuoteReadFixture();
    Sanctum::actingAs(taskActorWith(['create']));

    $this->postJson('/api/tasks', multiQuoteTaskBody(['work_order_id' => $workOrder->id, 'registry_id' => Registry::factory()->create()->id]))
        ->assertStatus(422)->assertJsonValidationErrors('registry_id');

    $this->assertDatabaseMissing('tasks', ['work_order_id' => $workOrder->id]);
});

it('a task linked to a multi-offer commessa without a customer keeps registry_id null', function () {
    [$workOrder] = multiQuoteReadFixture();
    Sanctum::actingAs(taskActorWith(['create']));

    $this->postJson('/api/tasks', multiQuoteTaskBody(['work_order_id' => $workOrder->id]))->assertCreated();

    expect(Task::query()->where('work_order_id', $workOrder->id)->value('registry_id'))->toBeNull();
});

it('a task on a mono-offer commessa still rejects a mismatching customer', function () {
    $workOrder = WorkOrder::factory()->create();
    Sanctum::actingAs(taskActorWith(['create']));

    $this->postJson('/api/tasks', multiQuoteTaskBody(['work_order_id' => $workOrder->id, 'registry_id' => Registry::factory()->create()->id]))
        ->assertStatus(422)->assertJsonValidationErrors('registry_id');
});

it('D-11: a time entry on a multi-offer commessa derives no customer from it', function () {
    [$workOrder] = multiQuoteReadFixture();
    foreach (['viewAny', 'view', 'create', 'viewAll'] as $ability) {
        Permission::findOrCreate("time-entries.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo(['time-entries.create', 'time-entries.view', 'time-entries.viewAny']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/time-entries', [
        'date' => '2026-09-14',
        'title' => 'Chiamata',
        'task_type_id' => TaskType::factory()->create()->id,
        'minutes' => 30,
        'work_order_id' => $workOrder->id,
    ])->assertCreated();

    $this->assertDatabaseHas('time_entries', ['id' => $response->json('data.id'), 'work_order_id' => $workOrder->id, 'registry_id' => null]);
});
