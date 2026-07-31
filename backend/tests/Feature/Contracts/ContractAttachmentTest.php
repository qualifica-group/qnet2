<?php

use App\Models\Attachment;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Contract as a polymorphic attachment owner (spec 0072, MT-05): the
 * `contract` alias registered in config/attachments.php. AC-041 (multi-file
 * upload, all listed and downloadable) and AC-042 (an alias absent from the
 * allow-list is rejected with 422) — same shape as
 * tests/Feature/Attachments/OpportunityAttachmentsTest.php.
 */
uses(RefreshDatabase::class);

if (! function_exists('userWithContractAttachmentAbilities')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function userWithContractAttachmentAbilities(array $abilities): User
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
// AC-041 — multiple documents uploaded to a contract, all listed/downloadable
// ---------------------------------------------------------------------------

it('upload: links multiple files to a contract via attachable_type=contract, collection=documents (AC-041)', function () {
    $actor = userWithContractAttachmentAbilities(['create', 'view']);
    Sanctum::actingAs($actor);
    $contract = Contract::factory()->create();

    $this->postJson('/api/attachments', [
        'file' => UploadedFile::fake()->create('one.pdf', 16, 'application/pdf'),
        'attachable_type' => 'contract',
        'attachable_id' => $contract->id,
        'collection' => 'documents',
    ])->assertCreated()
        ->assertJsonPath('data.attachable_type', 'contract')
        ->assertJsonPath('data.attachable_id', $contract->id)
        ->assertJsonPath('data.collection', 'documents');

    $this->postJson('/api/attachments', [
        'file' => UploadedFile::fake()->create('two.pdf', 16, 'application/pdf'),
        'attachable_type' => 'contract',
        'attachable_id' => $contract->id,
        'collection' => 'documents',
    ])->assertCreated();

    expect($contract->attachments()->count())->toBe(2);

    foreach ($contract->attachments as $attachment) {
        $this->get("/api/attachments/{$attachment->id}/download")->assertOk();
    }
});

it('deleting the contract cascades: removes its attachment rows and stored binaries (AC-041)', function () {
    $contract = Contract::factory()->create();
    $contract->attach(UploadedFile::fake()->create('one.pdf', 8, 'application/pdf'), 'documents');
    $contract->attach(UploadedFile::fake()->create('two.pdf', 8, 'application/pdf'), 'documents');

    $paths = $contract->attachments()->pluck('path');
    expect($paths)->toHaveCount(2);

    $contract->delete();

    expect(Attachment::where('attachable_type', 'contract')->where('attachable_id', $contract->id)->count())->toBe(0);
    foreach ($paths as $path) {
        Storage::disk('local')->assertMissing($path);
    }
});

it('Contract::attachments() relation returns the linked files', function () {
    $contract = Contract::factory()->create();
    $attachment = Attachment::factory()->for($contract, 'attachable')->create(['collection' => 'documents']);

    expect($contract->attachments()->count())->toBe(1)
        ->and($contract->attachments->first()->id)->toBe($attachment->id)
        ->and($attachment->attachable_type)->toBe('contract');
});

// ---------------------------------------------------------------------------
// AC-042 — an alias not on the allow-list is rejected with 422
// ---------------------------------------------------------------------------

it('upload: 422 when attachable_type is not on the allow-list (AC-042)', function () {
    $actor = userWithContractAttachmentAbilities(['create']);
    Sanctum::actingAs($actor);
    $contract = Contract::factory()->create();

    $this->postJson('/api/attachments', [
        'file' => UploadedFile::fake()->create('bogus.pdf', 16, 'application/pdf'),
        'attachable_type' => 'bogus-type',
        'attachable_id' => $contract->id,
        'collection' => 'documents',
    ])->assertStatus(422)->assertJsonValidationErrors('attachable_type');

    expect(Attachment::count())->toBe(0);
});
