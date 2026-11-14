<?php

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderProgressEntry;
use App\Notifications\WorkOrderUpdateRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
 * "Richiedi aggiornamento" on a work order (spec 0352, AC-001..AC-011).
 */

/** An actor with view + requestUpdate and the viewAll perimeter. */
function requestUpdateActor(array $abilities = ['viewAny', 'view', 'requestUpdate']): User
{
    Permission::findOrCreate('work-orders.requestUpdate');

    return workOrderUserWith($abilities);
}

/** @return array{0: WorkOrder, 1: array<int, User>, 2: User} work order, [p1, p2], supervisor not participating */
function requestUpdateWorkOrder(): array
{
    $workOrder = WorkOrder::factory()->create(['is_force_closed' => false]);
    $participants = User::factory()->count(2)->create();
    $supervisor = User::factory()->create();
    foreach ($participants->values() as $index => $participant) {
        $workOrder->participants()->attach($participant->id, ['position' => $index + 1]);
    }
    $workOrder->supervisors()->attach($supervisor->id);

    return [$workOrder, $participants->all(), $supervisor];
}

/** @return array<int, string> the action keys of the grid row titled $title */
function requestUpdateRowActions(string $title): array
{
    $items = test()->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items');

    return collect($items)->firstWhere('title', $title)['actions'];
}

function requestUpdateUrl(WorkOrder $workOrder): string
{
    return "/api/work-orders/{$workOrder->id}/request-update";
}

/** @return array<string, mixed> */
function requestUpdateBody(array $overrides = []): array
{
    return ['target' => 'participants', 'message' => 'Serve un aggiornamento entro venerdi.', ...$overrides];
}

it('notifies participants directly and supervisors in copy (AC-001)', function () {
    Notification::fake();
    Sanctum::actingAs(requestUpdateActor());
    [$workOrder, $participants, $supervisor] = requestUpdateWorkOrder();

    $this->postJson(requestUpdateUrl($workOrder), requestUpdateBody())
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Update request sent.')
        ->assertJsonPath('data', null);

    foreach ($participants as $participant) {
        Notification::assertSentTo($participant, WorkOrderUpdateRequested::class, fn ($n) => $n->toArray($participant)['is_cc'] === false);
    }
    Notification::assertSentTo($supervisor, WorkOrderUpdateRequested::class, fn ($n) => $n->toArray($supervisor)['is_cc'] === true);
    Notification::assertCount(3);
});

it('notifies only supervisors with no copy (AC-002)', function () {
    Notification::fake();
    Sanctum::actingAs(requestUpdateActor());
    [$workOrder, $participants, $supervisor] = requestUpdateWorkOrder();

    $this->postJson(requestUpdateUrl($workOrder), requestUpdateBody(['target' => 'supervisors']))->assertOk();

    Notification::assertSentTo($supervisor, WorkOrderUpdateRequested::class, fn ($n) => $n->toArray($supervisor)['is_cc'] === false);
    Notification::assertNotSentTo($participants[0], WorkOrderUpdateRequested::class);
    Notification::assertCount(1);
});

it('notifies the union once for all (AC-003)', function () {
    Notification::fake();
    Sanctum::actingAs(requestUpdateActor());
    [$workOrder, $participants, $supervisor] = requestUpdateWorkOrder();
    $workOrder->supervisors()->attach($participants[0]->id);

    $this->postJson(requestUpdateUrl($workOrder), requestUpdateBody(['target' => 'all']))->assertOk();

    Notification::assertSentToTimes($participants[0], WorkOrderUpdateRequested::class, 1);
    Notification::assertSentTo($supervisor, WorkOrderUpdateRequested::class, fn ($n) => $n->toArray($supervisor)['is_cc'] === false);
    Notification::assertCount(3);
});

