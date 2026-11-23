<?php

use App\Enums\DocumentChecklistSyncStatus;
use App\Jobs\SyncDocumentChecklistJob;
use App\Models\ArchivioSmartSetting;
use App\Models\DocumentChecklist;
use App\Models\DocumentChecklistItem;
use App\Models\DocumentChecklistSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

/*
| Document checklist sections (spec 0427): AC-001..AC-006.
*/

uses(RefreshDatabase::class);

it('AC-001: store creates 2 sections and links the items by section_index', function () {
    Queue::fake();
    Sanctum::actingAs(documentChecklistUserWith(['create', 'view']));

    $response = $this->postJson('/api/document-checklists', documentChecklistPayload([
        'sections' => [
            ['id' => null, 'name' => 'Societa', 'description' => 'Atti'],
            ['id' => null, 'name' => 'Persone', 'description' => null],
        ],
        'items' => [
            documentChecklistItemPayload(['name' => 'A', 'section_index' => 0]),
            documentChecklistItemPayload(['name' => 'B', 'section_index' => 1]),
            documentChecklistItemPayload(['name' => 'C', 'section_index' => null]),
        ],
    ]))->assertCreated()
        ->assertJsonPath('data.sections.0.name', 'Societa')
        ->assertJsonPath('data.sections.0.description', 'Atti')
        ->assertJsonPath('data.sections.1.name', 'Persone')
        ->assertJsonPath('data.items.0.section_index', 0)
        ->assertJsonPath('data.items.1.section_index', 1)
        ->assertJsonPath('data.items.2.section_index', null)
        ->assertJsonMissingPath('data.items.0.section');

    $checklist = DocumentChecklist::findOrFail($response->json('data.id'));
    $sections = $checklist->sections()->get();
    expect($sections->pluck('position')->all())->toBe([0, 1]);

    $items = $checklist->items()->get();
    expect($items[0]->section_id)->toBe($sections[0]->id)
        ->and($items[1]->section_id)->toBe($sections[1]->id)
        ->and($items[2]->section_id)->toBeNull();
});

it('AC-002: update keeps the renamed section id, deletes the missing one and creates the new one', function () {
    Queue::fake();
    Sanctum::actingAs(documentChecklistUserWith(['update', 'view']));
    $checklist = documentChecklistWithItems(['A', 'B']);
    $keep = DocumentChecklistSection::factory()->for($checklist, 'checklist')->create(['name' => 'Old', 'position' => 0]);
    $drop = DocumentChecklistSection::factory()->for($checklist, 'checklist')->create(['name' => 'Drop', 'position' => 1]);
    [$itemA, $itemB] = $checklist->items()->get();
    $itemA->update(['section_id' => $keep->id]);
    $itemB->update(['section_id' => $drop->id]);

    $response = $this->putJson("/api/document-checklists/{$checklist->id}", documentChecklistPayload([
        'code' => $checklist->code,
        'sections' => [
            ['id' => $keep->id, 'name' => 'Renamed', 'description' => null],
            ['id' => null, 'name' => 'Brand new', 'description' => null],
        ],
        'items' => [
            documentChecklistItemPayload(['id' => $itemA->id, 'name' => 'A', 'section_index' => 0]),
            documentChecklistItemPayload(['id' => $itemB->id, 'name' => 'B', 'section_index' => null]),
        ],
    ]))->assertOk();

    expect($keep->fresh()->name)->toBe('Renamed')
        ->and(DocumentChecklistSection::find($drop->id))->toBeNull()
        ->and($itemB->fresh()->section_id)->toBeNull()
        ->and($response->json('data.sections.0.id'))->toBe($keep->id)
        ->and($response->json('data.sections.1.id'))->not->toBe($drop->id)
        ->and($response->json('data.sections.1.name'))->toBe('Brand new');
});

it('AC-002: deleting a section leaves its items without section', function () {
    $checklist = documentChecklistWithItems(['A']);
    $section = DocumentChecklistSection::factory()->for($checklist, 'checklist')->create();
    $item = $checklist->items()->first();
    $item->update(['section_id' => $section->id]);

    $section->delete();

    expect($item->fresh()->section_id)->toBeNull();
});

