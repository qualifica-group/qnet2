<?php

use App\Models\QuoteLine;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderLinePayment;
use App\Models\WorkOrderPaymentStatus;
use App\Notifications\WorkOrderLineDeliverableNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

/**
 * Line payment data of a commessa (spec 0201): PATCH
 * /api/work-orders/{workOrder}/contract-data/lines/{quoteLine}, AC-008..AC-010.
 */
uses(RefreshDatabase::class);

function linePaymentUrl(WorkOrder $workOrder, int $lineId): string
{
    return "/api/work-orders/{$workOrder->id}/contract-data/lines/{$lineId}";
}

it('AC-008: PATCH creates the record at first save, logs the activity and returns the updated line', function () {
    $workOrder = WorkOrder::factory()->create();
    $line = contractDataLine($workOrder, 'consultancy', 500);
    $status = WorkOrderPaymentStatus::factory()->create(['color' => 'blue']);
    Sanctum::actingAs(workOrderPaymentsUserWith(['managePayments']));

    expect(WorkOrderLinePayment::count())->toBe(0);

    $this->patchJson(linePaymentUrl($workOrder, $line->id), [
        'work_order_payment_status_id' => $status->id,
        'payment_agreement' => 'Two instalments',
        'has_unpaid' => true,
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.quote_line_id', $line->id)
        ->assertJsonPath('data.net_amount', '500.00')
        ->assertJsonPath('data.payment.status.id', $status->id)
        ->assertJsonPath('data.payment.status.color', 'blue')
        ->assertJsonPath('data.payment.status.allows_delivery', false)
        ->assertJsonPath('data.payment.payment_agreement', 'Two instalments')
        ->assertJsonPath('data.payment.has_unpaid', true);

    $payment = WorkOrderLinePayment::firstWhere('quote_line_id', $line->id);
    expect($payment->work_order_id)->toBe($workOrder->id);
    expect(Activity::where('subject_type', 'work_order_line_payment')->where('subject_id', $payment->id)->exists())->toBeTrue();
});

it('AC-008: only the submitted keys change, null clears status and agreement', function () {
    $workOrder = WorkOrder::factory()->create();
    $line = contractDataLine($workOrder, 'consultancy', 500);
    $status = WorkOrderPaymentStatus::factory()->create();
    WorkOrderLinePayment::factory()->create([
        'work_order_id' => $workOrder->id, 'quote_line_id' => $line->id,
        'work_order_payment_status_id' => $status->id, 'payment_agreement' => 'Keep me', 'has_unpaid' => true,
    ]);
    Sanctum::actingAs(workOrderPaymentsUserWith(['managePayments']));

    $this->patchJson(linePaymentUrl($workOrder, $line->id), ['has_unpaid' => false])
        ->assertOk()
        ->assertJsonPath('data.payment.has_unpaid', false)
        ->assertJsonPath('data.payment.payment_agreement', 'Keep me')
        ->assertJsonPath('data.payment.status.id', $status->id);

    $this->patchJson(linePaymentUrl($workOrder, $line->id), ['work_order_payment_status_id' => null, 'payment_agreement' => null])
        ->assertOk()
        ->assertJsonPath('data.payment.status', null)
        ->assertJsonPath('data.payment.payment_agreement', null);

    expect(WorkOrderLinePayment::count())->toBe(1);
});

it('AC-009: PATCH is 403 without managePayments or out of scope, 404 for a foreign line, 401 anonymous', function () {
    $workOrder = WorkOrder::factory()->create();
    $line = contractDataLine($workOrder, 'consultancy', 500);
    $foreign = contractDataLine(WorkOrder::factory()->create(), 'consultancy', 100);

    $this->patchJson(linePaymentUrl($workOrder, $line->id), ['has_unpaid' => true])->assertUnauthorized();

    Sanctum::actingAs(workOrderPaymentsUserWith(['viewContractData']));
    $this->patchJson(linePaymentUrl($workOrder, $line->id), ['has_unpaid' => true])->assertForbidden();

    Sanctum::actingAs(workOrderPaymentsUserWith(['managePayments', 'view'], viewAll: false));
    $this->patchJson(linePaymentUrl($workOrder, $line->id), ['has_unpaid' => true])->assertForbidden();

    Sanctum::actingAs(workOrderPaymentsUserWith(['managePayments']));
    $this->patchJson(linePaymentUrl($workOrder, $foreign->id), ['has_unpaid' => true])->assertNotFound();

    expect(WorkOrderLinePayment::count())->toBe(0);
});

it('AC-009: 422 for a missing or inactive status, an over-long agreement or a non-boolean flag', function () {
    $workOrder = WorkOrder::factory()->create();
    $line = contractDataLine($workOrder, 'consultancy', 500);
    $inactive = WorkOrderPaymentStatus::factory()->create(['is_active' => false]);
    Sanctum::actingAs(workOrderPaymentsUserWith(['managePayments']));

    $this->patchJson(linePaymentUrl($workOrder, $line->id), ['work_order_payment_status_id' => 999999])
        ->assertUnprocessable()->assertJsonValidationErrors('work_order_payment_status_id');
    $this->patchJson(linePaymentUrl($workOrder, $line->id), ['work_order_payment_status_id' => $inactive->id])
        ->assertUnprocessable()->assertJsonValidationErrors('work_order_payment_status_id');
    $this->patchJson(linePaymentUrl($workOrder, $line->id), ['payment_agreement' => str_repeat('a', 2001)])
        ->assertUnprocessable()->assertJsonValidationErrors('payment_agreement');
    $this->patchJson(linePaymentUrl($workOrder, $line->id), ['has_unpaid' => 'maybe'])
        ->assertUnprocessable()->assertJsonValidationErrors('has_unpaid');
});

it('AC-009: a status assigned and later deactivated stays saveable unchanged', function () {
    $workOrder = WorkOrder::factory()->create();
    $line = contractDataLine($workOrder, 'consultancy', 500);
    $status = WorkOrderPaymentStatus::factory()->create(['is_active' => false]);
    WorkOrderLinePayment::factory()->create([
        'work_order_id' => $workOrder->id, 'quote_line_id' => $line->id, 'work_order_payment_status_id' => $status->id,
    ]);
    Sanctum::actingAs(workOrderPaymentsUserWith(['managePayments']));

    $this->patchJson(linePaymentUrl($workOrder, $line->id), ['work_order_payment_status_id' => $status->id, 'has_unpaid' => true])
        ->assertOk()
        ->assertJsonPath('data.payment.status.id', $status->id);
});

it('AC-008: payment data stays editable on a force-closed commessa', function () {
    $workOrder = WorkOrder::factory()->create(['is_force_closed' => true, 'force_close_reason' => 'Done']);
    $line = contractDataLine($workOrder, 'consultancy', 500);
    Sanctum::actingAs(workOrderPaymentsUserWith(['managePayments']));

    $this->patchJson(linePaymentUrl($workOrder, $line->id), ['has_unpaid' => true])->assertOk();
});

// ---------------------------------------------------------------------------
// AC-010 — notification on the transition to a deliverable status
// ---------------------------------------------------------------------------

/** @return array{workOrder: WorkOrder, line: QuoteLine, actor: User, supervisor: User, participant: User, both: User} */
function deliverableScenario(): array
{
    $workOrder = WorkOrder::factory()->create();
    $line = contractDataLine($workOrder, 'consultancy', 500);
    $actor = workOrderPaymentsUserWith(['managePayments']);
    $supervisor = User::factory()->create();
    $participant = User::factory()->create();
    $both = User::factory()->create();
    $workOrder->supervisors()->attach([$actor->id, $supervisor->id, $both->id]);
    $workOrder->participants()->attach([$participant->id => ['position' => 1], $both->id => ['position' => 2]]);
    Sanctum::actingAs($actor);

    return compact('workOrder', 'line', 'actor', 'supervisor', 'participant', 'both');
}

it('AC-010: a transition to a deliverable status notifies supervisors and participants once, never the actor', function () {
    Notification::fake();
    ['workOrder' => $workOrder, 'line' => $line, 'actor' => $actor, 'supervisor' => $supervisor, 'participant' => $participant, 'both' => $both] = deliverableScenario();
    $deliverable = WorkOrderPaymentStatus::factory()->deliverable()->create(['name' => 'Saldato']);

    $this->patchJson(linePaymentUrl($workOrder, $line->id), ['work_order_payment_status_id' => $deliverable->id])->assertOk();

    Notification::assertSentToTimes($supervisor, WorkOrderLineDeliverableNotification::class, 1);
    Notification::assertSentToTimes($participant, WorkOrderLineDeliverableNotification::class, 1);
    Notification::assertSentToTimes($both, WorkOrderLineDeliverableNotification::class, 1);
    Notification::assertNotSentTo($actor, WorkOrderLineDeliverableNotification::class);

    Notification::assertSentTo($supervisor, WorkOrderLineDeliverableNotification::class, function ($notification, array $channels) use ($supervisor): bool {
        expect($channels)->toBe(['database', 'mail'])
            ->and($notification->toArray($supervisor)['message'])->toContain('Saldato')
            ->and($notification->toArray($supervisor)['action_url'])->toBeNull();

        return true;
    });
});

it('AC-010: no notification between two deliverable statuses, towards a non-deliverable one, or on other edits', function () {
    Notification::fake();
    ['workOrder' => $workOrder, 'line' => $line] = deliverableScenario();
    $first = WorkOrderPaymentStatus::factory()->deliverable()->create();
    $second = WorkOrderPaymentStatus::factory()->deliverable()->create();
    $blocked = WorkOrderPaymentStatus::factory()->create();
    WorkOrderLinePayment::factory()->create([
        'work_order_id' => $workOrder->id, 'quote_line_id' => $line->id, 'work_order_payment_status_id' => $first->id,
    ]);

    $this->patchJson(linePaymentUrl($workOrder, $line->id), ['work_order_payment_status_id' => $second->id])->assertOk();
    $this->patchJson(linePaymentUrl($workOrder, $line->id), ['work_order_payment_status_id' => $blocked->id])->assertOk();
    $this->patchJson(linePaymentUrl($workOrder, $line->id), ['has_unpaid' => true, 'payment_agreement' => 'x'])->assertOk();
    $this->patchJson(linePaymentUrl($workOrder, $line->id), ['work_order_payment_status_id' => $blocked->id])->assertOk();

    Notification::assertNothingSent();
});

it('AC-010: from a non-deliverable status back to a deliverable one notifies again', function () {
    Notification::fake();
    ['workOrder' => $workOrder, 'line' => $line, 'supervisor' => $supervisor] = deliverableScenario();
    $deliverable = WorkOrderPaymentStatus::factory()->deliverable()->create();
    $blocked = WorkOrderPaymentStatus::factory()->create();
    WorkOrderLinePayment::factory()->create([
        'work_order_id' => $workOrder->id, 'quote_line_id' => $line->id, 'work_order_payment_status_id' => $blocked->id,
    ]);

    $this->patchJson(linePaymentUrl($workOrder, $line->id), ['work_order_payment_status_id' => $deliverable->id])->assertOk();

    Notification::assertSentToTimes($supervisor, WorkOrderLineDeliverableNotification::class, 1);
});

it('AC-010: the notification links to the commessa only for a recipient who may view work orders', function () {
    $workOrder = WorkOrder::factory()->create();
    $notification = new WorkOrderLineDeliverableNotification($workOrder->id, 'COM-1 - Title', 'Product', 'Saldato', 'Actor');
    $viewer = workOrderPaymentsUserWith(['view']);

    expect($notification->toArray($viewer)['action_url'])->toBe("/work-orders/{$workOrder->id}")
        ->and($notification->toArray(User::factory()->create())['action_url'])->toBeNull();
});
