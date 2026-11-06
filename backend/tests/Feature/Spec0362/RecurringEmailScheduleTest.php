<?php

use App\Enums\OutboundEmailStatus;
use App\Jobs\SendOutboundEmailJob;
use App\Models\Attachment;
use App\Models\Invoice;
use App\Models\OutboundEmail;
use App\Models\OutboundEmailRecurrence;
use App\Models\PurchaseOrder;
use App\Models\WorkOrder;
use App\Services\OutboundEmails\EmailOwnerRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| POST .../emails/{email}/schedule and /unschedule (spec 0362)
|--------------------------------------------------------------------------
*/

uses(RefreshDatabase::class);

function scheduleUrl(WorkOrder $workOrder, OutboundEmail $email, string $action = 'schedule'): string
{
    return "/api/work-orders/{$workOrder->id}/emails/{$email->id}/{$action}";
}

it('AC-001: a valid draft is programmed from starts_on, nothing sent, no copy', function () {
    Queue::fake();
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $email = recurringDraft($workOrder, $actor);
    $rule = recurringRuleInput();
    Sanctum::actingAs($actor);

    $this->postJson(scheduleUrl($workOrder, $email), ['recurrence' => $rule])
        ->assertOk()
        ->assertJsonPath('data.status', 'scheduled')
        ->assertJsonPath('data.recurrence.next_send_on', $rule['starts_on'])
        ->assertJsonPath('data.recurrence.ends_on', $rule['ends_on'])
        ->assertJsonPath('data.recurrence.sent_count', 0)
        ->assertJsonPath('data.recurrence.weekdays', [1])
        ->assertJsonPath('data.can.unschedule', true)
        ->assertJsonPath('data.can.schedule', false);

    Queue::assertNothingPushed();
    expect(OutboundEmail::query()->count())->toBe(1)
        ->and($email->fresh()->status)->toBe(OutboundEmailStatus::Scheduled)
        ->and(OutboundEmailRecurrence::query()->count())->toBe(1);
});