it('rejects a target group with no recipients (AC-004)', function () {
    Notification::fake();
    Sanctum::actingAs(requestUpdateActor());
    $workOrder = WorkOrder::factory()->create(['is_force_closed' => false]);
    $workOrder->participants()->attach(User::factory()->create()->id, ['position' => 1]);

    $this->postJson(requestUpdateUrl($workOrder), requestUpdateBody(['target' => 'supervisors']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('target');

    Notification::assertNothingSent();
});

it('validates message and target (AC-005)', function (array $overrides, string $field) {
    Sanctum::actingAs(requestUpdateActor());
    [$workOrder] = requestUpdateWorkOrder();

    $this->postJson(requestUpdateUrl($workOrder), requestUpdateBody($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'missing message' => [['message' => null], 'message'],
    'short message' => [['message' => 'ab'], 'message'],
    'long message' => [['message' => str_repeat('a', 2001)], 'message'],
    'unknown target' => [['target' => 'everyone'], 'target'],
]);

it('forbids without the permission or outside the perimeter (AC-006)', function () {
    Notification::fake();
    Permission::findOrCreate('work-orders.requestUpdate');
    [$workOrder] = requestUpdateWorkOrder();

    Sanctum::actingAs(workOrderUserWith(['viewAny', 'view']));
    $this->postJson(requestUpdateUrl($workOrder), requestUpdateBody())->assertForbidden();

    Permission::findOrCreate('work-orders.requestUpdate');
    Permission::findOrCreate('work-orders.view');
    $outsider = User::factory()->create();
    $outsider->givePermissionTo(['work-orders.view', 'work-orders.requestUpdate']);
    Sanctum::actingAs($outsider);
    $this->postJson(requestUpdateUrl($workOrder), requestUpdateBody())->assertForbidden();

    Notification::assertNothingSent();
});

it('answers 404 for a missing work order and 401 when unauthenticated', function () {
    $this->postJson('/api/work-orders/999999/request-update', requestUpdateBody())->assertUnauthorized();

    Sanctum::actingAs(requestUpdateActor());
    $this->postJson('/api/work-orders/999999/request-update', requestUpdateBody())->assertNotFound();
});

// Spec 0395 (REQUIREMENT CHANGED): "completed or closed" is the working phase, no longer the progress.
it('refuses a work order completed, cancelled or terminated (AC-007)', function () {
    Notification::fake();
    Sanctum::actingAs(requestUpdateActor());

    $closed = collect(['completed', 'cancelled', 'terminated'])->map(function (string $phase): WorkOrder {
        [$workOrder] = requestUpdateWorkOrder();
        $workOrder->forceFill(['work_phase' => $phase])->save();

        return $workOrder;
    });

    foreach ($closed as $workOrder) {
        $this->postJson(requestUpdateUrl($workOrder), requestUpdateBody())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This work order is already completed or closed.')
            ->assertJsonMissingPath('errors');
    }

    Notification::assertNothingSent();
});

it('works on an in-progress work order', function () {
    Notification::fake();
    Sanctum::actingAs(requestUpdateActor());
    [$workOrder] = requestUpdateWorkOrder();
    WorkOrderProgressEntry::factory()->forWorkOrder($workOrder)->increment(40, '2026-10-01')->create();

    $this->postJson(requestUpdateUrl($workOrder), requestUpdateBody())->assertOk();
});

it('stores and mails a notification with a per-recipient link (AC-008)', function () {
    [$workOrder] = requestUpdateWorkOrder();
    $requester = User::factory()->create(['name' => 'Mario Rossi']);
    Permission::findOrCreate('work-orders.view');
    $reader = User::factory()->create();
    $reader->givePermissionTo('work-orders.view');
    $outsider = User::factory()->create();

    $notification = new WorkOrderUpdateRequested($workOrder, $requester, 'Aggiorna lo stato', true);

    expect($notification->via($reader))->toBe(['database', 'mail']);

    $data = $notification->toArray($reader);
    expect($data['title'])->toBe(__('Update requested'))
        ->and($data['message'])->toContain('Mario Rossi')->toContain($workOrder->code)->toContain('Aggiorna lo stato')
        ->and($data['action_url'])->toBe("/work-orders/{$workOrder->id}")
        ->and($data['is_cc'])->toBeTrue();

    $none = $notification->toArray($outsider);
    expect($none['action_url'])->toBeNull()
        ->and($none['message'])->toContain('You cannot open this record');

    expect($notification->toMail($reader)->actionUrl)->toEndWith("/work-orders/{$workOrder->id}")
        ->and($notification->toMail($outsider)->actionUrl)->toBeNull();
});

it('computes the request_update flag on the detail (AC-009)', function () {
    [$open] = requestUpdateWorkOrder();
    [$done] = requestUpdateWorkOrder();
    $done->forceFill(['work_phase' => 'completed'])->save();

    Sanctum::actingAs(requestUpdateActor());
    $this->getJson("/api/work-orders/{$open->id}")->assertOk()->assertJsonPath('permissions.actions.request_update', true);
    $this->getJson("/api/work-orders/{$done->id}")->assertOk()->assertJsonPath('permissions.actions.request_update', false);

    Sanctum::actingAs(workOrderUserWith(['viewAny', 'view']));
    $this->getJson("/api/work-orders/{$open->id}")->assertOk()->assertJsonPath('permissions.actions.request_update', false);
});

it('offers request_update in the grid row actions under the same conditions (AC-010)', function () {
    WorkOrder::factory()->create(['title' => 'Aperta', 'is_force_closed' => false]);
    WorkOrder::factory()->create(['title' => 'Chiusa', 'is_force_closed' => true, 'force_close_reason' => 'x'])
        ->forceFill(['work_phase' => 'cancelled'])->save();

    Sanctum::actingAs(requestUpdateActor());
    expect(requestUpdateRowActions('Aperta'))->toContain('request_update')
        ->and(requestUpdateRowActions('Chiusa'))->not->toContain('request_update');
    $keys = collect($this->getJson('/api/tables/work-orders/columns')->assertOk()->json('data.actions'))->pluck('key');
    expect($keys)->toContain('request_update');

    Sanctum::actingAs(workOrderUserWith(['viewAny', 'view']));
    expect(requestUpdateRowActions('Aperta'))->not->toContain('request_update');
});

it('registers the permission in the catalogue and leaves the work order untouched (AC-011)', function () {
    $this->artisan('permissions:sync')->assertSuccessful();
    expect(Permission::where('name', 'work-orders.requestUpdate')->exists())->toBeTrue();

    Notification::fake();
    Sanctum::actingAs(requestUpdateActor());
    [$workOrder] = requestUpdateWorkOrder();
    $updatedAt = $workOrder->fresh()->updated_at;
    $activities = Activity::count();

    $this->postJson(requestUpdateUrl($workOrder), requestUpdateBody())->assertOk();

    expect($workOrder->fresh()->updated_at->equalTo($updatedAt))->toBeTrue()
        ->and(Activity::count())->toBe($activities);
});
