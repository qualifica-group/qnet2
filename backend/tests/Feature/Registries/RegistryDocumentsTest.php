<?php

use App\Models\Attachment;
use App\Models\Registry;
use App\Models\User;
use App\Policies\RegistryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Documents on an Anagrafica (spec 0173)
|--------------------------------------------------------------------------
|
| The Registry registers itself as an owner of the polymorphic Attachment
| subsystem; no endpoint is added. Documents behave exactly like those of an
| Opportunita'/Commessa: `registries.viewDocuments` only opens the tab and the
| row action, each attachment endpoint stays authorized by `attachments.*`.
*/

if (! function_exists('registryDocumentActor')) {
    /**
     * @param  array<int, string>  $registryAbilities
     * @param  array<int, string>  $attachmentAbilities
     */
    function registryDocumentActor(array $registryAbilities, array $attachmentAbilities = []): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewDocuments'] as $ability) {
            Permission::findOrCreate("registries.{$ability}");
        }

        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("attachments.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($registryAbilities as $ability) {
            $user->givePermissionTo("registries.{$ability}");
        }

        foreach ($attachmentAbilities as $ability) {
            $user->givePermissionTo("attachments.{$ability}");
        }

        return $user;
    }
}

beforeEach(function () {
    Storage::fake('local');
});

it('AC-001: `registry` is an attachable alias matching the morph alias already in the map', function () {
    expect(config('attachments.attachable_types.registry'))->toBe(Registry::class)
        ->and(Registry::factory()->create()->getMorphClass())->toBe('registry');
});

it('AC-001: a file is uploaded against attachable_type=registry and listed back', function () {
    $actor = registryDocumentActor(['view'], ['create', 'viewAny']);
    $registry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/attachments', [
        'attachable_type' => 'registry',
        'attachable_id' => $registry->id,
        'collection' => 'documents',
        'file' => UploadedFile::fake()->create('visura.pdf', 32, 'application/pdf'),
    ])->assertCreated()->assertJsonPath('data.attachable_type', 'registry');

    $this->getJson("/api/attachments?attachable_type=registry&attachable_id={$registry->id}&collection=documents")
        ->assertOk()
        ->assertJsonPath('data.0.original_name', 'visura.pdf');
});

it('AC-001: a registry document upload is 403 without attachments.create', function () {
    $actor = registryDocumentActor(['view'], ['viewAny']);
    $registry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/attachments', [
        'attachable_type' => 'registry',
        'attachable_id' => $registry->id,
        'file' => UploadedFile::fake()->create('vietato.pdf', 8, 'application/pdf'),
    ])->assertForbidden();

    expect(Attachment::count())->toBe(0);
});

it('AC-002: permissions:sync creates registries.viewDocuments', function () {
    expect(RegistryPolicy::abilities())->toContain('viewDocuments');

    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::where('name', 'registries.viewDocuments')->exists())->toBeTrue();
});

it('AC-002: permissions.actions.view_documents mirrors registries.viewDocuments', function () {
    $withPermission = registryDocumentActor(['view', 'viewDocuments']);
    $withoutPermission = registryDocumentActor(['view']);
    $registry = Registry::factory()->create();

    Sanctum::actingAs($withPermission);
    $this->getJson("/api/registries/{$registry->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.view_documents', true);

    Sanctum::actingAs($withoutPermission);
    $this->getJson("/api/registries/{$registry->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.view_documents', false);
});

it('AC-003: the documents row action and its documents_count badge follow registries.viewDocuments', function () {
    $registry = Registry::factory()->create(['name' => 'Con documenti']);
    $registry->attach(UploadedFile::fake()->create('uno.pdf', 8, 'application/pdf'), 'documents');
    $registry->attach(UploadedFile::fake()->create('due.pdf', 8, 'application/pdf'), 'documents');
    $registry->attach(UploadedFile::fake()->create('altro.pdf', 8, 'application/pdf'), 'other');

    Sanctum::actingAs(registryDocumentActor(['viewAny', 'viewDocuments']));
    $row = collect($this->postJson('/api/tables/registries/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items'))
        ->firstWhere('name', 'Con documenti');

    expect($row['actions'])->toContain('documents')
        ->and($row['documents_count'])->toBe(2);

    $actionKeys = collect($this->getJson('/api/tables/registries/columns')->assertOk()->json('data.actions'))->pluck('key');
    expect($actionKeys)->toContain('documents');

    Sanctum::actingAs(registryDocumentActor(['viewAny']));
    $row = collect($this->postJson('/api/tables/registries/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items'))
        ->firstWhere('name', 'Con documenti');

    expect($row['actions'])->not->toContain('documents');
});

it('AC-004: deleting a registry removes its attachment rows and their binaries', function () {
    $registry = Registry::factory()->create();
    $attachment = $registry->attach(UploadedFile::fake()->create('da-cancellare.pdf', 8, 'application/pdf'));
    Storage::disk('local')->assertExists($attachment->path);

    $registry->delete();

    expect(Attachment::count())->toBe(0);
    Storage::disk('local')->assertMissing($attachment->path);
});
