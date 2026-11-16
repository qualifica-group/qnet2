<?php

use App\Models\Quote;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\TaskAssigned;
use App\Notifications\WorkOrderTasksAssignedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Creating a commessa from a Modello di Task sends each supervisor ONE
 * summary of the tasks assigned to them, never one TaskAssigned per
 * generated task (a 21-row template used to send 21 notifications + emails).
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::findOrCreate('work-orders.create');
    Permission::findOrCreate('work-orders.view');
});

function createWorkOrderFromTemplate(User $actor, TaskTemplate $template, array $supervisorIds): WorkOrder
{
    Sanctum::actingAs($actor);

    $response = test()->postJson('/api/work-orders', [
        'quote_id' => Quote::factory()->create()->id, 'title' => 'Riepilogo', 'type' => 'processing',
        'start_date' => '2026-10-01', 'supervisor_ids' => $supervisorIds,
        'task_template_id' => $template->id,
    ])->assertCreated();

    // The request switches the locale to the actor's own: assert the Italian copy.
    App::setLocale('it');

    return WorkOrder::query()->findOrFail($response->json('data.id'));
}

function templateWithRows(int $rows): TaskTemplate
{
    $template = TaskTemplate::factory()->create();

    foreach (range(0, $rows - 1) as $position) {
        TaskTemplateItem::factory()->forTemplate($template)->atPosition($position)->create();
    }

    return $template;
}

it('sends each supervisor one summary with the task count, and no per-task notification', function () {
    Notification::fake();
    $actor = User::factory()->create()->givePermissionTo('work-orders.create');
    $supervisorA = User::factory()->create()->givePermissionTo('work-orders.view');
    $supervisorB = User::factory()->create();

    $workOrder = createWorkOrderFromTemplate($actor, templateWithRows(3), [$supervisorA->id, $supervisorB->id]);

    expect(Task::query()->where('work_order_id', $workOrder->id)->count())->toBe(3);
    Notification::assertNotSentTo([$supervisorA, $supervisorB], TaskAssigned::class);
    Notification::assertSentToTimes($supervisorA, WorkOrderTasksAssignedNotification::class, 1);
    Notification::assertSentToTimes($supervisorB, WorkOrderTasksAssignedNotification::class, 1);

    Notification::assertSentTo($supervisorA, WorkOrderTasksAssignedNotification::class, function ($notification, array $channels) use ($supervisorA, $actor, $workOrder): bool {
        $payload = $notification->toArray($supervisorA);

        return $channels === ['database', 'mail']
            && $payload['title'] === 'Attività della commessa assegnate a te'
            && $payload['message'] === "{$actor->name} ti ha assegnato le 3 attività della commessa {$workOrder->code} - {$workOrder->title}."
            && $payload['action_url'] === "/work-orders/{$workOrder->id}";
    });

    // No work-orders.view: the summary still arrives, without a link.
    Notification::assertSentTo($supervisorB, WorkOrderTasksAssignedNotification::class, fn ($notification): bool => $notification->toArray($supervisorB)['action_url'] === null);
});

it('uses the singular sentence for a one-row template', function () {
    Notification::fake();
    $actor = User::factory()->create()->givePermissionTo('work-orders.create');
    $supervisor = User::factory()->create();

    $workOrder = createWorkOrderFromTemplate($actor, templateWithRows(1), [$supervisor->id]);

    Notification::assertSentTo($supervisor, WorkOrderTasksAssignedNotification::class, fn ($notification): bool => $notification->toArray($supervisor)['message']
        === "{$actor->name} ti ha assegnato l'attività della commessa {$workOrder->code} - {$workOrder->title}.");
});

it('does not notify the actor who is also a supervisor', function () {
    Notification::fake();
    $actor = User::factory()->create()->givePermissionTo('work-orders.create');
    $supervisor = User::factory()->create();

    createWorkOrderFromTemplate($actor, templateWithRows(2), [$actor->id, $supervisor->id]);

    Notification::assertNotSentTo($actor, WorkOrderTasksAssignedNotification::class);
    Notification::assertSentToTimes($supervisor, WorkOrderTasksAssignedNotification::class, 1);
});

it('renders the summary email with the work order card', function () {
    $actor = User::factory()->create()->givePermissionTo('work-orders.create');
    $supervisor = User::factory()->create()->givePermissionTo('work-orders.view');
    Notification::fake();

    $workOrder = createWorkOrderFromTemplate($actor, templateWithRows(2), [$supervisor->id]);

    Notification::assertSentTo($supervisor, WorkOrderTasksAssignedNotification::class, function ($notification) use ($supervisor, $workOrder): bool {
        $html = (string) $notification->toMail($supervisor)->render();

        return str_contains($html, "{$workOrder->code} - {$workOrder->title}")
            && str_contains($html, "/work-orders/{$workOrder->id}");
    });
});
