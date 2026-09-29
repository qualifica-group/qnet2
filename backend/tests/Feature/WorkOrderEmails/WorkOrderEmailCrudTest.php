<?php

use App\Enums\OutboundEmailStatus;
use App\Models\Attachment;
use App\Models\EmailTemplate;
use App\Models\OutboundEmail;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| Draft CRUD + list + render-template (spec 0175, AC-005, AC-008)
|--------------------------------------------------------------------------
*/

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-008 — POST/PATCH/DELETE .../emails
// ---------------------------------------------------------------------------

it('AC-008: POST creates an empty draft with a bare {} body', function () {
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    Sanctum::actingAs($actor);

    $response = $this->postJson("/api/work-orders/{$workOrder->id}/emails", [])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.sender.id', $actor->id)
        ->assertJsonPath('data.to', [])
        ->assertJsonPath('data.can.update', true)
        ->assertJsonPath('data.can.delete', true);

    expect(OutboundEmail::query()->findOrFail($response->json('data.id')))
        ->status->toBe(OutboundEmailStatus::Draft)
        ->and(OutboundEmail::first()->sender_user_id)->toBe($actor->id);
});

it('AC-008: POST rejects an invalid recipient address with a 422 on to.0', function () {
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/emails", ['to' => ['not-an-email']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to.0']);

    expect(OutboundEmail::count())->toBe(0);
});

it('AC-008: POST rejects more than max_recipients combined to+cc+bcc, error on `to`', function () {
    config(['outbound_emails.max_recipients' => 3]);
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/emails", [
        'to' => ['a@example.com', 'b@example.com'],
        'cc' => ['c@example.com'],
        'bcc' => ['d@example.com'],
    ])->assertUnprocessable()->assertJsonValidationErrors(['to']);
});

it('AC-008: PATCH updates a draft (subject/body sanitized, recipients normalized)', function () {
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $email = OutboundEmail::factory()->forEmailable($workOrder)->create(['sender_user_id' => $actor->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}", [
        'subject' => 'Ciao',
        'body' => '<p onclick="alert(1)">Corpo</p><script>evil()</script>',
        'to' => [' dup@example.com ', 'dup@example.com', 'Other@Example.com'],
    ])
        ->assertOk()
        ->assertJsonPath('data.subject', 'Ciao')
        ->assertJsonPath('data.to', ['dup@example.com', 'Other@Example.com']);

    $body = OutboundEmail::query()->findOrFail($email->id)->body;
    expect($body)->not->toContain('onclick')->not->toContain('<script>');
});

it('AC-008: PATCH on a sent email is 409 and leaves it unchanged', function () {
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $email = OutboundEmail::factory()->forEmailable($workOrder)->sent()->create(['sender_user_id' => $actor->id, 'subject' => 'Original']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}", ['subject' => 'Changed'])
        ->assertStatus(409);

    expect(OutboundEmail::query()->findOrFail($email->id)->subject)->toBe('Original');
});

it('AC-008: DELETE removes a draft and its own attachments', function () {
    Storage::fake('local');
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $email = OutboundEmail::factory()->forEmailable($workOrder)->create(['sender_user_id' => $actor->id]);
    $attachment = Attachment::factory()->for($email, 'attachable')->create(['collection' => OutboundEmail::ATTACHMENT_COLLECTION]);
    Storage::disk($attachment->disk)->put($attachment->path, 'x');
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}")->assertNoContent();

    expect(OutboundEmail::find($email->id))->toBeNull()
        ->and(Attachment::find($attachment->id))->toBeNull()
        ->and(Storage::disk($attachment->disk)->exists($attachment->path))->toBeFalse();
});

it('AC-008: DELETE on a sent email is 409 and does not delete it', function () {
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $email = OutboundEmail::factory()->forEmailable($workOrder)->sent()->create(['sender_user_id' => $actor->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}")->assertStatus(409);

    expect(OutboundEmail::find($email->id))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// List — 20 per page, updated_at desc, meta shape
// ---------------------------------------------------------------------------

it('list: data + meta envelope, newest updated_at first', function () {
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['viewEmails']);
    $older = OutboundEmail::factory()->forEmailable($workOrder)->sent()->create(['updated_at' => now()->subDay()]);
    $newer = OutboundEmail::factory()->forEmailable($workOrder)->sent()->create(['updated_at' => now()]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/work-orders/{$workOrder->id}/emails")
        ->assertOk()
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.1.id', $older->id)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 1);
});

// ---------------------------------------------------------------------------
// AC-005 — POST .../emails/render-template
// ---------------------------------------------------------------------------

it('AC-005: render-template resolves work_order/sender/client, drops unknown tokens, escapes the body', function () {
    $workOrder = WorkOrder::factory()->create(['title' => 'Impianto <b>A</b>']);
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $actor->forceFill(['name' => 'Mario Rossi'])->save();
    $template = EmailTemplate::factory()->create([
        'subject' => 'Rif. {work_order.code} - {foo.bar}',
        'body' => '<p>Gentile {client.name}, da {sender.name} — {work_order.title}</p>',
    ]);
    Sanctum::actingAs($actor);

    $response = $this->postJson("/api/work-orders/{$workOrder->id}/emails/render-template", [
        'email_template_id' => $template->id,
    ])->assertOk();

    expect($response->json('data.subject'))->toBe("Rif. {$workOrder->code} - ")
        ->and($response->json('data.body'))->toContain('Mario Rossi')
        ->and($response->json('data.body'))->toContain('&lt;b&gt;A&lt;/b&gt;')
        ->not->toContain('<b>A</b>');
});

it('AC-005: render-template 422s for an inactive template', function () {
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $template = EmailTemplate::factory()->inactive()->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/emails/render-template", ['email_template_id' => $template->id])
        ->assertUnprocessable();
});
