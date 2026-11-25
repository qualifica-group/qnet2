<?php

use App\Broadcasting\WorkOrderChatChannel;
use App\Models\WorkOrderChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/../../Helpers/WorkOrderChatHelpers.php';
require_once __DIR__.'/../../Helpers/ProjectWorkOrderHelpers.php';

uses(RefreshDatabase::class);

/*
| CHAT_ENABLED (config/chat.php, spec 0432): the project chat switched off for
| the whole installation. On by default, so nothing changes until it is off.
*/

it('is on by default and says so in the public config', function (): void {
    expect(config('chat.enabled'))->toBeTrue();

    $this->getJson('/api/config')->assertOk()->assertJsonPath('data.features.chat', true);
});

it('tells the client when it is off', function (): void {
    config(['chat.enabled' => false]);

    $this->getJson('/api/config')->assertOk()->assertJsonPath('data.features.chat', false);
});

it('answers 404 on every chat endpoint when off', function (): void {
    config(['chat.enabled' => false]);
    $workOrder = chatWorkOrder();
    $message = WorkOrderChatMessage::factory()->create(['work_order_id' => $workOrder->id]);
    Sanctum::actingAs(chatMember($workOrder));

    $this->getJson('/api/work-order-chat/channels')->assertNotFound()->assertJsonPath('success', false);
    $this->getJson(chatUrl($workOrder))->assertNotFound();
    $this->postJson(chatUrl($workOrder), ['body' => 'hello'])->assertNotFound();
    $this->postJson(chatUrl($workOrder, 'read'), ['last_message_id' => $message->id])->assertNotFound();
    expect(WorkOrderChatMessage::count())->toBe(1);
});

it('gives no chat action nor channel access to a team member when off, keeping the workspace notes', function (): void {
    config(['chat.enabled' => false]);
    $workOrder = chatWorkOrder();
    $member = chatMember($workOrder);
    Sanctum::actingAs($member);

    expect($this->getJson("/api/work-orders/{$workOrder->id}")->assertOk()->json('permissions.actions'))
        ->toMatchArray(['view_chat' => false, 'write_chat' => false, 'view_page' => true, 'edit_page' => true]);
    expect(app(WorkOrderChatChannel::class)->join($member, $workOrder))->toBeFalse();
    $this->getJson("/api/work-orders/{$workOrder->id}/page")->assertOk();
});

it('drops the "Non letti" column of the project work orders when off', function (): void {
    config(['chat.enabled' => false]);
    Sanctum::actingAs(projectWorkOrderViewer());

    $columns = collect($this->getJson('/api/tables/project-work-orders/columns')->assertOk()->json('data.columns'))->pluck('id');

    expect($columns)->not->toContain('chat_unread_count');
});
