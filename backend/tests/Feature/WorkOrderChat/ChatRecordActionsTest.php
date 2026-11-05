<?php

use App\Enums\WorkOrderType;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/../../Helpers/WorkOrderChatHelpers.php';

uses(RefreshDatabase::class);

function chatActions($test, WorkOrder $workOrder): array
{
    return $test->getJson("/api/work-orders/{$workOrder->id}")->assertOk()->json('permissions.actions');
}

it('exposes view_chat and write_chat true to a team member of a project work order', function (): void {
    $workOrder = chatWorkOrder();
    Sanctum::actingAs(chatMember($workOrder));

    expect(chatActions($this, $workOrder))->toMatchArray(['view_chat' => true, 'write_chat' => true]);
});

it('exposes view_chat true and write_chat false on a closed work order', function (): void {
    $workOrder = chatClose(chatWorkOrder());
    Sanctum::actingAs(chatMember($workOrder));

    expect(chatActions($this, $workOrder))->toMatchArray(['view_chat' => true, 'write_chat' => false]);
});

it('exposes both false to a viewer outside the team and on a processing work order', function (): void {
    $project = chatWorkOrder();
    Sanctum::actingAs(chatOutsider());
    expect(chatActions($this, $project))->toMatchArray(['view_chat' => false, 'write_chat' => false]);

    $processing = chatWorkOrder(['type' => WorkOrderType::Processing]);
    Sanctum::actingAs(chatSuperAdmin());
    expect(chatActions($this, $processing))->toMatchArray(['view_chat' => false, 'write_chat' => false]);
});

it('exposes view_chat and write_chat true to a super-admin on a project work order', function (): void {
    $workOrder = chatWorkOrder();
    Sanctum::actingAs(chatSuperAdmin());

    expect(chatActions($this, $workOrder))->toMatchArray(['view_chat' => true, 'write_chat' => true]);
});
