<?php

use App\Enums\OutboundEmailStatus;
use App\Jobs\SendOutboundEmailJob;
use App\Models\Attachment;
use App\Models\OutboundEmail;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| POST .../emails/{email}/send (spec 0175, D-2/D-12, AC-012, AC-014)
|--------------------------------------------------------------------------
*/

uses(RefreshDatabase::class);

it('AC-012: a valid draft moves to queued, from_address is set, the job is queued', function () {
    Queue::fake();
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $email = OutboundEmail::factory()->forEmailable($workOrder)->create([
        'sender_user_id' => $actor->id,
        'to_recipients' => ['dest@example.com'],
        'subject' => 'Oggetto',
        'body' => '<p>Corpo</p>',
    ]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}/send")
        ->assertStatus(202)
        ->assertJsonPath('data.status', 'queued')
        ->assertJsonPath('data.from_address', $actor->email);

    $email->refresh();
    expect($email->status)->toBe(OutboundEmailStatus::Queued)
        ->and($email->from_address)->toBe($actor->email)
        ->and($email->queued_at)->not->toBeNull();

    Queue::assertPushed(SendOutboundEmailJob::class, fn (SendOutboundEmailJob $job) => true);
});

it('AC-012: no recipients in `to` is 422 and the email stays draft', function () {
    Queue::fake();
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $email = OutboundEmail::factory()->forEmailable($workOrder)->create([
        'sender_user_id' => $actor->id,
        'to_recipients' => [],
        'subject' => 'Oggetto',
        'body' => '<p>Corpo</p>',
    ]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}/send")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to']);

    expect($email->fresh()->status)->toBe(OutboundEmailStatus::Draft);
    Queue::assertNotPushed(SendOutboundEmailJob::class);
});

it('AC-012: an empty subject/body is 422', function () {
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $email = OutboundEmail::factory()->forEmailable($workOrder)->create([
        'sender_user_id' => $actor->id,
        'to_recipients' => ['dest@example.com'],
        'subject' => '',
        'body' => '<p></p>',
    ]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}/send")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['subject', 'body']);
});

it('AC-012: attachments total over the limit is 422 at send time too', function () {
    config(['outbound_emails.max_total_attachments_kb' => 1]);
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $email = OutboundEmail::factory()->forEmailable($workOrder)->create([
        'sender_user_id' => $actor->id,
        'to_recipients' => ['dest@example.com'],
        'subject' => 'x',
        'body' => '<p>x</p>',
    ]);
    Attachment::factory()->for($email, 'attachable')->create([
        'collection' => OutboundEmail::ATTACHMENT_COLLECTION,
        'size' => 2 * 1024 * 1024,
    ]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}/send")
        ->assertUnprocessable();
});

it('AC-012: sender with no email is 422', function () {
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $actor->forceFill(['email' => ''])->saveQuietly();
    $email = OutboundEmail::factory()->forEmailable($workOrder)->create([
        'sender_user_id' => $actor->id,
        'to_recipients' => ['dest@example.com'],
        'subject' => 'x',
        'body' => '<p>x</p>',
    ]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}/send")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sender']);
});

// ---------------------------------------------------------------------------
// AC-014 — resend
// ---------------------------------------------------------------------------

it('AC-014: a failed email can be resent (back to queued)', function () {
    Queue::fake();
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $email = OutboundEmail::factory()->forEmailable($workOrder)->failed()->create([
        'sender_user_id' => $actor->id,
        'to_recipients' => ['dest@example.com'],
        'subject' => 'x',
        'body' => '<p>x</p>',
    ]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}/send")
        ->assertStatus(202)
        ->assertJsonPath('data.status', 'queued')
        ->assertJsonPath('data.error_message', null);

    expect($email->fresh()->status)->toBe(OutboundEmailStatus::Queued);
});

it('AC-014: a sent email cannot be resent (409)', function () {
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $email = OutboundEmail::factory()->forEmailable($workOrder)->sent()->create(['sender_user_id' => $actor->id]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}/send")->assertStatus(409);
});
