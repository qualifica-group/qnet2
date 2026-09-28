<?php

use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// Nested sub-items inside a TaskTemplate's flat `items[]` (spec 0172, D-1/D-2):
// key/parent_key resolution, depth cap, "no stage on a sub-item", "offset
// within the direct parent's", ITEMS_MAX 500, and depth-first deletion with
// attachment cleanup (D-6). AC-002..AC-009.

if (! function_exists('taskTemplateUserWith')) {
    /**
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

// ---------------------------------------------------------------------------
// AC-002 — a 3-level tree under a root in a stage.
// ---------------------------------------------------------------------------

it('create: a 3-level tree under a root row gets the correct parent_id chain, stage only on the root (AC-002)', function () {
    Sanctum::actingAs(taskTemplateUserWith(['create']));

    $response = $this->postJson('/api/task-templates', [
        'name' => 'Con sotto-task',
        'stages' => [['key' => 's1', 'name' => 'Fase 1']],
        'items' => [
            ['key' => 'root', 'title' => 'Radice', 'due_offset_days' => 10, 'stage_key' => 's1'],
            ['key' => 'child', 'parent_key' => 'root', 'title' => 'Figlio', 'due_offset_days' => 5],
            ['key' => 'grand', 'parent_key' => 'child', 'title' => 'Nipote', 'due_offset_days' => 5],
            ['key' => 'great', 'parent_key' => 'grand', 'title' => 'Pronipote', 'due_offset_days' => 5],
        ],
    ])->assertCreated();

    $response->assertJsonPath('data.items_count', 4)
        ->assertJsonPath('data.items.0.title', 'Radice')
        ->assertJsonPath('data.items.0.parent_id', null)
        ->assertJsonPath('data.items.1.title', 'Figlio')
        ->assertJsonPath('data.items.2.title', 'Nipote')
        ->assertJsonPath('data.items.3.title', 'Pronipote');

    $items = collect($response->json('data.items'))->keyBy('title');
    $rootId = $items['Radice']['id'];
    $childId = $items['Figlio']['id'];
    $grandId = $items['Nipote']['id'];

    expect($items['Radice']['task_template_stage_id'])->not->toBeNull()
        ->and($items['Figlio']['parent_id'])->toBe($rootId)
        ->and($items['Figlio']['task_template_stage_id'])->toBeNull()
        ->and($items['Nipote']['parent_id'])->toBe($childId)
        ->and($items['Nipote']['task_template_stage_id'])->toBeNull()
        ->and($items['Pronipote']['parent_id'])->toBe($grandId)
        ->and($items['Pronipote']['task_template_stage_id'])->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-003 — a 4th level 422s, nothing persisted.
// ---------------------------------------------------------------------------

it('create: a 4th level below a root row -> 422 items.N.parent_key, nothing persisted (AC-003)', function () {
    Sanctum::actingAs(taskTemplateUserWith(['create']));

    $this->postJson('/api/task-templates', [
        'name' => 'Troppo profondo',
        'items' => [
            ['key' => 'a', 'title' => 'A', 'due_offset_days' => 0],
            ['key' => 'b', 'parent_key' => 'a', 'title' => 'B', 'due_offset_days' => 0],
            ['key' => 'c', 'parent_key' => 'b', 'title' => 'C', 'due_offset_days' => 0],
            ['key' => 'd', 'parent_key' => 'c', 'title' => 'D', 'due_offset_days' => 0],
            ['key' => 'e', 'parent_key' => 'd', 'title' => 'E', 'due_offset_days' => 0],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('items.4.parent_key');

    expect(TaskTemplate::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-004 — unknown parent_key, or one pointing to a later row.
// ---------------------------------------------------------------------------

it('create: an unknown parent_key, or one pointing to a later row, both 422 on items.N.parent_key (AC-004)', function () {
    Sanctum::actingAs(taskTemplateUserWith(['create']));

    $this->postJson('/api/task-templates', [
        'name' => 'Parent key sconosciuta',
        'items' => [
            ['key' => 'a', 'title' => 'A', 'due_offset_days' => 0],
            ['parent_key' => 'ghost', 'title' => 'B', 'due_offset_days' => 0],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('items.1.parent_key');

    $this->postJson('/api/task-templates', [
        'name' => 'Parent key successiva',
        'items' => [
            ['key' => 'a', 'parent_key' => 'b', 'title' => 'A', 'due_offset_days' => 0],
            ['key' => 'b', 'title' => 'B', 'due_offset_days' => 0],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('items.0.parent_key');

    expect(TaskTemplate::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-005 — a sub-item cannot carry a stage_key.
// ---------------------------------------------------------------------------

it('create: a sub-item with a stage_key -> 422 items.N.stage_key (AC-005)', function () {
    Sanctum::actingAs(taskTemplateUserWith(['create']));

    $this->postJson('/api/task-templates', [
        'name' => 'Sotto-item con fase',
        'stages' => [['key' => 's1', 'name' => 'Fase 1']],
        'items' => [
            ['key' => 'root', 'title' => 'Radice', 'due_offset_days' => 0, 'stage_key' => 's1'],
            ['key' => 'child', 'parent_key' => 'root', 'title' => 'Figlio', 'due_offset_days' => 0, 'stage_key' => 's1'],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('items.1.stage_key');

    expect(TaskTemplate::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-006 — offset never exceeds the direct parent's, equal is fine.
// ---------------------------------------------------------------------------

it('create: a sub-item offset greater than its parent\'s -> 422 items.N.due_offset_days, equal is accepted (AC-006)', function () {
    Sanctum::actingAs(taskTemplateUserWith(['create']));

    $this->postJson('/api/task-templates', [
        'name' => 'Offset maggiore',
        'items' => [
            ['key' => 'root', 'title' => 'Radice', 'due_offset_days' => 3],
            ['key' => 'child', 'parent_key' => 'root', 'title' => 'Figlio', 'due_offset_days' => 4],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('items.1.due_offset_days');

    $this->postJson('/api/task-templates', [
        'name' => 'Offset uguale',
        'items' => [
            ['key' => 'root', 'title' => 'Radice', 'due_offset_days' => 3],
            ['key' => 'child', 'parent_key' => 'root', 'title' => 'Figlio', 'due_offset_days' => 3],
        ],
    ])->assertCreated();
});

// ---------------------------------------------------------------------------
// AC-007 — ITEMS_MAX raised from 100 to 500.
// ---------------------------------------------------------------------------

it('create: 500 root rows are accepted, 501 -> 422 items (AC-007, D-5)', function () {
    Sanctum::actingAs(taskTemplateUserWith(['create']));

    $rowsOf = fn (int $count) => collect(range(1, $count))
        ->map(fn (int $n) => ['title' => "Riga {$n}", 'due_offset_days' => 0])
        ->all();

    $this->postJson('/api/task-templates', [
        'name' => 'Cinquecento righe',
        'items' => $rowsOf(500),
    ])->assertCreated()->assertJsonPath('data.items_count', 500);

    $this->postJson('/api/task-templates', [
        'name' => 'Cinquecentouno righe',
        'items' => $rowsOf(501),
    ])->assertStatus(422)->assertJsonValidationErrors('items');
});

// ---------------------------------------------------------------------------
// AC-008 — PATCH omission drops the whole sub-tree and its attachments;
// a key/parent_key-less PATCH (existing client) stays valid.
// ---------------------------------------------------------------------------

it('update: omitting a root drops its whole sub-tree and every level\'s attachment (AC-008)', function () {
    Storage::fake('local');
    Sanctum::actingAs(taskTemplateUserWith(['update']));

    $template = TaskTemplate::factory()->create();
    $root = TaskTemplateItem::factory()->forTemplate($template)->atPosition(0)->create(['title' => 'Radice']);
    $child = TaskTemplateItem::factory()->forTemplate($template)->atPosition(1)->create(['title' => 'Figlio', 'parent_id' => $root->id]);
    $grand = TaskTemplateItem::factory()->forTemplate($template)->atPosition(2)->create(['title' => 'Nipote', 'parent_id' => $child->id]);
    $kept = TaskTemplateItem::factory()->forTemplate($template)->atPosition(3)->create(['title' => 'Estranea']);

    $attachments = collect([$root, $child, $grand])->map(function (TaskTemplateItem $row) {
        $attachment = $row->attach(UploadedFile::fake()->create("{$row->title}.pdf", 5, 'application/pdf'), 'documents');

        return ['id' => $attachment->id, 'path' => $attachment->path];
    });

    $this->patchJson("/api/task-templates/{$template->id}", [
        'items' => [
            ['id' => $kept->id, 'title' => 'Estranea', 'due_offset_days' => 0],
        ],
    ])->assertOk()->assertJsonPath('data.items_count', 1);

    foreach ([$root, $child, $grand] as $row) {
        $this->assertDatabaseMissing('task_template_items', ['id' => $row->id]);
    }

    foreach ($attachments as $attachment) {
        $this->assertDatabaseMissing('attachments', ['id' => $attachment['id']]);
        Storage::disk('local')->assertMissing($attachment['path']);
    }

    $this->assertDatabaseHas('task_template_items', ['id' => $kept->id]);
});

it('update: a PATCH without key/parent_key on any row still validates (AC-008)', function () {
    Sanctum::actingAs(taskTemplateUserWith(['update']));

    $template = TaskTemplate::factory()->create();
    $item = TaskTemplateItem::factory()->forTemplate($template)->create(['title' => 'Sola']);

    $this->patchJson("/api/task-templates/{$template->id}", [
        'items' => [
            ['id' => $item->id, 'title' => 'Sola rinominata', 'due_offset_days' => 0],
        ],
    ])->assertOk()->assertJsonPath('data.items.0.title', 'Sola rinominata');
});

// ---------------------------------------------------------------------------
// AC-009 — deleting an unused template removes every level and its files.
// ---------------------------------------------------------------------------

it('delete: an unused template with a 3-level sub-tree removes every row and every level\'s attachment (AC-009)', function () {
    Storage::fake('local');
    Sanctum::actingAs(taskTemplateUserWith(['delete']));

    $template = TaskTemplate::factory()->create();
    $root = TaskTemplateItem::factory()->forTemplate($template)->atPosition(0)->create();
    $child = TaskTemplateItem::factory()->forTemplate($template)->atPosition(1)->create(['parent_id' => $root->id]);
    $grand = TaskTemplateItem::factory()->forTemplate($template)->atPosition(2)->create(['parent_id' => $child->id]);
    $great = TaskTemplateItem::factory()->forTemplate($template)->atPosition(3)->create(['parent_id' => $grand->id]);

    $rows = [$root, $child, $grand, $great];
    $attachments = collect($rows)->map(function (TaskTemplateItem $row) {
        $attachment = $row->attach(UploadedFile::fake()->create("doc-{$row->id}.pdf", 5, 'application/pdf'), 'documents');

        return ['id' => $attachment->id, 'path' => $attachment->path];
    });

    $this->deleteJson("/api/task-templates/{$template->id}")->assertNoContent();

    foreach ($rows as $row) {
        $this->assertDatabaseMissing('task_template_items', ['id' => $row->id]);
    }

    foreach ($attachments as $attachment) {
        $this->assertDatabaseMissing('attachments', ['id' => $attachment['id']]);
        Storage::disk('local')->assertMissing($attachment['path']);
    }
});
