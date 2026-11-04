<?php

use App\Enums\WorkOrderType;
use App\Models\WorkOrderChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/../../Helpers/WorkOrderChatHelpers.php';

uses(RefreshDatabase::class);

it('AC-001: a team member reads the messages in ascending order with channel and can_write', function (): void {
    $workOrder = chatWorkOrder();
    $supervisor = chatMember($workOrder);
    $participant = chatMember($workOrder, 'participants');
    $first = WorkOrderChatMessage::factory()->create(['work_order_id' => $workOrder->id, 'user_id' => $participant->id]);
    $second = WorkOrderChatMessage::factory()->create(['work_order_id' => $workOrder->id, 'user_id' => $supervisor->id]);

    foreach ([$supervisor, $participant] as $member) {
        Sanctum::actingAs($member);

        $this->getJson(chatUrl($workOrder))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.channel', "work-orders.{$workOrder->id}.chat")
            ->assertJsonPath('data.can_write', true)
            ->assertJsonPath('data.can_moderate', false)
            ->assertJsonPath('data.has_more', false)
            ->assertJsonPath('data.messages.0.id', $first->id)
            ->assertJsonPath('data.messages.1.id', $second->id);
    }
});

it('AC-002: a super-admin outside the team reads and writes', function (): void {
    $workOrder = chatWorkOrder();
    Sanctum::actingAs(chatSuperAdmin());

    $this->getJson(chatUrl($workOrder))->assertOk()->assertJsonPath('data.can_moderate', true);
    $this->postJson(chatUrl($workOrder), ['body' => 'Admin here'])->assertCreated();
});

it('AC-003: a user who sees the work order without being in the team gets 403 everywhere and writes nothing', function (): void {
    $workOrder = chatWorkOrder();
    $message = WorkOrderChatMessage::factory()->create(['work_order_id' => $workOrder->id]);
    Sanctum::actingAs(chatOutsider());

    $this->getJson(chatUrl($workOrder))->assertForbidden();
    $this->postJson(chatUrl($workOrder), ['body' => 'Hi'])->assertForbidden();
    $this->patchJson(chatUrl($workOrder, "messages/{$message->id}"), ['body' => 'Edit'])->assertForbidden();
    $this->deleteJson(chatUrl($workOrder, "messages/{$message->id}"))->assertForbidden();
    $this->postJson(chatUrl($workOrder, 'read'), ['last_message_id' => $message->id])->assertForbidden();

    expect(WorkOrderChatMessage::count())->toBe(1)
        ->and($message->fresh()->body)->toBe($message->body);
});

it('answers 403 before 422 to an outsider sending an invalid body', function (): void {
    Sanctum::actingAs(chatOutsider());

    $this->postJson(chatUrl(chatWorkOrder()), ['body' => ''])->assertForbidden();
});

it('answers 404 before 422 on a processing work order and 422 before 409 on a closed one', function (): void {
    $processing = chatWorkOrder(['type' => WorkOrderType::Processing]);
    Sanctum::actingAs(chatSuperAdmin());
    $this->postJson(chatUrl($processing), ['body' => ''])->assertNotFound()->assertJsonPath('success', false);

    $closed = chatClose(chatWorkOrder());
    $this->postJson(chatUrl($closed), ['body' => ''])->assertUnprocessable();
});

it('answers 403 to a team member who lacks the work-orders.view permission', function (): void {
    $workOrder = chatWorkOrder();
    $member = chatMember($workOrder);
    $member->revokePermissionTo('work-orders.view');
    Sanctum::actingAs($member);

    $this->getJson(chatUrl($workOrder))->assertForbidden();
});

it('answers 404 for an unknown work order', function (): void {
    Sanctum::actingAs(chatSuperAdmin());

    $this->getJson('/api/work-orders/999999/chat/messages')->assertNotFound();
});

it('AC-004: a processing work order has no chat, and it reappears intact when project again', function (): void {
    $workOrder = chatWorkOrder();
    $member = chatMember($workOrder);
    $message = WorkOrderChatMessage::factory()->create(['work_order_id' => $workOrder->id, 'user_id' => $member->id]);
    Sanctum::actingAs($member);

    $workOrder->forceFill(['type' => WorkOrderType::Processing])->save();

    $this->getJson(chatUrl($workOrder))->assertNotFound()->assertJsonPath('success', false);
    $this->postJson(chatUrl($workOrder), ['body' => 'Hi'])->assertNotFound()->assertJsonPath('success', false);
    $this->patchJson(chatUrl($workOrder, "messages/{$message->id}"), ['body' => 'Hi'])->assertNotFound();
    $this->deleteJson(chatUrl($workOrder, "messages/{$message->id}"))->assertNotFound();
    $this->postJson(chatUrl($workOrder, 'read'), ['last_message_id' => $message->id])->assertNotFound();
    expect(WorkOrderChatMessage::count())->toBe(1);

    $workOrder->forceFill(['type' => WorkOrderType::Project])->save();

    $this->getJson(chatUrl($workOrder))->assertOk()->assertJsonPath('data.messages.0.id', $message->id);
});

it('AC-011: a force closed or completed work order is read only with 409 on every write', function (string $close): void {
    $workOrder = chatWorkOrder();
    $member = chatMember($workOrder);
    $message = WorkOrderChatMessage::factory()->create(['work_order_id' => $workOrder->id, 'user_id' => $member->id]);
    $close === 'closed' ? chatClose($workOrder) : chatComplete($workOrder);
    Sanctum::actingAs($member);

    $this->getJson(chatUrl($workOrder))->assertOk()->assertJsonPath('data.can_write', false);
    $this->postJson(chatUrl($workOrder), ['body' => 'Hi'])->assertStatus(409)->assertJsonPath('success', false);
    $this->patchJson(chatUrl($workOrder, "messages/{$message->id}"), ['body' => 'Hi'])->assertStatus(409)->assertJsonPath('success', false);
    $this->deleteJson(chatUrl($workOrder, "messages/{$message->id}"))->assertStatus(409)->assertJsonPath('success', false);
    expect($message->fresh()->body)->toBe($message->body);
})->with([
    'force closed' => 'closed',
    'completed' => 'completed',
]);

it('answers 401 without authentication', function (): void {
    $this->getJson(chatUrl(chatWorkOrder()))->assertUnauthorized();
});
