<?php

use App\Models\Attachment;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\User;
use App\RichText\RichText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Task template header + item description rich text (spec 0128): AC-004,
| AC-005, AC-006, AC-008, AC-015 on `task-templates`. AC-013 (generation)
| lives in WorkOrderTaskTemplateGenerationTest, next to the rest of D-6/D-8.
|--------------------------------------------------------------------------
*/

if (! function_exists('taskTemplateUserWith')) {
    /**
     * Duplicated (guarded) from TaskTemplateCrudTest — see its own docblock.
     *
     * @param  array<int, string>  $abilities
     */
    function taskTemplateUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("task-templates.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("task-templates.{$ability}");
        }

        return $user;
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

// ---------------------------------------------------------------------------
// Header description
// ---------------------------------------------------------------------------

it('create: an inline image in the header description becomes a rich_text attachment of the template', function () {
    Sanctum::actingAs(taskTemplateUserWith(['create', 'view']));

    $response = $this->postJson('/api/task-templates', [
        'name' => 'Con immagine',
        'description' => '<p>x</p><img src="'.richTextTinyPng().'" alt="pic">',
        'items' => [['title' => 'Riga', 'due_offset_days' => 0]],
    ])->assertCreated();

    $template = TaskTemplate::query()->findOrFail($response->json('data.id'));
    $attachment = Attachment::query()
        ->where('attachable_type', $template->getMorphClass())
        ->where('attachable_id', $template->id)
        ->where('collection', RichText::ATTACHMENT_COLLECTION)
        ->sole();

    expect($response->json('data.description'))
        ->toBe('<p>x</p><img alt="pic" data-attachment-id="'.$attachment->id.'">');
});

it('create: a corrupt inline image in the header description is 422, no template row and no attachment created', function () {
    Sanctum::actingAs(taskTemplateUserWith(['create']));

    $this->postJson('/api/task-templates', [
        'name' => 'Rotto',
        'description' => '<img src="data:image/png;base64,%%%not-base64%%%" alt="">',
        'items' => [['title' => 'Riga', 'due_offset_days' => 0]],
    ])->assertStatus(422)->assertJsonValidationErrors('description');

    $this->assertDatabaseMissing('task_templates', ['name' => 'Rotto']);
    expect(Attachment::query()->count())->toBe(0);
});

it('create: an img referencing another template\'s attachment is stripped, the foreign attachment stays intact', function () {
    Sanctum::actingAs(taskTemplateUserWith(['create', 'view']));

    $otherTemplate = TaskTemplate::factory()->create();
    $foreign = Attachment::factory()->make(['collection' => RichText::ATTACHMENT_COLLECTION]);
    $foreign->attachable()->associate($otherTemplate);
    $foreign->save();

    $response = $this->postJson('/api/task-templates', [
        'name' => 'Con riferimento estraneo',
        'description' => '<p>x</p><img data-attachment-id="'.$foreign->id.'" alt="">',
        'items' => [['title' => 'Riga', 'due_offset_days' => 0]],
    ])->assertCreated();

    expect($response->json('data.description'))->toBe('<p>x</p>')
        ->and(Attachment::query()->find($foreign->id))->not->toBeNull();
});

it('update: PATCH removing one of two saved header images deletes it (row + file), the other stays', function () {
    $actor = taskTemplateUserWith(['create', 'update', 'view']);
    Sanctum::actingAs($actor);

    $create = $this->postJson('/api/task-templates', [
        'name' => 'Due immagini',
        'description' => '<img src="'.richTextTinyPng().'" alt="one"><img src="'.richTextTinyPng().'" alt="two">',
        'items' => [['title' => 'Riga', 'due_offset_days' => 0]],
    ])->assertCreated();

    $template = TaskTemplate::query()->findOrFail($create->json('data.id'));
    $attachments = Attachment::query()
        ->where('attachable_type', $template->getMorphClass())
        ->where('attachable_id', $template->id)
        ->orderBy('id')
        ->get();
    expect($attachments)->toHaveCount(2);
    [$kept, $removed] = [$attachments[0], $attachments[1]];

    $this->patchJson("/api/task-templates/{$template->id}", [
        'description' => '<img data-attachment-id="'.$kept->id.'" alt="one">',
    ])->assertOk()->assertJsonPath('data.description', '<img data-attachment-id="'.$kept->id.'" alt="one">');

    expect(Attachment::query()->find($kept->id))->not->toBeNull()
        ->and(Attachment::query()->find($removed->id))->toBeNull();
    Storage::disk(config('attachments.disk'))->assertMissing($removed->path);
});

it('create/update: an empty <p></p> header description saves as null', function () {
    $actor = taskTemplateUserWith(['create', 'update', 'view']);
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/task-templates', [
        'name' => 'Vuoto',
        'description' => '<p></p>',
        'items' => [['title' => 'Riga', 'due_offset_days' => 0]],
    ])->assertCreated();
    expect($created->json('data.description'))->toBeNull();

    $template = TaskTemplate::factory()->create(['description' => 'Originale']);
    $this->patchJson("/api/task-templates/{$template->id}", ['description' => '<p></p>'])
        ->assertOk()
        ->assertJsonPath('data.description', null);
});