it('AC-003: store rejects bad sections and date fields, writes nothing and queues nothing', function () {
    Queue::fake();
    Sanctum::actingAs(documentChecklistUserWith(['create']));

    $sections = array_map(fn (int $n): array => ['id' => null, 'name' => "Group {$n}", 'description' => null], range(1, 21));
    $sections[1]['name'] = 'GROUP 1';

    $this->postJson('/api/document-checklists', documentChecklistPayload([
        'sections' => $sections,
        'items' => [
            documentChecklistItemPayload(['section_index' => 21]),
            documentChecklistItemPayload(['max_age_days' => 30, 'date_field' => 'Issue Date']),
            documentChecklistItemPayload(['max_age_days' => null, 'date_field' => 'issue_date']),
        ],
    ]))->assertUnprocessable()->assertJsonValidationErrors([
        'sections', 'sections.1.name', 'items.0.section_index', 'items.1.date_field', 'items.2.date_field',
    ]);

    expect(DocumentChecklist::count())->toBe(0)
        ->and(DocumentChecklistSection::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('AC-003: update rejects a section id of another checklist and an empty or too long name', function () {
    Queue::fake();
    Sanctum::actingAs(documentChecklistUserWith(['update']));
    $checklist = documentChecklistWithItems(['A']);
    $foreign = DocumentChecklistSection::factory()->create();

    $this->putJson("/api/document-checklists/{$checklist->id}", documentChecklistPayload([
        'code' => $checklist->code,
        'sections' => [
            ['id' => $foreign->id, 'name' => 'Stolen', 'description' => null],
            ['id' => null, 'name' => '', 'description' => null],
            ['id' => null, 'name' => str_repeat('a', 256), 'description' => null],
        ],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['sections.0.id', 'sections.1.name', 'sections.2.name']);

    expect($foreign->fresh()->document_checklist_id)->not->toBe($checklist->id);
    Queue::assertNothingPushed();
});

it('AC-004: the data migration turns section text into ordered sections and down() restores it', function () {
    $migration = require database_path('migrations/2026_12_24_100100_move_document_checklist_item_section_to_sections.php');
    $checklist = DocumentChecklist::factory()->create();

    // Rebuild the pre-migration shape: text column back, no section rows.
    $migration->down();
    $texts = ['B', 'A', 'B', null];

    foreach ($texts as $position => $text) {
        $id = DocumentChecklistItem::factory()->create([
            'document_checklist_id' => $checklist->id, 'position' => $position, 'section_id' => null,
        ])->id;
        DB::table('document_checklist_items')->where('id', $id)->update(['section' => $text]);
    }

    $migration->up();
    $migration->up();

    $sections = DocumentChecklistSection::where('document_checklist_id', $checklist->id)->orderBy('position')->get();
    $items = $checklist->items()->get();
    expect(Schema::hasColumn('document_checklist_items', 'section'))->toBeFalse()
        ->and($sections->pluck('name')->all())->toBe(['B', 'A'])
        ->and($sections->pluck('position')->all())->toBe([0, 1])
        ->and($items->pluck('section_id')->all())->toBe([$sections[0]->id, $sections[1]->id, $sections[0]->id, null]);

    $migration->down();

    expect(Schema::hasColumn('document_checklist_items', 'section'))->toBeTrue()
        ->and(DB::table('document_checklist_items')->orderBy('position')->pluck('section')->all())->toBe($texts);

    $migration->up();
});

it('AC-005: the job sends sections, section_external_id, section name and the item date_field', function () {
    ArchivioSmartSetting::factory()->create(['base_url' => 'https://archivio.test/api/v1/', 'token' => 'tok']);
    Http::fake(['*' => Http::response(['data' => []], 200)]);
    $checklist = DocumentChecklist::factory()->create();
    $second = DocumentChecklistSection::factory()->for($checklist, 'checklist')->create(['name' => 'Second', 'description' => 'Desc', 'position' => 1]);
    $first = DocumentChecklistSection::factory()->for($checklist, 'checklist')->create(['name' => 'First', 'position' => 0]);
    $withField = DocumentChecklistItem::factory()->for($checklist, 'checklist')->create([
        'position' => 0, 'section_id' => $second->id, 'max_age_days' => 30, 'date_field' => 'expiry_date',
    ]);
    $withoutField = DocumentChecklistItem::factory()->for($checklist, 'checklist')->create([
        'position' => 1, 'section_id' => null, 'max_age_days' => 30,
    ]);

    $job = (new SyncDocumentChecklistJob($checklist->id))->withFakeQueueInteractions();
    $job->job->attempts = 1;
    app()->call([$job, 'handle']);

    Http::assertSent(function (Request $request) use ($first, $second, $withField, $withoutField) {
        $body = $request->data();
        $items = collect($body['items'])->keyBy('external_id');

        return $body['sections'] === [
            ['external_id' => (string) $first->id, 'name' => 'First', 'description' => null, 'sort_order' => 0],
            ['external_id' => (string) $second->id, 'name' => 'Second', 'description' => 'Desc', 'sort_order' => 1],
        ]
            && $items[(string) $withField->id]['section_external_id'] === (string) $second->id
            && $items[(string) $withField->id]['section'] === 'Second'
            && $items[(string) $withField->id]['validation_rules']['date_field'] === 'expiry_date'
            && $items[(string) $withoutField->id]['section_external_id'] === null
            && $items[(string) $withoutField->id]['section'] === null
            && $items[(string) $withoutField->id]['validation_rules']['date_field'] === 'issue_date';
    });
});

it('AC-006: changing only the sections marks the checklist pending and queues the job', function () {
    Queue::fake();
    Sanctum::actingAs(documentChecklistUserWith(['update', 'view']));
    $checklist = documentChecklistWithItems(['A'], ['sync_status' => DocumentChecklistSyncStatus::Synced]);
    $section = DocumentChecklistSection::factory()->for($checklist, 'checklist')->create(['name' => 'Old', 'position' => 0]);
    $item = $checklist->items()->first();

    $this->putJson("/api/document-checklists/{$checklist->id}", documentChecklistPayload([
        'name' => $checklist->name,
        'code' => $checklist->code,
        'description' => $checklist->description,
        'is_active' => $checklist->is_active,
        'sections' => [['id' => $section->id, 'name' => 'Renamed', 'description' => null]],
        'items' => [documentChecklistItemPayload([
            'id' => $item->id, 'name' => $item->name, 'section_index' => null,
        ])],
    ]))->assertOk()->assertJsonPath('data.sync_status', 'pending');

    expect($checklist->fresh()->sync_status)->toBe(DocumentChecklistSyncStatus::Pending);
    Queue::assertPushed(SyncDocumentChecklistJob::class, 1);
});
