<?php

use App\Models\Attachment;
use App\Models\Note;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\User;
use App\RichText\RichText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/*
|--------------------------------------------------------------------------
| Authorization of `rich_text` attachments (spec 0128, D-6, AC-010/AC-011)
|--------------------------------------------------------------------------
|
| Images embedded in a rich text field are read through the OWNING record's
| read access, never through `attachments.*`; and are entirely closed off
| to the generic upload/delete/browse endpoints, which stay permission-only
| for every other collection (unchanged, see AttachmentCrudTest/
| AttachmentIndexTest/TaskDocumentsTest).
*/

uses(RefreshDatabase::class);

// Reused verbatim across the Attachments suites — function_exists guards let
// every file declare it without a redeclaration clash.
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

// Reused verbatim from TaskVisibilityTest.
if (! function_exists('taskActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function taskActorWith(array $abilities, bool $withViewAll = true): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll', 'manageAll', 'complete', 'validate', 'block', 'viewDocuments', 'requestUpdate'] as $ability) {
            Permission::findOrCreate("tasks.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("tasks.{$ability}");
        }

        if ($withViewAll) {
            $user->givePermissionTo('tasks.viewAll');
        }

        return $user;
    }
}

// Reused verbatim from NoteAuthorizationTest.
if (! function_exists('noteActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function noteActor(array $abilities = []): User
    {
        foreach (['request-management.view', 'request-management.viewAll', 'notes.create'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo($ability);
        }

        return $user;
    }
}

if (! function_exists('noteManagedOpportunity')) {
    function noteManagedOpportunity(User $operator): Opportunity
    {
        $opportunity = Opportunity::factory()->create();
        Quote::factory()->for($opportunity)->create(['operator_id' => $operator->id]);

        return $opportunity;
    }
}

if (! function_exists('richTextAttachment')) {
    /**
     * A `view`/`download` request 404s before authorization even matters if
     * the binary is missing from the (faked) disk — every positive-access
     * assertion here needs the file to actually exist, not just the row.
     */
    function richTextAttachment(Model $owner, User $uploader): Attachment
    {
        $attachment = Attachment::factory()
            ->for($owner, 'attachable')
            ->create(['collection' => RichText::ATTACHMENT_COLLECTION, 'uploaded_by' => $uploader->id]);

        Storage::disk($attachment->disk)->put($attachment->path, 'fake-image-bytes');

        return $attachment;
    }
}

beforeEach(function () {
    Storage::fake('local');
});

// ---------------------------------------------------------------------------
// AC-010 — a rich_text image of a Task reads through TaskPolicy::view, not
// attachments.view
// ---------------------------------------------------------------------------

it('AC-010: a task member without attachments.view reads a rich_text image of that task', function () {
    $uploader = User::factory()->create();
    $member = taskActorWith(['view'], withViewAll: false);
    $task = Task::factory()->forCreator($member)->create();
    $attachment = richTextAttachment($task, $uploader);

    Sanctum::actingAs($member);

    $this->get("/api/attachments/{$attachment->id}/view")->assertOk();
});

it('AC-010: a non-member with attachments.view does NOT read a rich_text image of a task they cannot see', function () {
    $uploader = User::factory()->create();
    $outsider = taskActorWith(['view'], withViewAll: false);
    Permission::findOrCreate('attachments.view');
    $outsider->givePermissionTo('attachments.view');
    $task = Task::factory()->create();
    $attachment = richTextAttachment($task, $uploader);

    Sanctum::actingAs($outsider);

    $this->get("/api/attachments/{$attachment->id}/view")->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-010 — a rich_text image of a Note reads through the note's OWN host
// read gate (NoteEntityRegistry), on a request-management (Opportunity) host
// ---------------------------------------------------------------------------

it('AC-010: an actor who manages the Opportunity without attachments.view reads a rich_text image of a note on it', function () {
    $uploader = User::factory()->create();
    $actor = noteActor(['request-management.view']);
    $opportunity = noteManagedOpportunity($actor);
    $note = Note::factory()->for($opportunity, 'notable')->create();
    $attachment = richTextAttachment($note, $uploader);

    Sanctum::actingAs($actor);

    $this->get("/api/attachments/{$attachment->id}/view")->assertOk();
});

it('AC-010: an actor with attachments.view who does NOT manage the Opportunity does NOT read a rich_text image of a note on it', function () {
    $uploader = User::factory()->create();
    $actor = noteActor(['request-management.view']);
    Permission::findOrCreate('attachments.view');
    $actor->givePermissionTo('attachments.view');
    $opportunity = Opportunity::factory()->create();
    $note = Note::factory()->for($opportunity, 'notable')->create();
    $attachment = richTextAttachment($note, $uploader);

    Sanctum::actingAs($actor);

    $this->get("/api/attachments/{$attachment->id}/view")->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-010 — same pair, on a task-hosted note
// ---------------------------------------------------------------------------

it('AC-010: a task member without attachments.view reads a rich_text image of a note hosted on that task', function () {
    $uploader = User::factory()->create();
    $member = taskActorWith(['view'], withViewAll: false);
    $task = Task::factory()->forCreator($member)->create();
    $note = Note::factory()->for($task, 'notable')->create();
    $attachment = richTextAttachment($note, $uploader);

    Sanctum::actingAs($member);

    $this->get("/api/attachments/{$attachment->id}/view")->assertOk();
});

it('AC-010: an actor with attachments.view who is not a member of the task does NOT read a rich_text image of a note hosted on it', function () {
    $uploader = User::factory()->create();
    $outsider = taskActorWith(['view'], withViewAll: false);
    Permission::findOrCreate('attachments.view');
    $outsider->givePermissionTo('attachments.view');
    $task = Task::factory()->create();
    $note = Note::factory()->for($task, 'notable')->create();
    $attachment = richTextAttachment($note, $uploader);

    Sanctum::actingAs($outsider);

    $this->get("/api/attachments/{$attachment->id}/view")->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-010 — a rich_text image of a TaskTemplate header / row reads through
// TaskTemplatePolicy::view, not attachments.view
// ---------------------------------------------------------------------------

it('AC-010: task-templates.view without attachments.view reads a rich_text image of a template header', function () {
    $uploader = User::factory()->create();
    Permission::findOrCreate('task-templates.view');
    $actor = User::factory()->create();
    $actor->givePermissionTo('task-templates.view');
    $template = TaskTemplate::factory()->create();
    $attachment = richTextAttachment($template, $uploader);

    Sanctum::actingAs($actor);

    $this->get("/api/attachments/{$attachment->id}/view")->assertOk();
});

it('AC-010: attachments.view without task-templates.view does NOT read a rich_text image of a template row', function () {
    $uploader = User::factory()->create();
    Permission::findOrCreate('attachments.view');
    $actor = User::factory()->create();
    $actor->givePermissionTo('attachments.view');
    $item = TaskTemplateItem::factory()->create();
    $attachment = richTextAttachment($item, $uploader);

    Sanctum::actingAs($actor);

    $this->get("/api/attachments/{$attachment->id}/view")->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-011 — the generic write endpoints refuse the rich_text collection
// outright, regardless of resource permission
// ---------------------------------------------------------------------------

it('AC-011: POST /api/attachments with collection=rich_text is 403 even with attachments.create', function () {
    $actor = userWithAttachmentAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/attachments', [
        'file' => UploadedFile::fake()->image('inline.png'),
        'collection' => RichText::ATTACHMENT_COLLECTION,
    ])->assertForbidden();

    expect(Attachment::count())->toBe(0);
});

it('AC-011: DELETE /api/attachments/{id} of a rich_text attachment is 403 even with attachments.delete', function () {
    $uploader = User::factory()->create();
    $actor = userWithAttachmentAbilities(['delete']);
    $task = Task::factory()->create();
    $attachment = richTextAttachment($task, $uploader);

    Sanctum::actingAs($actor);

    $this->deleteJson("/api/attachments/{$attachment->id}")->assertForbidden();

    expect(Attachment::find($attachment->id))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-011 — the generic index excludes rich_text by default; an explicit
// collection=rich_text filter is refused the same way
// ---------------------------------------------------------------------------

it('AC-011: GET /api/attachments for a task without collection excludes rich_text attachments', function () {
    $uploader = User::factory()->create();
    $actor = userWithAttachmentAbilities(['viewAny']);
    $task = Task::factory()->create();

    $document = Attachment::factory()->for($task, 'attachable')->create(['collection' => 'documents', 'uploaded_by' => $uploader->id]);
    richTextAttachment($task, $uploader);

    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/attachments?'.http_build_query([
        'attachable_type' => 'task',
        'attachable_id' => $task->id,
    ]))->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$document->id]);
});

it('AC-011: GET /api/attachments with an explicit collection=rich_text is 403 even with attachments.viewAny', function () {
    $actor = userWithAttachmentAbilities(['viewAny']);
    $task = Task::factory()->create();

    Sanctum::actingAs($actor);

    $this->getJson('/api/attachments?'.http_build_query([
        'attachable_type' => 'task',
        'attachable_id' => $task->id,
        'collection' => RichText::ATTACHMENT_COLLECTION,
    ]))->assertForbidden();
});
