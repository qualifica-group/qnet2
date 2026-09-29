<?php

use App\Models\OutboundEmail;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| Authorization + draft privacy (spec 0175, AC-006, AC-007)
|--------------------------------------------------------------------------
*/

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-006
// ---------------------------------------------------------------------------

it('AC-006: list is 403 without work-orders.viewEmails', function () {
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, []);
    Sanctum::actingAs($actor);

    $this->getJson("/api/work-orders/{$workOrder->id}/emails")->assertForbidden();
});

it('AC-006: create/patch/attachments/send are 403 without work-orders.sendEmail', function () {
    Storage::fake('local');
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['viewEmails']);
    $email = OutboundEmail::factory()->forEmailable($workOrder)->create(['sender_user_id' => $actor->id]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/emails", [])->assertForbidden();
    $this->patchJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}", ['subject' => 'x'])->assertForbidden();
    // A validly-shaped payload, so the 403 from authorize() is what actually
    // answers first -- not a 422 from the FormRequest's own field validation,
    // which (like every FormRequest in this app) runs before the controller
    // body even starts.
    $this->postJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}/attachments", [
        'file' => UploadedFile::fake()->create('test.pdf', 10, 'application/pdf'),
    ])->assertForbidden();
    $this->postJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}/send", [])->assertForbidden();
});

it('AC-006: every endpoint is 403 on a commessa outside the actor\'s scope', function () {
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['viewEmails', 'sendEmail'], inScope: false);
    Sanctum::actingAs($actor);

    $this->getJson("/api/work-orders/{$workOrder->id}/emails")->assertForbidden();
    $this->postJson("/api/work-orders/{$workOrder->id}/emails", [])->assertForbidden();
    $this->getJson("/api/work-orders/{$workOrder->id}/emails/compose-context")->assertForbidden();
});

it('AC-006: an {email} belonging to another commessa 404s (scopeBindings-equivalent)', function () {
    $workOrderA = WorkOrder::factory()->create();
    $workOrderB = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrderA, ['viewEmails']);
    workOrderEmailActor($workOrderB, [], inScope: false); // no-op, just to vary ids
    $emailOfB = OutboundEmail::factory()->forEmailable($workOrderB)->sent()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/work-orders/{$workOrderA->id}/emails/{$emailOfB->id}")->assertNotFound();
});

// ---------------------------------------------------------------------------
// AC-007 — draft privacy
// ---------------------------------------------------------------------------

it('AC-007: user A\'s draft does not appear in user B\'s list', function () {
    $workOrder = WorkOrder::factory()->create();
    $userA = workOrderEmailActor($workOrder, ['viewEmails', 'sendEmail']);
    $userB = workOrderEmailActor($workOrder, ['viewEmails', 'sendEmail']);
    $draftOfA = OutboundEmail::factory()->forEmailable($workOrder)->create(['sender_user_id' => $userA->id]);
    Sanctum::actingAs($userB);

    $response = $this->getJson("/api/work-orders/{$workOrder->id}/emails")->assertOk();
    expect(collect($response->json('data'))->pluck('id'))->not->toContain($draftOfA->id);
});

it('AC-007: GET/PATCH/DELETE by user B on user A\'s draft are 404/403 (never found for B)', function () {
    $workOrder = WorkOrder::factory()->create();
    $userA = workOrderEmailActor($workOrder, ['viewEmails', 'sendEmail']);
    $userB = workOrderEmailActor($workOrder, ['viewEmails', 'sendEmail']);
    $draftOfA = OutboundEmail::factory()->forEmailable($workOrder)->create(['sender_user_id' => $userA->id]);
    Sanctum::actingAs($userB);

    $this->getJson("/api/work-orders/{$workOrder->id}/emails/{$draftOfA->id}")->assertNotFound();
    $this->patchJson("/api/work-orders/{$workOrder->id}/emails/{$draftOfA->id}", ['subject' => 'x'])->assertNotFound();
    $this->deleteJson("/api/work-orders/{$workOrder->id}/emails/{$draftOfA->id}")->assertNotFound();
    $this->postJson("/api/work-orders/{$workOrder->id}/emails/{$draftOfA->id}/send")->assertNotFound();
});

it('AC-007: user A\'s SENT email is visible to user B with viewEmails', function () {
    $workOrder = WorkOrder::factory()->create();
    $userA = workOrderEmailActor($workOrder, ['viewEmails', 'sendEmail']);
    $userB = workOrderEmailActor($workOrder, ['viewEmails']);
    $sentOfA = OutboundEmail::factory()->forEmailable($workOrder)->sent()->create(['sender_user_id' => $userA->id]);
    Sanctum::actingAs($userB);

    $this->getJson("/api/work-orders/{$workOrder->id}/emails/{$sentOfA->id}")->assertOk();
    $response = $this->getJson("/api/work-orders/{$workOrder->id}/emails")->assertOk();
    expect(collect($response->json('data'))->pluck('id'))->toContain($sentOfA->id);
});

it('AC-014/D-2: user B cannot resend user A\'s failed email (403 non autore)', function () {
    $workOrder = WorkOrder::factory()->create();
    $userA = workOrderEmailActor($workOrder, ['viewEmails', 'sendEmail']);
    $userB = workOrderEmailActor($workOrder, ['viewEmails', 'sendEmail']);
    $failedOfA = OutboundEmail::factory()->forEmailable($workOrder)->failed()->create([
        'sender_user_id' => $userA->id,
        'to_recipients' => ['dest@example.com'],
        'subject' => 'x',
        'body' => '<p>x</p>',
    ]);
    Sanctum::actingAs($userB);

    $this->postJson("/api/work-orders/{$workOrder->id}/emails/{$failedOfA->id}/send")->assertForbidden();
});