it('D-2: a header description that only LOOKS non-empty (a lone remote img, stripped by the sanitizer) saves as null', function () {
    $actor = taskTemplateUserWith(['create', 'update', 'view']);
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/task-templates', [
        'name' => 'Solo immagine remota',
        'description' => '<img src="https://example.com/pic.png" alt="remote">',
        'items' => [['title' => 'Riga', 'due_offset_days' => 0]],
    ])->assertCreated();
    expect($created->json('data.description'))->toBeNull()
        ->and(Attachment::query()->count())->toBe(0);

    // Same on PATCH, clearing a previously saved image (D-4 cleanup).
    $create = $this->postJson('/api/task-templates', [
        'name' => 'Con immagine da svuotare',
        'description' => '<img src="'.richTextTinyPng().'" alt="pic">',
        'items' => [['title' => 'Riga', 'due_offset_days' => 0]],
    ])->assertCreated();
    $template = TaskTemplate::query()->findOrFail($create->json('data.id'));
    $attachment = Attachment::query()->where('attachable_type', $template->getMorphClass())->where('attachable_id', $template->id)->sole();

    $this->patchJson("/api/task-templates/{$template->id}", [
        'description' => '<img src="https://example.com/pic.png" alt="remote">',
    ])->assertOk()->assertJsonPath('data.description', null);

    expect(Attachment::query()->find($attachment->id))->toBeNull();
    Storage::disk(config('attachments.disk'))->assertMissing($attachment->path);
});

// ---------------------------------------------------------------------------
// Item description (owner: the row itself, D-3)
// ---------------------------------------------------------------------------

it('create: an inline image in an item description becomes a rich_text attachment of THAT row', function () {
    Sanctum::actingAs(taskTemplateUserWith(['create', 'view']));

    $response = $this->postJson('/api/task-templates', [
        'name' => 'Con voce illustrata',
        'items' => [['title' => 'Riga', 'due_offset_days' => 0, 'description' => '<img src="'.richTextTinyPng().'" alt="pic">']],
    ])->assertCreated();

    $item = TaskTemplateItem::query()->findOrFail($response->json('data.items.0.id'));
    $attachment = Attachment::query()
        ->where('attachable_type', $item->getMorphClass())
        ->where('attachable_id', $item->id)
        ->where('collection', RichText::ATTACHMENT_COLLECTION)
        ->sole();

    expect($response->json('data.items.0.description'))
        ->toBe('<img alt="pic" data-attachment-id="'.$attachment->id.'">');
});

it('D-2: an item description that only LOOKS non-empty (a lone remote img, stripped by the sanitizer) saves as null', function () {
    Sanctum::actingAs(taskTemplateUserWith(['create', 'view']));

    $response = $this->postJson('/api/task-templates', [
        'name' => 'Voce con immagine remota',
        'items' => [['title' => 'Riga', 'due_offset_days' => 0, 'description' => '<img src="https://example.com/pic.png" alt="remote">']],
    ])->assertCreated();

    expect($response->json('data.items.0.description'))->toBeNull()
        ->and(Attachment::query()->count())->toBe(0);
});

it('update (full sync): a corrupt inline image on an item description is 422 on items.N.description, template untouched', function () {
    $actor = taskTemplateUserWith(['create', 'update', 'view']);
    Sanctum::actingAs($actor);

    $template = TaskTemplate::factory()->create();
    $item = TaskTemplateItem::factory()->forTemplate($template)->atPosition(0)->create(['title' => 'Originale']);

    $this->patchJson("/api/task-templates/{$template->id}", [
        'items' => [[
            'id' => $item->id,
            'title' => 'Originale',
            'due_offset_days' => 0,
            'description' => '<img src="data:image/png;base64,%%%not-base64%%%" alt="">',
        ]],
    ])->assertStatus(422)->assertJsonValidationErrors('items.0.description');

    expect($item->fresh()->title)->toBe('Originale')
        ->and(Attachment::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-015 — grid cell is plain text, never markup
// ---------------------------------------------------------------------------

it('AC-015: the table row exposes the description as plain text, markup stripped', function () {
    $actor = taskTemplateUserWith(['viewAny']);
    TaskTemplate::factory()->create(['name' => 'Riga tabella', 'description' => '<p><strong>Ciao</strong></p>']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/task-templates/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();

    $row = collect($response->json('items'))->firstWhere('name', 'Riga tabella');

    expect($row['description'])->toBe('Ciao');
});

// ---------------------------------------------------------------------------
// D-13 (consistency) — TaskTemplatesAuthorization declares description as
// richtext too, same as TasksAuthorization
// ---------------------------------------------------------------------------

it('the meta field catalogue declares description as richtext', function () {
    Sanctum::actingAs(taskTemplateUserWith(['viewAny', 'create']));

    $fields = collect($this->getJson('/api/meta/task-templates')->assertOk()->json('data.fields'))->keyBy('key');

    expect($fields['description']['type'])->toBe('richtext');
});
