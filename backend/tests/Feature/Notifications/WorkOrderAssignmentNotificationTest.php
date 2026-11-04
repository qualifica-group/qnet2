<?php

use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\RecordAssignmentNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0351 — whoever is ADDED to a Commessa as Responsabile or Partecipante
// is notified. AC-001 -> AC-005, AC-007 (AC-006, the silent legacy import,
// is asserted by WorkOrdersSourceImportTest).

uses(RefreshDatabase::class);

if (! function_exists('workOrderAssignmentTitles')) {
    /**
     * The titles of every assignment notification $user received, in send order.
     *
     * @return array<int, string>
     */
    function workOrderAssignmentTitles(User $user): array
    {
        return Notification::sent($user, RecordAssignmentNotification::class)
            ->map(fn (RecordAssignmentNotification $notification): string => (string) $notification->toArray($user)['title'])
            ->all();
    }
}

beforeEach(function () {
    Notification::fake();
    $this->actor = workOrderUserWith(['create', 'update', 'view']);
    Sanctum::actingAs($this->actor);
});

it('notifies every Responsabile and Partecipante of a new commessa, never the actor (AC-001)', function () {
    $quote = Quote::factory()->create();
    $responsible = User::factory()->create();
    $participant = User::factory()->create();

    $this->postJson('/api/work-orders', [
        'quote_id' => $quote->id, 'title' => 'Nuova', 'type' => 'processing', 'start_date' => '2026-11-04',
        'supervisor_ids' => [$responsible->id, $this->actor->id],
        'participant_slots' => [$participant->id, $this->actor->id],
    ])->assertCreated();

    expect(workOrderAssignmentTitles($responsible))->toBe([__('You were assigned as Work Order Supervisor')])
        ->and(workOrderAssignmentTitles($participant))->toBe([__('You were assigned as Work Order Participant')]);

    Notification::assertNotSentTo($this->actor, RecordAssignmentNotification::class);
});

it('notifies only who just entered the team on update (AC-002)', function () {
    $workOrder = WorkOrder::factory()->create();
    $keptResponsible = User::factory()->create();
    $keptParticipant = User::factory()->create();
    $newResponsible = User::factory()->create();
    $newParticipant = User::factory()->create();
    $workOrder->supervisors()->attach($keptResponsible->id);
    $workOrder->participants()->attach($keptParticipant->id, ['position' => 1]);

    $this->patchJson("/api/work-orders/{$workOrder->id}", [
        'supervisor_ids' => [$keptResponsible->id, $newResponsible->id],
        'participant_slots' => [$keptParticipant->id, $newParticipant->id],
    ])->assertOk();

    expect(workOrderAssignmentTitles($newResponsible))->toBe([__('You were assigned as Work Order Supervisor')])
        ->and(workOrderAssignmentTitles($newParticipant))->toBe([__('You were assigned as Work Order Participant')]);

    Notification::assertNotSentTo($keptResponsible, RecordAssignmentNotification::class);
    Notification::assertNotSentTo($keptParticipant, RecordAssignmentNotification::class);
});

it('stays silent when a Partecipante only changes slot or someone is removed (AC-003)', function () {
    $workOrder = WorkOrder::factory()->create();
    $first = User::factory()->create();
    $second = User::factory()->create();
    $removed = User::factory()->create();
    $workOrder->supervisors()->attach($removed->id);
    $workOrder->participants()->attach($first->id, ['position' => 1]);
    $workOrder->participants()->attach($second->id, ['position' => 2]);

    $this->patchJson("/api/work-orders/{$workOrder->id}", [
        'supervisor_ids' => [],
        'participant_slots' => [$second->id, $first->id],
    ])->assertOk();

    Notification::assertNothingSent();
});

it('sends one notification per role to a user who is both Responsabile and Partecipante (AC-004)', function () {
    $quote = Quote::factory()->create();
    $member = User::factory()->create();

    $this->postJson('/api/work-orders', [
        'quote_id' => $quote->id, 'title' => 'Doppio ruolo', 'type' => 'processing', 'start_date' => '2026-11-04',
        'supervisor_ids' => [$member->id],
        'participant_slots' => [$member->id],
    ])->assertCreated();

    expect(workOrderAssignmentTitles($member))->toBe([
        __('You were assigned as Work Order Supervisor'),
        __('You were assigned as Work Order Participant'),
    ]);
});

it('notifies nobody when the create is rejected inside the transaction (AC-005)', function () {
    $quote = Quote::factory()->create();

    $this->postJson('/api/work-orders', [
        'quote_id' => $quote->id, 'title' => 'Rollback', 'type' => 'processing', 'start_date' => '2026-11-04',
        'quote_line_ids' => [QuoteLine::factory()->create()->id],
        'supervisor_ids' => [User::factory()->create()->id],
        'participant_slots' => [User::factory()->create()->id],
    ])->assertStatus(422);

    Notification::assertNothingSent();
});

it('names the commessa by code and title and links it only for who may view work orders (AC-007)', function () {
    Permission::findOrCreate('work-orders.view');
    $quote = Quote::factory()->create();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('work-orders.view');
    $blind = User::factory()->create();

    $id = $this->postJson('/api/work-orders', [
        'quote_id' => $quote->id, 'title' => 'Corso sicurezza', 'type' => 'processing', 'start_date' => '2026-11-04',
        'supervisor_ids' => [$viewer->id],
        'participant_slots' => [$blind->id],
    ])->assertCreated()->json('data.id');
    $label = WorkOrder::query()->findOrFail($id)->code.' - Corso sicurezza';

    Notification::assertSentTo($viewer, function (RecordAssignmentNotification $notification) use ($viewer, $id, $label): bool {
        $payload = $notification->toArray($viewer);
        $mail = $notification->toMail($viewer);

        return $payload['action_url'] === "/work-orders/{$id}"
            && $payload['message'] === __(':actor assigned you as Supervisor of the work order :label.', ['actor' => $this->actor->name, 'label' => $label])
            && $mail->actionUrl !== null
            && str_contains(implode("\n", array_map('strval', $mail->introLines)), 'Corso sicurezza');
    });

    Notification::assertSentTo($blind, function (RecordAssignmentNotification $notification) use ($blind): bool {
        $payload = $notification->toArray($blind);

        return $payload['action_url'] === null
            && str_contains((string) $payload['message'], __('You cannot open this record: ask an administrator to grant you access to the module.'));
    });
});