it('AC-002: missing or inconsistent dates are 422 and the draft stays a draft', function (array $overrides, string $field) {
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $email = recurringDraft($workOrder, $actor);
    Sanctum::actingAs($actor);

    $this->postJson(scheduleUrl($workOrder, $email), ['recurrence' => recurringRuleInput($overrides)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    expect($email->fresh()->status)->toBe(OutboundEmailStatus::Draft)
        ->and(OutboundEmailRecurrence::query()->count())->toBe(0);
})->with([
    'ends_on missing' => [['ends_on' => null], 'recurrence.ends_on'],
    'ends_on before starts_on' => [fn () => ['starts_on' => today()->addDays(5)->toDateString(), 'ends_on' => today()->addDays(2)->toDateString()], 'recurrence.ends_on'],
    'starts_on in the past' => [fn () => ['starts_on' => today()->subDay()->toDateString()], 'recurrence.starts_on'],
]);

it('AC-003: pairing rules are enforced on recurrence.*', function (array $overrides, string $field) {
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $email = recurringDraft($workOrder, $actor);
    Sanctum::actingAs($actor);

    $this->postJson(scheduleUrl($workOrder, $email), ['recurrence' => recurringRuleInput($overrides)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'weekdays on daily' => [['frequency' => 'daily'], 'recurrence.weekdays'],
    'weekly without weekdays' => [['weekdays' => null], 'recurrence.weekdays'],
    'month_day with ordinal' => [['frequency' => 'monthly', 'weekdays' => null, 'month_mode' => 'ordinal', 'ordinal' => 2, 'ordinal_weekday' => 3, 'month_day' => 5], 'recurrence.month_day'],
    'interval zero' => [['interval' => 0], 'recurrence.interval'],
]);

it('AC-004: a draft without recipients, subject or body is 422 like send', function () {
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $email = recurringDraft($workOrder, $actor, ['to_recipients' => [], 'subject' => '', 'body' => '<p></p>']);
    Sanctum::actingAs($actor);

    $this->postJson(scheduleUrl($workOrder, $email), ['recurrence' => recurringRuleInput()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to', 'subject', 'body']);

    expect($email->fresh()->status)->toBe(OutboundEmailStatus::Draft);
});

it('AC-005: authz - no ability, out of perimeter, not author, foreign draft, foreign work order', function () {
    $workOrder = WorkOrder::factory()->create();
    $author = workOrderEmailActor($workOrder, ['sendEmail']);
    $draft = recurringDraft($workOrder, $author);
    $payload = ['recurrence' => recurringRuleInput()];

    Sanctum::actingAs(workOrderEmailActor($workOrder, ['viewEmails']));
    $this->postJson(scheduleUrl($workOrder, $draft), $payload)->assertForbidden();

    Sanctum::actingAs(workOrderEmailActor($workOrder, ['sendEmail'], inScope: false));
    $this->postJson(scheduleUrl($workOrder, $draft), $payload)->assertForbidden();

    // A draft of someone else is never found.
    Sanctum::actingAs(workOrderEmailActor($workOrder, ['sendEmail']));
    $this->postJson(scheduleUrl($workOrder, $draft), $payload)->assertNotFound();

    // A programmed email of someone else is visible but not unschedulable.
    $programmed = programmedEmail($workOrder, $author);
    $this->postJson(scheduleUrl($workOrder, $programmed, 'unschedule'))
        ->assertForbidden();
    expect($programmed->fresh()->status)->toBe(OutboundEmailStatus::Scheduled);

    // An email of another work order is a 404.
    $other = WorkOrder::factory()->create();
    $otherActor = workOrderEmailActor($other, ['sendEmail']);
    Sanctum::actingAs($otherActor);
    $this->postJson(scheduleUrl($other, $programmed), $payload)->assertNotFound();
});

it('AC-006: a completed or closed work order cannot be programmed (409)', function () {
    $workOrder = WorkOrder::factory()->create();
    $workOrder->forceFill(['is_force_closed' => true])->save();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $email = recurringDraft($workOrder, $actor);
    Sanctum::actingAs($actor);

    $this->postJson(scheduleUrl($workOrder, $email), ['recurrence' => recurringRuleInput()])
        ->assertStatus(409)
        ->assertJsonPath('message', __('outbound_emails.recurrence_owner_not_active'));

    expect($email->fresh()->status)->toBe(OutboundEmailStatus::Draft);
});

it('AC-007: a non-draft email cannot be programmed; a scheduled one is locked (PATCH, DELETE, send)', function () {
    Queue::fake();
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    Sanctum::actingAs($actor);
    $payload = ['recurrence' => recurringRuleInput()];

    foreach (['sent', 'queued'] as $state) {
        $email = OutboundEmail::factory()->forEmailable($workOrder)->{$state}()->create(['sender_user_id' => $actor->id]);
        $this->postJson(scheduleUrl($workOrder, $email), $payload)->assertStatus(409);
    }

    $programmed = programmedEmail($workOrder, $actor);
    $this->postJson(scheduleUrl($workOrder, $programmed), $payload)->assertStatus(409);
    $this->patchJson("/api/work-orders/{$workOrder->id}/emails/{$programmed->id}", ['subject' => 'x'])->assertStatus(409);
    $this->deleteJson("/api/work-orders/{$workOrder->id}/emails/{$programmed->id}")->assertStatus(409);
    $this->postJson(scheduleUrl($workOrder, $programmed, 'send'))->assertStatus(409);

    Queue::assertNotPushed(SendOutboundEmailJob::class);
});

it('AC-017: unschedule returns a scheduled or ended email to draft, rule kept; it can be reprogrammed', function (OutboundEmailStatus $status) {
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $email = programmedEmail($workOrder, $actor, ['end_reason' => null, 'frequency' => 'daily'], ['status' => $status]);
    Sanctum::actingAs($actor);

    $this->postJson(scheduleUrl($workOrder, $email, 'unschedule'))
        ->assertOk()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.recurrence.frequency', 'daily')
        ->assertJsonPath('data.recurrence.next_send_on', null)
        ->assertJsonPath('data.can.update', true)
        ->assertJsonPath('data.can.delete', true)
        ->assertJsonPath('data.can.send', true)
        ->assertJsonPath('data.can.schedule', true)
        ->assertJsonPath('data.can.unschedule', false);

    $this->patchJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}", ['subject' => 'Nuovo'])->assertOk();

    $rule = recurringRuleInput(['starts_on' => today()->addDays(2)->toDateString(), 'ends_on' => today()->addDays(9)->toDateString(), 'frequency' => 'daily']);
    unset($rule['weekdays']);
    $this->postJson(scheduleUrl($workOrder, $email), ['recurrence' => $rule])
        ->assertOk()
        ->assertJsonPath('data.status', 'scheduled')
        ->assertJsonPath('data.recurrence.next_send_on', $rule['starts_on'])
        ->assertJsonPath('data.recurrence.sent_count', 0);

    expect(OutboundEmailRecurrence::query()->count())->toBe(1);
})->with([OutboundEmailStatus::Scheduled, OutboundEmailStatus::ScheduleEnded]);

it('AC-017: unschedule on a draft is 409 not_scheduled', function () {
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $email = recurringDraft($workOrder, $actor);
    Sanctum::actingAs($actor);

    $this->postJson(scheduleUrl($workOrder, $email, 'unschedule'))
        ->assertStatus(409)
        ->assertJsonPath('message', __('outbound_emails.not_scheduled'));
});

it('AC-018: deleting a draft with a rule removes the rule; sent copies stay', function () {
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $email = programmedEmail($workOrder, $actor);
    $recurrence = $email->recurrence;
    $copy = OutboundEmail::factory()->forEmailable($workOrder)->sent()->create(['sender_user_id' => $actor->id]);
    $copy->forceFill(['outbound_email_recurrence_id' => $recurrence->id, 'occurrence_on' => today()->toDateString()])->save();
    Sanctum::actingAs($actor);

    $this->postJson(scheduleUrl($workOrder, $email, 'unschedule'))->assertOk();
    $this->deleteJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}")->assertNoContent();

    expect(OutboundEmail::query()->find($email->id))->toBeNull()
        ->and(OutboundEmailRecurrence::query()->count())->toBe(0)
        ->and($copy->fresh()->outbound_email_recurrence_id)->toBeNull();
});

it('AC-019: list and detail show scheduled emails to anyone with viewEmails, with recurrence data', function () {
    $workOrder = WorkOrder::factory()->create();
    $author = workOrderEmailActor($workOrder, ['sendEmail']);
    $programmed = programmedEmail($workOrder, $author, ['next_send_on' => '2026-12-14']);
    $copy = OutboundEmail::factory()->forEmailable($workOrder)->sent()->create(['sender_user_id' => $author->id]);
    $copy->forceFill(['outbound_email_recurrence_id' => $programmed->recurrence->id, 'occurrence_on' => today()->toDateString()])->save();
    Attachment::factory()->for($programmed, 'attachable')->create(['collection' => OutboundEmail::ATTACHMENT_COLLECTION]);
    Sanctum::actingAs(workOrderEmailActor($workOrder, ['viewEmails']));

    $rows = collect($this->getJson("/api/work-orders/{$workOrder->id}/emails")->assertOk()->json('data'))->keyBy('id');
    expect($rows[$programmed->id]['status'])->toBe('scheduled')
        ->and($rows[$programmed->id]['next_send_on'])->toBe('2026-12-14')
        ->and($rows[$programmed->id]['is_recurring_copy'])->toBeFalse()
        ->and($rows[$copy->id]['is_recurring_copy'])->toBeTrue()
        ->and($rows[$copy->id]['next_send_on'])->toBeNull();

    $this->getJson("/api/work-orders/{$workOrder->id}/emails/{$programmed->id}")
        ->assertOk()
        ->assertJsonPath('data.recurrence.next_send_on', '2026-12-14')
        ->assertJsonPath('data.recurrence_source_id', null)
        ->assertJsonPath('data.can.unschedule', false)
        ->assertJsonPath('data.can.schedule', false);

    $this->getJson("/api/work-orders/{$workOrder->id}/emails/{$copy->id}")
        ->assertOk()
        ->assertJsonPath('data.recurrence_source_id', $programmed->id)
        ->assertJsonPath('data.recurrence', null);
});

it('AC-019: can.schedule is true on an own draft of an active work order', function () {
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail', 'viewEmails']);
    $email = recurringDraft($workOrder, $actor);
    Sanctum::actingAs($actor);

    $this->getJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}")
        ->assertOk()
        ->assertJsonPath('data.can.schedule', true)
        ->assertJsonPath('data.recurrence', null);
});

it('AC-020: invoices and purchase orders never allow recurrence and expose no schedule route', function () {
    $owners = app(EmailOwnerRegistry::class);
    $invoice = Invoice::factory()->create();
    $purchaseOrder = PurchaseOrder::factory()->create();

    expect($owners->for($invoice)->allowsRecurrence($invoice))->toBeFalse()
        ->and($owners->for($purchaseOrder)->allowsRecurrence($purchaseOrder))->toBeFalse();

    $scheduleRoutes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route) => str_ends_with($route->uri(), '/schedule') || str_ends_with($route->uri(), '/unschedule'))
        ->map(fn ($route) => $route->uri())
        ->values()->all();

    expect($scheduleRoutes)->toBe([
        'api/work-orders/{workOrder}/emails/{email}/schedule',
        'api/work-orders/{workOrder}/emails/{email}/unschedule',
    ]);
});
