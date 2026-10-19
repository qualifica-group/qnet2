<?php

use App\Enums\AbsenceRequestStatus;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['attachments.disk' => 'local']);
    Storage::fake('local');
});

/** A real PE header named .pdf: the fake file helpers guess the MIME from the name, a real file does not. */
function absenceFakeExecutable(): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'exe');
    file_put_contents($path, "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xff\xff".str_repeat("\x00", 64));

    return new UploadedFile($path, 'virus.pdf', null, null, true);
}

function absenceUrl($request): string
{
    return "/api/absence-requests/{$request->id}/attachment";
}

it('AC-015: the person uploads a pdf and a png, the second replaces the first', function () {
    $manager = absenceUserWith();
    $person = absenceUserWith();
    $request = absenceSubmitted($person, $manager);
    Sanctum::actingAs($person);

    $this->post(absenceUrl($request), ['file' => UploadedFile::fake()->create('cert.pdf', 20, 'application/pdf')], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.attachment.name', 'cert.pdf');
    $old = Attachment::query()->firstOrFail();

    $this->post(absenceUrl($request), ['file' => UploadedFile::fake()->image('scan.png')], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.attachment.name', 'scan.png');

    expect(Attachment::query()->count())->toBe(1);
    Storage::disk('local')->assertMissing($old->path);
});

it('AC-015: an exe renamed to pdf, and a non-allowed extension, are 422', function () {
    $request = absenceSubmitted($person = absenceUserWith(), absenceUserWith());
    Sanctum::actingAs($person);

    $this->post(absenceUrl($request), ['file' => absenceFakeExecutable()], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors('file');
    $this->post(absenceUrl($request), ['file' => UploadedFile::fake()->create('notes.txt', 5, 'text/plain')], ['Accept' => 'application/json'])
        ->assertUnprocessable();
    $this->post(absenceUrl($request), [], ['Accept' => 'application/json'])->assertUnprocessable();

    expect(Attachment::query()->count())->toBe(0);
});

it('AC-015: download is for who sees the request; a stranger gets 404; no file is 404', function () {
    $manager = absenceUserWith();
    $person = absenceUserWith();
    $request = absenceSubmitted($person, $manager);
    $stranger = absenceUserWith();
    Sanctum::actingAs($person);

    $this->getJson(absenceUrl($request))->assertNotFound();
    $this->post(absenceUrl($request), ['file' => UploadedFile::fake()->create('cert.pdf', 20, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();

    foreach ([$person, $manager] as $viewer) {
        Sanctum::actingAs($viewer);
        $this->get(absenceUrl($request))->assertOk()->assertDownload('cert.pdf');
    }

    Sanctum::actingAs($stranger);
    $this->get(absenceUrl($request))->assertNotFound();
    $this->deleteJson(absenceUrl($request))->assertNotFound();
});

it('AC-015: an approver sees but cannot write; the person cannot write once approved; HR always can', function () {
    $manager = absenceUserWith();
    $person = absenceUserWith();
    $hr = absenceUserWith(['viewAny', 'manageAll']);
    $request = absenceSubmitted($person, $manager);
    $file = fn () => ['file' => UploadedFile::fake()->create('cert.pdf', 20, 'application/pdf')];

    Sanctum::actingAs($manager);
    $this->post(absenceUrl($request), $file(), ['Accept' => 'application/json'])->assertForbidden();
    $this->deleteJson(absenceUrl($request))->assertForbidden();

    $request->update(['status' => AbsenceRequestStatus::Approved]);
    Sanctum::actingAs($person);
    $this->post(absenceUrl($request), $file(), ['Accept' => 'application/json'])->assertForbidden();

    Sanctum::actingAs($hr);
    $this->post(absenceUrl($request), $file(), ['Accept' => 'application/json'])->assertOk();
});

it('AC-015: delete removes the row and the file (204)', function () {
    $person = absenceUserWith();
    $request = absenceSubmitted($person, absenceUserWith());
    Sanctum::actingAs($person);
    $this->post(absenceUrl($request), ['file' => UploadedFile::fake()->create('cert.pdf', 20, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();
    $attachment = Attachment::query()->firstOrFail();

    $this->deleteJson(absenceUrl($request))->assertNoContent();

    expect(Attachment::query()->count())->toBe(0);
    Storage::disk('local')->assertMissing($attachment->path);
});

it('AC-015: the generic attachment endpoints never expose an absence attachment, even with attachments.*', function () {
    $person = absenceUserWith();
    $request = absenceSubmitted($person, absenceUserWith());
    Sanctum::actingAs($person);
    $this->post(absenceUrl($request), ['file' => UploadedFile::fake()->create('cert.pdf', 20, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();
    $attachment = Attachment::query()->firstOrFail();

    foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
        Permission::findOrCreate("attachments.{$ability}", 'web');
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $power = User::factory()->create();
    $power->givePermissionTo(['attachments.viewAny', 'attachments.view', 'attachments.create', 'attachments.update', 'attachments.delete']);
    Sanctum::actingAs($power);

    $this->getJson("/api/attachments/{$attachment->id}")->assertForbidden();
    $this->getJson("/api/attachments/{$attachment->id}/download")->assertForbidden();
    $this->getJson("/api/attachments/{$attachment->id}/view")->assertForbidden();
    $this->deleteJson("/api/attachments/{$attachment->id}")->assertForbidden();
    $this->getJson("/api/attachments?attachable_type=absence_request&attachable_id={$request->id}")->assertUnprocessable();
    $this->postJson('/api/attachments', ['file' => UploadedFile::fake()->create('x.pdf', 5, 'application/pdf'), 'attachable_type' => 'absence_request', 'attachable_id' => $request->id])->assertUnprocessable();

    expect(Attachment::query()->count())->toBe(1);
});
