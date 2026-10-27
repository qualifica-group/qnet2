<?php

use App\Models\Attachment;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('userWithAttachmentAbilities')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function userWithAttachmentAbilities(array $abilities): User
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

beforeEach(function () {
    Storage::fake('local');
});

// ---------------------------------------------------------------------------
// Opportunity as a polymorphic attachment owner (config/attachments.php +
// HasAttachments trait wiring)
// ---------------------------------------------------------------------------

it('upload: links a file to an opportunity via attachable_type=opportunity, collection=documents', function () {
    $actor = userWithAttachmentAbilities(['create']);
    // Spec 0316 D-9 (REQUIREMENT CHANGED): the actor must be able to view the owner.
    $actor->givePermissionTo(Permission::findOrCreate('opportunities.view'));
    Sanctum::actingAs($actor);
    $opportunity = Opportunity::factory()->create(['supervisor_id' => $actor->id]);

    $this->postJson('/api/attachments', [
        'file' => UploadedFile::fake()->create('contract.pdf', 16, 'application/pdf'),
        'attachable_type' => 'opportunity',
        'attachable_id' => $opportunity->id,
        'collection' => 'documents',
    ])->assertCreated()
        ->assertJsonPath('data.attachable_type', 'opportunity')
        ->assertJsonPath('data.attachable_id', $opportunity->id)
        ->assertJsonPath('data.collection', 'documents');

    expect($opportunity->attachments()->count())->toBe(1);
});

it('download: still works for an attachment linked to an opportunity', function () {
    $actor = userWithAttachmentAbilities(['create', 'view']);
    $actor->givePermissionTo(Permission::findOrCreate('opportunities.view'));
    Sanctum::actingAs($actor);
    $opportunity = Opportunity::factory()->create(['supervisor_id' => $actor->id]);

    $this->postJson('/api/attachments', [
        'file' => UploadedFile::fake()->create('contract.pdf', 16, 'application/pdf'),
        'attachable_type' => 'opportunity',
        'attachable_id' => $opportunity->id,
        'collection' => 'documents',
    ])->assertCreated();

    $attachment = Attachment::firstOrFail();

    $this->get("/api/attachments/{$attachment->id}/download")
        ->assertOk()
        ->assertDownload('contract.pdf');
});

it('Opportunity::attachments() relation returns the linked files', function () {
    $opportunity = Opportunity::factory()->create();
    $attachment = Attachment::factory()->for($opportunity, 'attachable')->create(['collection' => 'documents']);

    expect($opportunity->attachments()->count())->toBe(1)
        ->and($opportunity->attachments->first()->id)->toBe($attachment->id)
        ->and($attachment->attachable_type)->toBe('opportunity');
});

it('deleting the opportunity cascades: removes its attachment rows and stored binaries', function () {
    // Opportunity is a hard-delete model (no SoftDeletes), so a plain
    // delete() is the model's own force-delete — the cascade path
    // HasAttachments::bootHasAttachments() wires on `deleting`.
    $opportunity = Opportunity::factory()->create();
    $opportunity->attach(UploadedFile::fake()->create('one.pdf', 8, 'application/pdf'), 'documents');
    $opportunity->attach(UploadedFile::fake()->create('two.pdf', 8, 'application/pdf'), 'documents');

    $paths = $opportunity->attachments()->pluck('path');
    expect($paths)->toHaveCount(2);

    $opportunity->delete();

    expect(Attachment::where('attachable_type', 'opportunity')->where('attachable_id', $opportunity->id)->count())->toBe(0);
    foreach ($paths as $path) {
        Storage::disk('local')->assertMissing($path);
    }
});
