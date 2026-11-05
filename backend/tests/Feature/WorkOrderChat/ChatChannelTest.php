<?php

use App\Broadcasting\WorkOrderChatChannel;
use App\Enums\WorkOrderType;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/../../Helpers/WorkOrderChatHelpers.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // phpunit.xml pins BROADCAST_CONNECTION=null, whose auth() accepts anything:
    // switch to a signing driver and re-register the channel rules on it.
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
        'broadcasting.connections.reverb.options.host' => 'localhost',
        'broadcasting.connections.reverb.options.scheme' => 'http',
    ]);
    app(BroadcastManager::class)->purge('reverb');
    require base_path('routes/channels.php');
});

function chatAuth($test, int $workOrderId)
{
    return $test->postJson('/api/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "private-work-orders.{$workOrderId}.chat",
    ]);
}

it('generates the channel name from one place, from a model or an id', function (): void {
    $workOrder = chatWorkOrder();

    expect(WorkOrderChatChannel::name($workOrder))->toBe("work-orders.{$workOrder->id}.chat")
        ->and(WorkOrderChatChannel::name(42))->toBe('work-orders.42.chat');
});

it('AC-013: a team member is authorized and gets a signature', function (): void {
    $workOrder = chatWorkOrder();
    Sanctum::actingAs(chatMember($workOrder, 'participants'));

    chatAuth($this, $workOrder->id)->assertOk()->assertJsonStructure(['auth']);
});

it('AC-013: a super-admin is authorized', function (): void {
    Sanctum::actingAs(chatSuperAdmin());

    chatAuth($this, chatWorkOrder()->id)->assertOk();
});

it('AC-013: a user outside the team gets 403', function (): void {
    $workOrder = chatWorkOrder();
    Sanctum::actingAs(chatOutsider());

    chatAuth($this, $workOrder->id)->assertForbidden();
});

it('AC-013: a non project work order or a missing one gets 403', function (): void {
    $workOrder = chatWorkOrder();
    $member = chatMember($workOrder);
    Sanctum::actingAs($member);
    $workOrder->forceFill(['type' => WorkOrderType::Processing])->save();

    chatAuth($this, $workOrder->id)->assertForbidden();
    chatAuth($this, 999999)->assertForbidden();
});
