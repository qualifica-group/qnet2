<?php

use App\Models\WorkOrderChatMessage;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/../../Helpers/WorkOrderChatHelpers.php';

uses(RefreshDatabase::class);

// A Reverb server that is down must not turn a saved message into an error:
// with QUEUE_CONNECTION=sync the broadcast runs inside the request.
it('keeps the write successful when the realtime server is unreachable', function (): void {
    config([
        'queue.default' => 'sync',
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
        'broadcasting.connections.reverb.options.host' => '127.0.0.1',
        'broadcasting.connections.reverb.options.port' => 1,
        'broadcasting.connections.reverb.options.scheme' => 'http',
        'broadcasting.connections.reverb.options.useTLS' => false,
    ]);
    app(BroadcastManager::class)->purge('reverb');

    $workOrder = chatWorkOrder();
    $member = chatMember($workOrder);
    Sanctum::actingAs($member);

    $this->postJson(chatUrl($workOrder), ['body' => 'Hello team'])
        ->assertCreated()
        ->assertJsonPath('success', true);

    expect(WorkOrderChatMessage::query()->where('work_order_id', $workOrder->id)->count())->toBe(1);
});
