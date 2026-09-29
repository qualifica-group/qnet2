<?php

use App\Models\Attachment;
use App\Models\OutboundEmail;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/*
|--------------------------------------------------------------------------
| `email_attachments` carve-out on the generic Attachments endpoints
| (spec 0175, D-8, AC-011 — generic half; the nested-endpoint half is BE-05)
|--------------------------------------------------------------------------
|
| Same treatment as `rich_text` (spec 0128, D-6): closed off entirely to
| /api/attachments, regardless of holding attachments.*.
*/

uses(RefreshDatabase::class);

if (! function_exists('attachmentActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function attachmentActorWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("attachments.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("attachments.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('emailAttachment')) {
    function emailAttachment(OutboundEmail $email): Attachment
    {
        $attachment = Attachment::factory()
            ->for($email, 'attachable')
            ->create(['collection' => OutboundEmail::ATTACHMENT_COLLECTION]);

        Storage::disk($attachment->disk)->put($attachment->path, 'fake-pdf-bytes');

        return $attachment;
    }
}

beforeEach(function () {
    Storage::fake('local');
});

it('AC-011: GET /api/attachments/{id} of an email attachment is 403 even with attachments.view', function () {
    $actor = attachmentActorWith(['view']);
    $email = OutboundEmail::factory()->forEmailable(WorkOrder::factory()->create())->create();
    $attachment = emailAttachment($email);

    Sanctum::actingAs($actor);

    $this->getJson("/api/attachments/{$attachment->id}")->assertForbidden();
});

it('AC-011: GET /api/attachments/{id}/download of an email attachment is 403 even with attachments.view', function () {
    $actor = attachmentActorWith(['view']);
    $email = OutboundEmail::factory()->forEmailable(WorkOrder::factory()->create())->create();
    $attachment = emailAttachment($email);

    Sanctum::actingAs($actor);

    $this->get("/api/attachments/{$attachment->id}/download")->assertForbidden();
});

it('AC-011: DELETE /api/attachments/{id} of an email attachment is 403 even with attachments.delete', function () {
    $actor = attachmentActorWith(['delete']);
    $email = OutboundEmail::factory()->forEmailable(WorkOrder::factory()->create())->create();
    $attachment = emailAttachment($email);

    Sanctum::actingAs($actor);

    $this->deleteJson("/api/attachments/{$attachment->id}")->assertForbidden();

    expect(Attachment::find($attachment->id))->not->toBeNull();
});

it('AC-011: POST /api/attachments with collection=email_attachments is 403 even with attachments.create', function () {
    $actor = attachmentActorWith(['create']);
    $email = OutboundEmail::factory()->forEmailable(WorkOrder::factory()->create())->create();

    Sanctum::actingAs($actor);

    $this->postJson('/api/attachments', [
        'attachable_type' => 'outbound_email',
        'attachable_id' => $email->id,
        'collection' => OutboundEmail::ATTACHMENT_COLLECTION,
        'file' => UploadedFile::fake()->create('allegato.pdf', 8, 'application/pdf'),
    ])->assertForbidden();

    expect(Attachment::count())->toBe(0);
});

it('AC-011: GET /api/attachments for an email without collection excludes email_attachments', function () {
    $actor = attachmentActorWith(['viewAny']);
    $email = OutboundEmail::factory()->forEmailable(WorkOrder::factory()->create())->create();
    emailAttachment($email);

    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/attachments?'.http_build_query([
        'attachable_type' => 'outbound_email',
        'attachable_id' => $email->id,
    ]))->assertOk();

    expect($response->json('data'))->toBe([]);
});

it('AC-011: GET /api/attachments with an explicit collection=email_attachments is 403 even with attachments.viewAny', function () {
    $actor = attachmentActorWith(['viewAny']);
    $email = OutboundEmail::factory()->forEmailable(WorkOrder::factory()->create())->create();

    Sanctum::actingAs($actor);

    $this->getJson('/api/attachments?'.http_build_query([
        'attachable_type' => 'outbound_email',
        'attachable_id' => $email->id,
        'collection' => OutboundEmail::ATTACHMENT_COLLECTION,
    ]))->assertForbidden();
});
