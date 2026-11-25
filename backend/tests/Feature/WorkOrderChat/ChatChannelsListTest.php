<?php

use App\Enums\WorkOrderType;
use App\Http\Resources\WorkOrderChatChannelResource;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChatMessage;
use App\Models\WorkOrderChatRead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/../../Helpers/WorkOrderChatHelpers.php';

uses(RefreshDatabase::class);

const CHAT_CHANNELS_URL = '/api/work-order-chat/channels';

it('AC-001: lists only the project work orders where the actor is supervisor or participant', function (): void {
    $supervised = chatWorkOrder();
    $me = chatMember($supervised);
    $participated = chatWorkOrder();
    $participated->participants()->attach($me->id, ['position' => 1]);
    $outsideTeam = chatWorkOrder();
    $processing = WorkOrder::factory()->create(['type' => WorkOrderType::Processing]);
    $processing->supervisors()->attach($me->id);
    Sanctum::actingAs($me);

    $ids = collect($this->getJson(CHAT_CHANNELS_URL)->assertOk()->json('data'))->pluck('work_order_id');

    expect($ids->sort()->values()->all())->toBe(collect([$supervised->id, $participated->id])->sort()->values()->all())
        ->and($ids)->not->toContain($outsideTeam->id)
        ->and($ids)->not->toContain($processing->id);
});

it('AC-001: a super-admin outside every team gets no channel', function (): void {
    chatWorkOrder();
    Sanctum::actingAs(chatSuperAdmin());

    $this->getJson(CHAT_CHANNELS_URL)->assertOk()->assertJsonPath('data', []);
});

it('AC-002: is empty without the project work orders viewing permission', function (): void {
    $workOrder = chatWorkOrder();
    $member = User::factory()->create();
    $workOrder->supervisors()->attach($member->id);
    Sanctum::actingAs($member);

    $this->getJson(CHAT_CHANNELS_URL)->assertOk()->assertJsonPath('data', []);
});

it('requires authentication', function (): void {
    $this->getJson(CHAT_CHANNELS_URL)->assertUnauthorized();
});

it('AC-003: counts other people live messages after the read mark', function (): void {
    $workOrder = chatWorkOrder();
    $me = chatMember($workOrder);
    $colleague = chatMember($workOrder, 'participants');
    $first = WorkOrderChatMessage::factory()->create(['work_order_id' => $workOrder->id, 'user_id' => $colleague->id]);
    WorkOrderChatMessage::factory()->count(2)->create(['work_order_id' => $workOrder->id, 'user_id' => $colleague->id]);
    WorkOrderChatMessage::factory()->create(['work_order_id' => $workOrder->id, 'user_id' => $me->id]);
    WorkOrderChatMessage::factory()->create(['work_order_id' => $workOrder->id, 'user_id' => $colleague->id])->delete();
    WorkOrderChatRead::factory()->create(['work_order_id' => $workOrder->id, 'user_id' => $me->id, 'last_read_message_id' => $first->id]);
    Sanctum::actingAs($me);

    $this->getJson(CHAT_CHANNELS_URL)->assertJsonPath('data.0.unread_count', 2);
});

it('AC-004: carries the last message, deleted ones without preview, newest channel first', function (): void {
    $quiet = chatWorkOrder(['title' => 'Quiet']);
    $me = chatMember($quiet);
    $busy = chatWorkOrder(['title' => 'Busy']);
    $busy->supervisors()->attach($me->id);
    $deletedLast = chatWorkOrder(['title' => 'Deleted last']);
    $deletedLast->supervisors()->attach($me->id);
    $author = chatMember($busy, 'participants');
    WorkOrderChatMessage::factory()->create(['work_order_id' => $deletedLast->id, 'user_id' => $author->id])->delete();
    WorkOrderChatMessage::factory()->create(['work_order_id' => $busy->id, 'user_id' => $author->id, 'body' => str_repeat('a', 300)]);
    Sanctum::actingAs($me);

    $data = $this->getJson(CHAT_CHANNELS_URL)->assertOk()->json('data');

    expect(array_column($data, 'title'))->toBe(['Busy', 'Deleted last', 'Quiet'])
        ->and($data[0]['last_message']['author_name'])->toBe($author->name)
        ->and(mb_strlen($data[0]['last_message']['body_preview']))->toBeLessThanOrEqual(WorkOrderChatChannelResource::PREVIEW_LENGTH + 3)
        ->and($data[0]['last_message']['is_deleted'])->toBeFalse()
        ->and($data[0]['channel'])->toBe("work-orders.{$busy->id}.chat")
        ->and($data[1]['last_message']['body_preview'])->toBeNull()
        ->and($data[1]['last_message']['is_deleted'])->toBeTrue()
        ->and($data[2]['last_message'])->toBeNull();
});

it('AC-005: can_write is false on a closed or completed work order', function (): void {
    $open = chatWorkOrder(['title' => 'Open']);
    $me = chatMember($open);
    $closed = chatClose(chatWorkOrder(['title' => 'Closed']));
    $closed->supervisors()->attach($me->id);
    Sanctum::actingAs($me);

    $byTitle = collect($this->getJson(CHAT_CHANNELS_URL)->json('data'))->keyBy('title');

    expect($byTitle['Open']['can_write'])->toBeTrue()
        ->and($byTitle['Closed']['can_write'])->toBeFalse();
});
