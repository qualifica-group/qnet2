<?php

use App\Models\Attachment;
use App\Models\Task;
use App\Models\User;
use App\RichText\RichText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Task description rich text (spec 0128): AC-002, AC-004, AC-005, AC-006,
| AC-008. TaskDescriptionWriter's own write-time behaviour — the sanitizer/
| image-processor rules themselves are Unit-tested in tests/Unit/RichText.
|--------------------------------------------------------------------------
*/

if (! function_exists('taskActorWith')) {
    /**
     * Duplicated (guarded) from TaskCrudTest — see its own docblock for why.
     *
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

if (! function_exists('taskPayload')) {
    /**
     * Duplicated (guarded) from TaskCrudTest — see its own docblock for why.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function taskPayload(array $overrides = []): array
    {
        return [
            'title' => 'Prima attivita',
            'requester_id' => User::factory()->create()->id,
            'assignee_ids' => [User::factory()->create()->id],
            'end_date' => '2026-12-31',
            ...$overrides,
        ];
    }
}

if (! function_exists('richTextTinyPng')) {
    // A real, minimal 1x1 transparent PNG — decodes and detects as image/png.
    function richTextTinyPng(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
    }
}

beforeEach(function () {
    Storage::fake(config('attachments.disk'));
});

it('AC-002: a mention span in a task description is saved as plain text, never as a mention node', function () {
    Sanctum::actingAs(taskActorWith(['create', 'view']));

    $description = '<p>Vedi <span data-type="mention" data-id="7" data-label="Anna Bianchi">@Anna Bianchi</span></p>';

    $response = $this->postJson('/api/tasks', taskPayload(['description' => $description]))->assertCreated();

    expect($response->json('data.description'))->toBe('<p>Vedi @Anna Bianchi</p>');
});

it('AC-004: a corrupt inline image on POST is 422 on description, no task row and no attachment created', function () {
    Sanctum::actingAs(taskActorWith(['create']));

    $this->postJson('/api/tasks', taskPayload([
        'title' => 'Con immagine rotta',
        'description' => '<img src="data:image/png;base64,%%%not-base64%%%" alt="">',
    ]))->assertStatus(422)->assertJsonValidationErrors('description');

    $this->assertDatabaseMissing('tasks', ['title' => 'Con immagine rotta']);
    expect(Attachment::query()->count())->toBe(0);
});

it('AC-004: a corrupt inline image on PATCH is 422, the task keeps its original description untouched', function () {
    $actor = taskActorWith(['create', 'update', 'view']);
    Sanctum::actingAs($actor);
    $task = Task::factory()->forCreator($actor)->create(['description' => 'Originale']);

    $this->patchJson("/api/tasks/{$task->id}", [
        'description' => '<img src="data:image/png;base64,%%%not-base64%%%" alt="">',
    ])->assertStatus(422)->assertJsonValidationErrors('description');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'description' => 'Originale']);
    expect(Attachment::query()->count())->toBe(0);
});

it('AC-005: an img referencing another task\'s attachment is stripped, the foreign attachment stays intact', function () {
    Sanctum::actingAs(taskActorWith(['create', 'view']));

    $otherTask = Task::factory()->create();
    $foreign = Attachment::factory()->make(['collection' => RichText::ATTACHMENT_COLLECTION]);
    $foreign->attachable()->associate($otherTask);
    $foreign->save();

    $response = $this->postJson('/api/tasks', taskPayload([
        'description' => '<p>x</p><img data-attachment-id="'.$foreign->id.'" alt="">',
    ]))->assertCreated();

    expect($response->json('data.description'))->toBe('<p>x</p>')
        ->and(Attachment::query()->find($foreign->id))->not->toBeNull();
});

it('AC-006: PATCH removing one of two saved images deletes it (row + file), the other stays', function () {
    $actor = taskActorWith(['create', 'update', 'view']);
    Sanctum::actingAs($actor);

    $create = $this->postJson('/api/tasks', taskPayload([
        'description' => '<img src="'.richTextTinyPng().'" alt="one"><img src="'.richTextTinyPng().'" alt="two">',
    ]))->assertCreated();

    $task = Task::query()->findOrFail($create->json('data.id'));
    $attachments = Attachment::query()
        ->where('attachable_type', $task->getMorphClass())
        ->where('attachable_id', $task->id)
        ->orderBy('id')
        ->get();
    expect($attachments)->toHaveCount(2);
    [$kept, $removed] = [$attachments[0], $attachments[1]];

    $this->patchJson("/api/tasks/{$task->id}", [
        'description' => '<img data-attachment-id="'.$kept->id.'" alt="one">',
    ])->assertOk()->assertJsonPath('data.description', '<img data-attachment-id="'.$kept->id.'" alt="one">');

    expect(Attachment::query()->find($kept->id))->not->toBeNull()
        ->and(Attachment::query()->find($removed->id))->toBeNull();
    Storage::disk(config('attachments.disk'))->assertMissing($removed->path);
});

it('AC-008: an empty <p></p> description saves as null on both POST and PATCH', function () {
    $actor = taskActorWith(['create', 'update', 'view']);
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/tasks', taskPayload(['description' => '<p></p>']))->assertCreated();
    expect($created->json('data.description'))->toBeNull();

    $task = Task::factory()->forCreator($actor)->create(['description' => 'Originale']);
    $this->patchJson("/api/tasks/{$task->id}", ['description' => '<p></p>'])
        ->assertOk()
        ->assertJsonPath('data.description', null);
});

it('D-2: a description that only LOOKS non-empty (a lone remote img, stripped by the sanitizer) saves as null on POST', function () {
    Sanctum::actingAs(taskActorWith(['create', 'view']));

    $response = $this->postJson('/api/tasks', taskPayload([
        'description' => '<img src="https://example.com/pic.png" alt="remote">',
    ]))->assertCreated();

    expect($response->json('data.description'))->toBeNull()
        ->and(Attachment::query()->count())->toBe(0);
});

it('D-2: the same PATCH clears an existing description to null and deletes its now-unreferenced images', function () {
    $actor = taskActorWith(['create', 'update', 'view']);
    Sanctum::actingAs($actor);

    $create = $this->postJson('/api/tasks', taskPayload([
        'description' => '<img src="'.richTextTinyPng().'" alt="pic">',
    ]))->assertCreated();
    $task = Task::query()->findOrFail($create->json('data.id'));
    $attachment = Attachment::query()->where('attachable_type', $task->getMorphClass())->where('attachable_id', $task->id)->sole();

    $this->patchJson("/api/tasks/{$task->id}", [
        'description' => '<img src="https://example.com/pic.png" alt="remote">',
    ])->assertOk()->assertJsonPath('data.description', null);

    expect(Attachment::query()->find($attachment->id))->toBeNull();
    Storage::disk(config('attachments.disk'))->assertMissing($attachment->path);
});

it('AC-016: the meta field catalogue declares description as richtext', function () {
    Sanctum::actingAs(taskActorWith(['viewAny', 'create']));

    $fields = collect($this->getJson('/api/meta/tasks')->assertOk()->json('data.fields'))->keyBy('key');

    expect($fields['description']['type'])->toBe('richtext');
});
