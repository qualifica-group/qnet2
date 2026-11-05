<?php

use App\Models\WorkOrderChatMessage;
use App\Models\WorkOrderChatRead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/../../Helpers/WorkOrderChatHelpers.php';

uses(RefreshDatabase::class);

it('AC-012: counts only other people live messages and the read marker clears them without going back', function (): void {
    $workOrder = chatWorkOrder();
    $me = chatMember($workOrder);
    $colleague = chatMember($workOrder, 'participants');
    $theirs = WorkOrderChatMessage::factory()->count(3)->create(['work_order_id' => $workOrder->id, 'user_id' => $colleague->id]);
    $mine = WorkOrderChatMessage::factory()->create(['work_order_id' => $workOrder->id, 'user_id' => $me->id]);
    $deleted = WorkOrderChatMessage::factory()->create(['work_order_id' => $workOrder->id, 'user_id' => $colleague->id]);
    $deleted->delete();
    Sanctum::actingAs($me);

    $this->getJson(chatUrl($workOrder))->assertJsonPath('data.unread_count', 3)->assertJsonPath('data.last_read_message_id', null);

    $this->postJson(chatUrl($workOrder, 'read'), ['last_message_id' => $mine->id])
        ->assertOk()
        ->assertJsonPath('data.last_read_message_id', $mine->id)
        ->assertJsonPath('data.unread_count', 0);

    $newer = WorkOrderChatMessage::factory()->create(['work_order_id' => $workOrder->id, 'user_id' => $colleague->id]);
    $this->getJson(chatUrl($workOrder))->assertJsonPath('data.unread_count', 1);

    $this->postJson(chatUrl($workOrder, 'read'), ['last_message_id' => $theirs->first()->id])
        ->assertOk()
        ->assertJsonPath('data.last_read_message_id', $mine->id)
        ->assertJsonPath('data.unread_count', 1);

    $this->postJson(chatUrl($workOrder, 'read'), ['last_message_id' => $newer->id])->assertJsonPath('data.unread_count', 0);
    $this->getJson(chatUrl($workOrder))
        ->assertJsonPath('data.unread_count', 0)
        ->assertJsonPath('data.last_read_message_id', $newer->id);
    expect(WorkOrderChatRead::count())->toBe(1);
});

it('AC-012: the read marker is per user', function (): void {
    $workOrder = chatWorkOrder();
    $me = chatMember($workOrder);
    $colleague = chatMember($workOrder, 'participants');
    $message = WorkOrderChatMessage::factory()->create(['work_order_id' => $workOrder->id, 'user_id' => $colleague->id]);

    Sanctum::actingAs($me);
    $this->postJson(chatUrl($workOrder, 'read'), ['last_message_id' => $message->id])->assertOk();

    Sanctum::actingAs($colleague);
    $this->getJson(chatUrl($workOrder))->assertJsonPath('data.last_read_message_id', null);
});

it('AC-012: read rejects a message of another work order or a missing one with 422', function (): void {
    $workOrder = chatWorkOrder();
    $foreign = WorkOrderChatMessage::factory()->create(['work_order_id' => chatWorkOrder()->id]);
    Sanctum::actingAs(chatMember($workOrder));

    $this->postJson(chatUrl($workOrder, 'read'), ['last_message_id' => $foreign->id])->assertUnprocessable();
    $this->postJson(chatUrl($workOrder, 'read'), ['last_message_id' => 999999])->assertUnprocessable();
    $this->postJson(chatUrl($workOrder, 'read'), [])->assertUnprocessable();
    expect(WorkOrderChatRead::count())->toBe(0);
});

it('paginates backwards with before_id and has_more', function (): void {
    $workOrder = chatWorkOrder();
    Sanctum::actingAs(chatMember($workOrder));
    $messages = WorkOrderChatMessage::factory()->count(5)->create(['work_order_id' => $workOrder->id]);

    $page = $this->getJson(chatUrl($workOrder).'?limit=2')->assertOk()
        ->assertJsonPath('data.has_more', true)
        ->assertJsonCount(2, 'data.messages');
    expect($page->json('data.messages.*.id'))->toBe([$messages[3]->id, $messages[4]->id]);

    $older = $this->getJson(chatUrl($workOrder)."?limit=2&before_id={$messages[3]->id}")->assertOk()->assertJsonPath('data.has_more', true);
    expect($older->json('data.messages.*.id'))->toBe([$messages[1]->id, $messages[2]->id]);

    $this->getJson(chatUrl($workOrder)."?limit=2&before_id={$messages[1]->id}")
        ->assertJsonPath('data.has_more', false)
        ->assertJsonPath('data.messages.0.id', $messages[0]->id);
});

it('rejects an invalid limit or before_id with 422', function (array $query): void {
    $workOrder = chatWorkOrder();
    Sanctum::actingAs(chatMember($workOrder));

    $this->getJson(chatUrl($workOrder).'?'.http_build_query($query))->assertUnprocessable();
})->with([
    'limit zero' => [['limit' => 0]],
    'limit over max' => [['limit' => 101]],
    'before_id text' => [['before_id' => 'abc']],
]);

it('returns the exact ChatMessage shape', function (): void {
    $workOrder = chatWorkOrder();
    $member = chatMember($workOrder);
    WorkOrderChatMessage::factory()->create(['work_order_id' => $workOrder->id, 'user_id' => $member->id]);
    Sanctum::actingAs($member);

    $response = $this->getJson(chatUrl($workOrder))->assertOk();

    expect(array_keys($response->json('data')))->toBe(['messages', 'has_more', 'can_write', 'can_moderate', 'unread_count', 'last_read_message_id', 'channel'])
        ->and(array_keys($response->json('data.messages.0')))->toBe(['id', 'work_order_id', 'author', 'body', 'is_deleted', 'edited_at', 'created_at', 'editable_until'])
        ->and(array_keys($response->json('data.messages.0.author')))->toBe(['id', 'name', 'avatar_url']);

    $createdAt = Carbon\Carbon::parse($response->json('data.messages.0.created_at'));
    expect(Carbon\Carbon::parse($response->json('data.messages.0.editable_until'))->equalTo($createdAt->copy()->addMinutes(15)))->toBeTrue();
});

it('shows a null author when the user was removed', function (): void {
    $workOrder = chatWorkOrder();
    $removed = chatMember($workOrder, 'participants');
    WorkOrderChatMessage::factory()->create(['work_order_id' => $workOrder->id, 'user_id' => $removed->id]);
    $removed->delete();
    Sanctum::actingAs(chatMember($workOrder));

    $this->getJson(chatUrl($workOrder))->assertOk()->assertJsonPath('data.messages.0.author', null)->assertJsonPath('data.unread_count', 1);
});
