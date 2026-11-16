<?php

use App\Models\Attachment;
use App\Models\Registry;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0393: "Scarica tutti" packs every document of one owner and collection
 * into a single ZIP, as the legacy QNet did for a work order (task 427111).
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    config(['migrations.legacy_files_base_url' => 'https://legacy.test']);

    foreach (['viewAny', 'view', 'create', 'delete'] as $ability) {
        Permission::findOrCreate("attachments.{$ability}");
    }
});

function archiveActor(array $abilities = ['viewAny', 'view']): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(array_map(fn (string $ability) => "attachments.{$ability}", $abilities));

    return $user;
}

function storedDocument(Registry $registry, string $name, string $contents, string $collection = 'documents'): Attachment
{
    $attachment = Attachment::factory()->create([
        'attachable_type' => 'registry',
        'attachable_id' => $registry->id,
        'collection' => $collection,
        'original_name' => $name,
    ]);
    Storage::disk('local')->put($attachment->path, $contents);

    return $attachment;
}

function archiveUrl(string $type, int $id): string
{
    return "/api/attachments/archive?attachable_type={$type}&attachable_id={$id}&collection=documents";
}

/**
 * @return array<string, string> entry name => contents
 */
function archiveEntries(TestResponse $response): array
{
    $path = $response->baseResponse->getFile()->getPathname();
    $zip = new ZipArchive;
    $zip->open($path);

    $entries = [];
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $name = $zip->getNameIndex($index);
        $entries[$name] = $zip->getFromName($name);
    }

    $zip->close();
    unlink($path);

    return $entries;
}

it('AC-001: packs every document of the collection, renaming duplicated names', function () {
    $registry = Registry::factory()->create();
    storedDocument($registry, 'Preventivo.pdf', 'first');
    storedDocument($registry, 'Preventivo.pdf', 'second');
    storedDocument($registry, 'Visura.pdf', 'visura');
    storedDocument($registry, 'Altro.pdf', 'other collection', 'contracts');
    Sanctum::actingAs(archiveActor());

    $response = $this->get(archiveUrl('registry', $registry->id))
        ->assertOk()
        ->assertDownload(__('Documents')."-{$registry->id}.zip");

    $entries = archiveEntries($response);

    expect(array_keys($entries))->toEqualCanonicalizing(['Preventivo.pdf', 'Preventivo (2).pdf', 'Visura.pdf'])
        ->and([$entries['Preventivo.pdf'], $entries['Preventivo (2).pdf']])->toEqualCanonicalizing(['first', 'second'])
        ->and($entries['Visura.pdf'])->toBe('visura');
});

it('AC-002: a file no longer available is left out and named in a note inside the archive', function () {
    $registry = Registry::factory()->create();
    storedDocument($registry, 'Visura.pdf', 'visura');
    $lost = storedDocument($registry, 'Perso.pdf', 'gone');
    Storage::disk('local')->delete($lost->path);
    Sanctum::actingAs(archiveActor());

    $entries = archiveEntries($this->get(archiveUrl('registry', $registry->id))->assertOk());
    $note = __('Documents not included.txt');

    expect(array_keys($entries))->toEqualCanonicalizing(['Visura.pdf', $note])
        ->and($entries[$note])->toContain('Perso.pdf');
});

it('AC-003: legacy documents are fetched from the legacy QNet; an unreachable one is listed as missing', function () {
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['view', 'viewDocuments']);
    $actor->givePermissionTo(['attachments.viewAny', 'attachments.view']);
    $legacy = ['attachable_type' => 'work_order', 'attachable_id' => $workOrder->id];
    $available = Attachment::factory()->legacyLink()->create([...$legacy, 'original_name' => 'Contratto.pdf']);
    $gone = Attachment::factory()->legacyLink()->create([...$legacy, 'original_name' => 'Sparito.pdf']);
    Http::fake([
        $available->external_url => Http::response('legacy-body'),
        $gone->external_url => Http::response('', 404),
    ]);
    Sanctum::actingAs($actor);

    $entries = archiveEntries($this->get(archiveUrl('work_order', $workOrder->id))->assertOk());

    expect($entries['Contratto.pdf'])->toBe('legacy-body')
        ->and($entries[__('Documents not included.txt')])->toContain('Sparito.pdf');
});

it('AC-004: 403 without attachments.view', function () {
    $registry = Registry::factory()->create();
    storedDocument($registry, 'Visura.pdf', 'visura');
    Sanctum::actingAs(archiveActor(['viewAny']));

    $this->getJson(archiveUrl('registry', $registry->id))->assertForbidden();
});

it('AC-004: 403 on a work order the actor cannot see', function () {
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['view', 'viewDocuments'], inScope: false);
    $actor->givePermissionTo(['attachments.viewAny', 'attachments.view']);
    Attachment::factory()->create(['attachable_type' => 'work_order', 'attachable_id' => $workOrder->id, 'collection' => 'documents']);
    Sanctum::actingAs($actor);

    $this->getJson(archiveUrl('work_order', $workOrder->id))->assertForbidden();
});

it('AC-005: 404 when the owner has no documents, 401 for a guest', function () {
    $registry = Registry::factory()->create();

    $this->getJson(archiveUrl('registry', $registry->id))->assertUnauthorized();

    Sanctum::actingAs(archiveActor());
    $this->getJson(archiveUrl('registry', $registry->id))->assertNotFound();
});
