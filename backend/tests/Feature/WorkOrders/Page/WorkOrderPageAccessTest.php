<?php

use App\Enums\WorkOrderType;
use App\Models\User;
use App\Models\WorkOrderPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/../../../Helpers/WorkOrderChatHelpers.php';
require_once __DIR__.'/../../../Helpers/WorkOrderPageHelpers.php';

uses(RefreshDatabase::class);

it('AC-002: a work order without a page returns null content, version 0 and empty maps', function (): void {
    $workOrder = chatWorkOrder();
    Sanctum::actingAs(chatMember($workOrder));

    $response = $this->getJson(pageUrl($workOrder))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.content', null)
        ->assertJsonPath('data.version', 0)
        ->assertJsonPath('data.updated_by', null)
        ->assertJsonPath('data.can_edit', true);

    expect($response->getContent())->toContain('"tasks":{}')->toContain('"polls":{}');
    expect(WorkOrderPage::count())->toBe(0);
});

it('AC-003: outsider 403, non project 404, no view permission 403, on every endpoint', function (): void {
    $workOrder = chatWorkOrder();
    $poll = pageFor($workOrder)->polls()->create(['question' => 'Q?', 'options' => [['key' => 'o1', 'label' => 'A'], ['key' => 'o2', 'label' => 'B']]]);
    $outsider = chatOutsider();
    $superAdmin = chatSuperAdmin();

    Sanctum::actingAs($outsider);
    $this->getJson(pageUrl($workOrder))->assertForbidden();
    $this->putJson(pageUrl($workOrder), ['content' => '<p>x</p>', 'version' => 0])->assertForbidden();
    $this->postJson(pageUrl($workOrder, 'polls'), ['question' => 'Q', 'options' => ['a', 'b']])->assertForbidden();
    $this->putJson(pageUrl($workOrder, "polls/{$poll->id}/vote"), ['option_key' => 'o1'])->assertForbidden();
    $this->deleteJson(pageUrl($workOrder, "polls/{$poll->id}/vote"))->assertForbidden();

    $processing = chatWorkOrder(['type' => WorkOrderType::Processing]);
    Sanctum::actingAs($superAdmin);
    $this->getJson(pageUrl($processing))->assertNotFound();
    $this->putJson(pageUrl($processing), ['content' => null, 'version' => 0])->assertNotFound();

    $noView = User::factory()->create();
    $workOrder->supervisors()->attach($noView->id);
    Sanctum::actingAs($noView);
    $this->getJson(pageUrl($workOrder))->assertForbidden();
});

it('lets a super-admin outside the team read and write', function (): void {
    $workOrder = chatWorkOrder();
    Sanctum::actingAs(chatSuperAdmin());

    $this->getJson(pageUrl($workOrder))->assertOk();
    $this->putJson(pageUrl($workOrder), ['content' => '<p>Hi</p>', 'version' => 0])->assertOk();
});

it('AC-006: a closed or completed work order is read-only: 409 on PUT, POST poll and vote', function (string $how): void {
    $workOrder = chatWorkOrder();
    $member = chatMember($workOrder);
    $poll = pageFor($workOrder)->polls()->create(['question' => 'Q?', 'options' => [['key' => 'o1', 'label' => 'A'], ['key' => 'o2', 'label' => 'B']]]);
    $how === 'closed' ? chatClose($workOrder) : chatComplete($workOrder);
    Sanctum::actingAs($member);

    $this->getJson(pageUrl($workOrder))->assertOk()->assertJsonPath('data.can_edit', false);
    $this->putJson(pageUrl($workOrder), ['content' => '<p>x</p>', 'version' => 0])->assertStatus(409);
    $this->postJson(pageUrl($workOrder, 'polls'), ['question' => 'Q', 'options' => ['a', 'b']])->assertStatus(409);
    $this->putJson(pageUrl($workOrder, "polls/{$poll->id}/vote"), ['option_key' => 'o1'])->assertStatus(409);
    $this->deleteJson(pageUrl($workOrder, "polls/{$poll->id}/vote"))->assertStatus(409);
})->with([
    'force closed' => 'closed',
    'completed' => 'completed',
]);

it('exposes view_page and edit_page like the chat actions (spec 0420 D-2, new requirement)', function (): void {
    $workOrder = chatWorkOrder();
    $outsider = chatOutsider();
    Sanctum::actingAs(chatMember($workOrder));

    $actions = $this->getJson("/api/work-orders/{$workOrder->id}")->assertOk()->json('permissions.actions');
    expect($actions)->toMatchArray(['view_page' => true, 'edit_page' => true]);

    chatClose($workOrder);
    $actions = $this->getJson("/api/work-orders/{$workOrder->id}")->json('permissions.actions');
    expect($actions)->toMatchArray(['view_page' => true, 'edit_page' => false]);

    Sanctum::actingAs($outsider);
    $actions = $this->getJson("/api/work-orders/{$workOrder->id}")->json('permissions.actions');
    expect($actions)->toMatchArray(['view_page' => false, 'edit_page' => false]);
});
