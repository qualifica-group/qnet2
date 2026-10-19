<?php

use App\Models\Attachment;
use App\Models\StaffTraining;
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

function trainingAttachmentUrl(StaffTraining $training): string
{
    return "/api/staff-trainings/{$training->id}/attachment";
}

function trainingUpload(UploadedFile $file, StaffTraining $training)
{
    return test()->post(trainingAttachmentUrl($training), ['file' => $file], ['Accept' => 'application/json']);
}

it('0284 AC-005: manage uploads a pdf, the second file replaces the first', function () {
    $training = StaffTraining::factory()->create();
    Sanctum::actingAs(trainingUserWith(['manage']));

    trainingUpload(UploadedFile::fake()->create('cert.pdf', 20, 'application/pdf'), $training)
        ->assertOk()->assertJsonPath('data.attachment.name', 'cert.pdf')->assertJsonStructure(['data' => ['attachment' => ['id', 'name', 'size']]]);
    $old = Attachment::query()->firstOrFail();

    trainingUpload(UploadedFile::fake()->image('scan.png'), $training)
        ->assertOk()->assertJsonPath('data.attachment.name', 'scan.png');

    expect(Attachment::query()->count())->toBe(1);
    Storage::disk('local')->assertMissing($old->path);
});

it('0284 AC-005: an exe renamed to pdf, a wrong extension and a missing file are 422', function () {
    $training = StaffTraining::factory()->create();
    Sanctum::actingAs(trainingUserWith(['manage']));
    $path = tempnam(sys_get_temp_dir(), 'exe');
    file_put_contents($path, "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xff\xff".str_repeat("\x00", 64));

    trainingUpload(new UploadedFile($path, 'virus.pdf', null, null, true), $training)->assertUnprocessable()->assertJsonValidationErrors('file');
    trainingUpload(UploadedFile::fake()->create('notes.txt', 5, 'text/plain'), $training)->assertUnprocessable();
    $this->post(trainingAttachmentUrl($training), [], ['Accept' => 'application/json'])->assertUnprocessable();

    expect(Attachment::query()->count())->toBe(0);
});

it('0284 AC-005: upload and delete are 403 for who only sees the training', function () {
    $person = trainingUserWith(['viewAny']);
    $training = StaffTraining::factory()->forUser($person)->create();
    $training->attach(UploadedFile::fake()->create('cert.pdf', 20, 'application/pdf'));
    Sanctum::actingAs($person);

    trainingUpload(UploadedFile::fake()->create('new.pdf', 20, 'application/pdf'), $training)->assertForbidden();
    $this->deleteJson(trainingAttachmentUrl($training))->assertForbidden();

    expect(Attachment::query()->count())->toBe(1);
});

it('0284 AC-005: download is for who sees the training, a stranger gets 404, no file is 404', function () {
    $manager = trainingUserWith(['viewTeam']);
    $person = User::factory()->create();
    trainingProfile($person, $manager);
    $training = StaffTraining::factory()->forUser($person)->create();

    Sanctum::actingAs($manager);
    $this->get(trainingAttachmentUrl($training), ['Accept' => 'application/json'])->assertNotFound();

    $training->attach(UploadedFile::fake()->create('cert.pdf', 20, 'application/pdf'));
    $this->get(trainingAttachmentUrl($training))->assertOk()->assertDownload('cert.pdf');

    Sanctum::actingAs(trainingUserWith(['viewAny']));
    $this->get(trainingAttachmentUrl($training), ['Accept' => 'application/json'])->assertNotFound();
    trainingUpload(UploadedFile::fake()->create('x.pdf', 5, 'application/pdf'), $training)->assertNotFound();
    $this->deleteJson(trainingAttachmentUrl($training))->assertNotFound();
});

it('0284 AC-005: manage removes the file with its binary', function () {
    $training = StaffTraining::factory()->create();
    $training->attach(UploadedFile::fake()->create('cert.pdf', 20, 'application/pdf'));
    $attachment = Attachment::query()->firstOrFail();
    Sanctum::actingAs(trainingUserWith(['manage']));

    $this->deleteJson(trainingAttachmentUrl($training))->assertNoContent();

    expect(Attachment::query()->count())->toBe(0);
    Storage::disk('local')->assertMissing($attachment->path);
});

it('0284 AC-005: the generic attachment endpoints never expose a certificate, even with attachments.*', function () {
    $training = StaffTraining::factory()->create();
    $training->attach(UploadedFile::fake()->create('cert.pdf', 20, 'application/pdf'));
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
});

it('0284 AC-005: deleting the training removes its certificate', function () {
    $training = StaffTraining::factory()->create();
    $training->attach(UploadedFile::fake()->create('cert.pdf', 20, 'application/pdf'));
    Sanctum::actingAs(trainingUserWith(['manage']));

    $this->deleteJson("/api/staff-trainings/{$training->id}")->assertNoContent();

    expect(Attachment::query()->count())->toBe(0);
});
