<?php

use App\Enums\TaskStatusGroup;
use App\Models\Attachment;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Documents on a Task (spec 0117, D-8, D-9)
|--------------------------------------------------------------------------
|
| The Task registers itself as an owner of the polymorphic Attachment
| subsystem; no endpoint is added. DECISIONE UTENTE 2026-09-11: the documents
| of a Task behave EXACTLY like those of an Opportunita', which means the
| authorization is resource-level (`attachments.*`) and there is no
| per-record gate of any kind. The last test in this file pins that exposure
| deliberately, so nobody later reads its absence as an oversight.
*/

if (! function_exists('taskDocumentActor')) {
    /**
     * @param  array<int, string>  $taskAbilities
     * @param  array<int, string>  $attachmentAbilities
     */
    function taskDocumentActor(array $taskAbilities, array $attachmentAbilities = []): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll', 'manageAll', 'complete', 'validate', 'block', 'viewDocuments', 'requestUpdate'] as $ability) {
            Permission::findOrCreate("tasks.{$ability}");
        }

        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("attachments.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($taskAbilities as $ability) {
            $user->givePermissionTo("tasks.{$ability}");
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

// ---------------------------------------------------------------------------
// AC-002 / AC-003 — the alias and the owner-side relation
// ---------------------------------------------------------------------------

it('AC-002: `task` is an attachable alias and matches the morph alias already in the map', function () {
    expect(config('attachments.attachable_types'))->toHaveKey('task')
        ->and(config('attachments.attachable_types.task'))->toBe(Task::class)
        ->and(Task::factory()->create()->getMorphClass())->toBe('task');
});

it('AC-003: a Task owns its attachments through the HasAttachments morph relation', function () {
    $task = Task::factory()->create();
    $attachment = $task->attach(UploadedFile::fake()->create('allegato.pdf', 16, 'application/pdf'));

    expect($task->attachments()->pluck('attachments.id')->all())->toBe([$attachment->id])
        ->and($attachment->attachable_type)->toBe('task');
});

// ---------------------------------------------------------------------------
// upload / list through the generic endpoints
// ---------------------------------------------------------------------------

it('AC-002: a file is uploaded against attachable_type=task and listed back', function () {
    $actor = taskDocumentActor(['view'], ['create', 'viewAny']);
    $task = Task::factory()->forCreator($actor)->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/attachments', [
        'attachable_type' => 'task',
        'attachable_id' => $task->id,
        'collection' => 'documents',
        'file' => UploadedFile::fake()->create('capitolato.pdf', 32, 'application/pdf'),
    ])->assertCreated()->assertJsonPath('data.attachable_type', 'task');

    $this->getJson("/api/attachments?attachable_type=task&attachable_id={$task->id}&collection=documents")
        ->assertOk()
        ->assertJsonPath('data.0.original_name', 'capitolato.pdf');
});

it('a task document upload is 403 without attachments.create', function () {
    $actor = taskDocumentActor(['view'], ['viewAny']);
    $task = Task::factory()->forCreator($actor)->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/attachments', [
        'attachable_type' => 'task',
        'attachable_id' => $task->id,
        'file' => UploadedFile::fake()->create('vietato.pdf', 8, 'application/pdf'),
    ])->assertForbidden();

    expect(Attachment::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-019 — documents are an OPERATIVE action: the write lock does not reach them
// ---------------------------------------------------------------------------

it('AC-019: a member uploads a document on a frozen task (blocked, closed, in validation)', function (string $frozen) {
    $actor = taskDocumentActor(['view'], ['create']);

    $task = match ($frozen) {
        'blocked' => Task::factory()->forCreator($actor)->create(['is_blocked' => true]),
        'closed' => Task::factory()->forCreator($actor)
            ->inStatus(TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->create())->create(),
        'in_validation' => Task::factory()->forCreator($actor)
            ->inStatus(TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create())->create(),
    };

    Sanctum::actingAs($actor);

    $this->postJson('/api/attachments', [
        'attachable_type' => 'task',
        'attachable_id' => $task->id,
        'file' => UploadedFile::fake()->create('su-task-congelato.pdf', 8, 'application/pdf'),
    ])->assertCreated();
})->with(['blocked', 'closed', 'in_validation']);

// ---------------------------------------------------------------------------
// AC-020 — the detail exposes the gate of the documents tab
// ---------------------------------------------------------------------------

it('AC-020: permissions.actions.view_documents mirrors tasks.viewDocuments, and the other eleven survive', function () {
    $withPermission = taskDocumentActor(['view', 'viewDocuments']);
    $withoutPermission = taskDocumentActor(['view']);
    $task = Task::factory()->forCreator($withPermission)->create();
    $task->assignees()->attach($withoutPermission);

    Sanctum::actingAs($withPermission);
    $actions = $this->getJson("/api/tasks/{$task->id}")->assertOk()->json('permissions.actions');

    // spec 0118, D-10: `request_update` joined the array as the seventh
    // domain action, after `unblock`. spec 0121, D-6: `complete_to_validation`
    // joined right after `complete` — both are mechanical consequences of
    // TasksAuthorization::actions() growing by one key, not a change to this
    // criterion's own subject.
    expect($actions['view_documents'])->toBeTrue()
        ->and(array_keys($actions))->toBe([
            'delete', 'export', 'import', 'view_activity', 'view_documents',
            'complete', 'complete_to_validation', 'uncomplete', 'approve', 'reject', 'block', 'unblock',
            'request_update',
        ]);

    Sanctum::actingAs($withoutPermission);
    $this->getJson("/api/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.view_documents', false);
});

// ---------------------------------------------------------------------------
// AC-022 — deleting the Task takes its documents with it
// ---------------------------------------------------------------------------

it('AC-022: deleting a task removes its attachment rows and their binaries', function () {
    $task = Task::factory()->create();
    $attachment = $task->attach(UploadedFile::fake()->create('da-cancellare.pdf', 8, 'application/pdf'));
    Storage::disk('local')->assertExists($attachment->path);

    $task->delete();

    expect(Attachment::count())->toBe(0);
    Storage::disk('local')->assertMissing($attachment->path);
});

// ---------------------------------------------------------------------------
// D-8 — the accepted exposure, pinned on purpose
// ---------------------------------------------------------------------------

it('D-8: attachments carry NO per-record gate, so a stranger with attachments.viewAny reads a task document', function () {
    $owner = taskDocumentActor(['view'], ['create']);
    $task = Task::factory()->forCreator($owner)->create();
    $task->attach(UploadedFile::fake()->create('interno.pdf', 8, 'application/pdf'));

    // No `tasks.view` at all, no membership: TaskVisibilityScope hides the
    // Task itself from this actor...
    $stranger = taskDocumentActor([], ['viewAny']);
    Sanctum::actingAs($stranger);
    $this->getJson("/api/tasks/{$task->id}")->assertForbidden();

    // ...and its documents are readable all the same. This is the documented
    // consequence of behaving exactly like the Opportunita' documents
    // (decisione utente 2026-09-11), NOT a defect of this spec. Closing it
    // means giving the Attachment subsystem a per-record gate for all six
    // aliases, which is a spec of its own.
    $this->getJson("/api/attachments?attachable_type=task&attachable_id={$task->id}")
        ->assertOk()
        ->assertJsonPath('data.0.original_name', 'interno.pdf');
});
