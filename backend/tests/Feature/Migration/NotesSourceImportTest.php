<?php

use App\Migrations\MigrationOrder;
use App\Migrations\MigrationRegistry;
use App\Models\Attachment;
use App\Models\MigrationRun;
use App\Models\Note;
use App\Models\Opportunity;
use App\Models\PurchaseRequest;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use App\RichText\RichText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

const LEGACY_NOTE_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

beforeEach(function () {
    seedMigrationsConfig();
    Storage::fake('local');
    $this->workOrder = WorkOrder::factory()->create();
    $this->workOrder->forceFill(['old_id' => 19123])->save();
    $this->author = User::factory()->create(['old_id' => 79]);
    $this->mentioned = User::factory()->create(['old_id' => 98]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function legacyNoteRecord(array $overrides = []): array
{
    return array_merge([
        'id' => 2107359,
        'subject_type' => 'work_order',
        'subject_id' => 19123,
        'parent_id' => null,
        'kind' => 'note',
        'body' => '<p>Ciao <span data-type="mention" data-id="98" data-label="Anna Venere">@Anna Venere</span> fatto?</p>',
        'author_user_id' => 79,
        'warnings' => [],
        'edited_at' => null,
        'created_at' => '2026-10-21 19:53:05',
        'updated_at' => '2026-10-21 19:53:05',
    ], $overrides);
}

/**
 * @param  array<int, array<string, mixed>>  $records
 */
function runLegacyNotesImport(array $records, ?User $actor = null): MigrationRun
{
    Http::fake([
        fakeMigrationsBaseUrl().'/notes*' => Http::response([
            'items' => $records,
            'pagination' => ['total' => count($records)],
        ]),
    ]);

    $run = MigrationRun::factory()->create(['user_id' => ($actor ?? migrationsSuperAdminActor())->id, 'source' => 'notes']);
    runMigrationJobFor($run);

    return $run->fresh();
}

it('AC-001 imports a root note on its work order with author, body, mentions and legacy dates, without log nor notification', function () {
    Notification::fake();
    $actor = migrationsSuperAdminActor();
    $activityBefore = DB::table('activity_log')->count();

    $run = runLegacyNotesImport([legacyNoteRecord(['edited_at' => '2026-10-22 08:00:00', 'updated_at' => '2026-10-22 08:00:00'])], $actor);

    $note = Note::query()->where('old_id', 2107359)->sole();

    expect($run->created_rows)->toBe(1)
        ->and($run->report)->toBeNull()
        ->and($note->notable_type)->toBe($this->workOrder->getMorphClass())
        ->and($note->notable_id)->toBe($this->workOrder->id)
        ->and($note->parent_id)->toBeNull()
        ->and($note->user_id)->toBe($this->author->id)
        ->and($note->body)->toContain('data-id="'.$this->mentioned->id.'"')
        ->and($note->mentionedUsers()->pluck('users.id')->all())->toBe([$this->mentioned->id])
        ->and($note->edited_at->toDateTimeString())->toBe('2026-10-22 08:00:00')
        ->and($note->created_at->toDateTimeString())->toBe('2026-10-21 19:53:05')
        ->and(DB::table('activity_log')->count())->toBe($activityBefore);

    Notification::assertNothingSent();
});

it('AC-001 imports notes on opportunities, tasks, registries, referents and purchase requests', function (string $subjectType, string $modelClass) {
    $subject = $modelClass::factory()->create();
    $subject->forceFill(['old_id' => 555])->save();

    runLegacyNotesImport([legacyNoteRecord(['subject_type' => $subjectType, 'subject_id' => 555])]);

    $note = Note::query()->sole();

    expect($note->notable_type)->toBe($subject->getMorphClass())
        ->and($note->notable_id)->toBe($subject->id);
})->with([
    'opportunity' => ['opportunity', Opportunity::class],
    'task' => ['task', Task::class],
    // REQUIREMENT CHANGED (spec 0312 D-6): the three modules now host a notes thread.
    'registry' => ['registry', Registry::class],
    'referent' => ['referent', Referent::class],
    'purchase request' => ['purchase_request', PurchaseRequest::class],
]);

it('AC-001 sanitizes the legacy markup of the body', function () {
    runLegacyNotesImport([legacyNoteRecord(['body' => '<p class="ql-align-center">Testo<script>alert(1)</script></p>'])]);

    expect(Note::query()->sole()->body)->toBe('<p>Testo</p>');
});

it('AC-002 attaches a reply to its migrated root note and its subject', function () {
    $run = runLegacyNotesImport([
        legacyNoteRecord(),
        legacyNoteRecord(['id' => 2107400, 'subject_type' => 'opportunity', 'subject_id' => 1, 'parent_id' => 2107359]),
    ]);

    $root = Note::query()->where('old_id', 2107359)->sole();
    $reply = Note::query()->where('old_id', 2107400)->sole();

    expect($run->created_rows)->toBe(2)
        ->and($reply->parent_id)->toBe($root->id)
        ->and($reply->notable_type)->toBe($root->notable_type)
        ->and($reply->notable_id)->toBe($root->notable_id);
});

it('AC-002 fails a reply whose root note is not migrated', function () {
    $run = runLegacyNotesImport([legacyNoteRecord(['parent_id' => 1])]);

    expect($run->failed_rows)->toBe(1)
        ->and(Note::query()->count())->toBe(0)
        ->and(legacyTaskErrors($run)[0])->toContain('Root note (legacy id 1) not migrated');
});

it('AC-003 skips a note already imported', function () {
    runLegacyNotesImport([legacyNoteRecord()]);

    $run = runLegacyNotesImport([legacyNoteRecord()]);

    expect($run->skipped_rows)->toBe(1)
        ->and(Note::query()->count())->toBe(1);
});

it('AC-004 fails the row when the subject or the author is missing, not migrated or not supported', function (array $overrides, string $reason) {
    $run = runLegacyNotesImport([legacyNoteRecord($overrides)]);

    expect($run->failed_rows)->toBe(1)
        ->and(Note::query()->count())->toBe(0)
        ->and(legacyTaskErrors($run)[0])->toContain($reason);
})->with([
    'subject not migrated' => [['subject_id' => 99], 'Subject work_order (legacy id 99) not migrated'],
    'subject missing' => [['subject_id' => null], 'Subject work_order is required'],
    'author not migrated' => [['author_user_id' => 99], 'Author (legacy id 99) not migrated'],
    // REQUIREMENT CHANGED (spec 0312 D-6): registry and purchase_request are supported now.
    'type not supported' => [['subject_type' => 'lead'], 'Notes on lead are not supported'],
]);

it('AC-004 fails an orphan sent by the legacy with its reason', function () {
    $run = runLegacyNotesImport([['id' => 'activity_log:752408', 'orphan_reason' => 'Record orders 4733 inesistente: nota senza record.', 'warnings' => []]]);

    expect($run->failed_rows)->toBe(1)
        ->and(legacyTaskErrors($run)[0])->toContain('Record orders 4733 inesistente');
});

it('AC-005 keeps the mention of a user not migrated as text with a warning', function () {
    $run = runLegacyNotesImport([legacyNoteRecord([
        'body' => '<p><span data-type="mention" data-id="404" data-label="Mario Rossi">@Mario Rossi</span> ciao</p>',
    ])]);

    $note = Note::query()->sole();

    expect($note->body)->toBe('<p>@Mario Rossi ciao</p>')
        ->and($note->mentionedUsers()->count())->toBe(0)
        ->and(legacyTaskWarnings($run))->toHaveCount(1)
        ->and(legacyTaskWarnings($run)[0])->toContain('Mentioned user (legacy id 404) not migrated');
});

it('AC-006 stores an inline image as a rich text attachment of the note', function () {
    runLegacyNotesImport([legacyNoteRecord([
        'body' => '<p>Foto</p><p><img src="data:image/png;base64,'.LEGACY_NOTE_PNG_BASE64.'"></p>',
    ])]);

    $note = Note::query()->sole();
    $attachment = Attachment::query()->where('attachable_type', $note->getMorphClass())->where('attachable_id', $note->id)->sole();

    expect($attachment->collection)->toBe(RichText::ATTACHMENT_COLLECTION)
        ->and($note->body)->toContain('data-attachment-id="'.$attachment->id.'"')
        ->and($note->body)->not->toContain('base64');
});

it('AC-007 registers the source in the last phase with its dependencies', function () {
    $source = app(MigrationRegistry::class)->resolve('notes');
    $phases = MigrationOrder::phases();

    expect(MigrationOrder::phaseOf('notes'))->toBe(count($phases))
        ->and(array_map(fn ($dependency) => $dependency->toArray(), $source->dependencies()))->toBe([
            ['source' => 'users', 'required' => true],
            ['source' => 'work-orders', 'required' => true],
            ['source' => 'opportunities', 'required' => false],
            ['source' => 'tasks', 'required' => false],
            ['source' => 'registries', 'required' => false],
            ['source' => 'referents', 'required' => false],
            ['source' => 'purchase-requests', 'required' => false],
        ]);
});
