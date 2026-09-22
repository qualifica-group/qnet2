<?php

use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\TaskTemplateStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// POST/PUT/PATCH /api/task-templates(/{taskTemplate}): `stages` full sync +
// `items.*.stage_key` (spec 0146, D-2). AC-001, AC-002, AC-003.

if (! function_exists('taskTemplateStageUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function taskTemplateStageUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
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
// AC-001: create with stages
// ---------------------------------------------------------------------------

it('create: stages come back in the submitted order, each item carries the right task_template_stage_id (AC-001)', function () {
    Sanctum::actingAs(taskTemplateStageUserWith(['create']));

    $response = $this->postJson('/api/task-templates', [
        'name' => 'Con fasi',
        'stages' => [
            ['key' => 's1', 'name' => 'Prima fase'],
            ['key' => 's2', 'name' => 'Seconda fase'],
        ],
        'items' => [
            ['title' => 'Riga uno', 'due_offset_days' => 0, 'stage_key' => 's1'],
            ['title' => 'Riga due', 'due_offset_days' => 1, 'stage_key' => 's2'],
            ['title' => 'Riga senza fase', 'due_offset_days' => 2],
        ],
    ])->assertCreated();

    $response->assertJsonPath('data.stages.0.name', 'Prima fase')
        ->assertJsonPath('data.stages.0.sort_order', 0)
        ->assertJsonPath('data.stages.1.name', 'Seconda fase')
        ->assertJsonPath('data.stages.1.sort_order', 1);

    $template = TaskTemplate::where('name', 'Con fasi')->firstOrFail();
    $stages = $template->stages()->orderBy('sort_order')->get();
    expect($stages)->toHaveCount(2);

    $items = $template->items()->orderBy('sort_order')->get();
    expect($items[0]->task_template_stage_id)->toBe($stages[0]->id)
        ->and($items[1]->task_template_stage_id)->toBe($stages[1]->id)
        ->and($items[2]->task_template_stage_id)->toBeNull();
});

it('create: stages.*.id is prohibited, since a brand-new template owns no stage yet', function () {
    Sanctum::actingAs(taskTemplateStageUserWith(['create']));

    $this->postJson('/api/task-templates', [
        'name' => 'Fasi con id',
        'stages' => [
            ['id' => 999999, 'key' => 's1', 'name' => 'Fase'],
        ],
        'items' => [
            ['title' => 'Riga', 'due_offset_days' => 0],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('stages.0.id');
});

// ---------------------------------------------------------------------------
// AC-002: update omits a stage -> deleted, its items fall back to null
// ---------------------------------------------------------------------------

it('update: omitting an existing stage deletes it, its items fall back to task_template_stage_id null (AC-002)', function () {
    Sanctum::actingAs(taskTemplateStageUserWith(['update']));

    $template = TaskTemplate::factory()->create();
    $keep = TaskTemplateStage::factory()->forTemplate($template)->atPosition(0)->create(['name' => 'Da tenere']);
    $drop = TaskTemplateStage::factory()->forTemplate($template)->atPosition(1)->create(['name' => 'Da eliminare']);
    $item = TaskTemplateItem::factory()->forTemplate($template)->atPosition(0)->create(['task_template_stage_id' => $drop->id]);

    $this->patchJson("/api/task-templates/{$template->id}", [
        'stages' => [
            ['id' => $keep->id, 'key' => 'k', 'name' => 'Da tenere'],
        ],
        'items' => [
            ['id' => $item->id, 'title' => $item->title, 'due_offset_days' => 0],
        ],
    ])->assertOk()
        ->assertJsonPath('data.stages.0.id', $keep->id)
        ->assertJsonPath('data.items.0.task_template_stage_id', null);

    $this->assertDatabaseMissing('task_template_stages', ['id' => $drop->id]);
    $this->assertDatabaseHas('task_template_items', ['id' => $item->id, 'task_template_stage_id' => null]);
});

it('update: an omitted `stages` key leaves existing stages untouched', function () {
    Sanctum::actingAs(taskTemplateStageUserWith(['update']));

    $template = TaskTemplate::factory()->create();
    $stage = TaskTemplateStage::factory()->forTemplate($template)->create(['name' => 'Invariata']);

    $this->patchJson("/api/task-templates/{$template->id}", [
        'name' => 'Rinominata soltanto',
    ])->assertOk();

    $this->assertDatabaseHas('task_template_stages', ['id' => $stage->id, 'name' => 'Invariata']);
});

// ---------------------------------------------------------------------------
// AC-003: unresolved stage_key / foreign stage id -> 422
// ---------------------------------------------------------------------------

it('create: an items.*.stage_key not present in stages -> 422 on items.N.stage_key (AC-003)', function () {
    Sanctum::actingAs(taskTemplateStageUserWith(['create']));

    $this->postJson('/api/task-templates', [
        'name' => 'Chiave orfana',
        'stages' => [
            ['key' => 's1', 'name' => 'Fase'],
        ],
        'items' => [
            ['title' => 'Riga', 'due_offset_days' => 0, 'stage_key' => 'inesistente'],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('items.0.stage_key');

    $this->assertDatabaseMissing('task_templates', ['name' => 'Chiave orfana']);
});

it('update: items.*.stage_key never resolves when `stages` is omitted from the same request -> 422 (AC-003)', function () {
    Sanctum::actingAs(taskTemplateStageUserWith(['update']));

    $template = TaskTemplate::factory()->create();
    $stage = TaskTemplateStage::factory()->forTemplate($template)->create();
    $item = TaskTemplateItem::factory()->forTemplate($template)->create();

    $this->patchJson("/api/task-templates/{$template->id}", [
        'items' => [
            ['id' => $item->id, 'title' => $item->title, 'due_offset_days' => 0, 'stage_key' => 'k'],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('items.0.stage_key');

    expect($item->fresh()->task_template_stage_id)->toBeNull()
        ->and(TaskTemplateStage::query()->find($stage->id))->not->toBeNull();
});

it('update: stages.*.id belonging to another template -> 422 stages.N.id, neither template changes (AC-003)', function () {
    Sanctum::actingAs(taskTemplateStageUserWith(['update']));

    $templateOne = TaskTemplate::factory()->create();
    $templateTwo = TaskTemplate::factory()->create();
    $foreignStage = TaskTemplateStage::factory()->forTemplate($templateTwo)->create(['name' => 'Foreign']);

    $this->patchJson("/api/task-templates/{$templateOne->id}", [
        'stages' => [
            ['id' => $foreignStage->id, 'key' => 'k', 'name' => 'Hijack'],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('stages.0.id');

    expect($foreignStage->fresh()->name)->toBe('Foreign')
        ->and($templateOne->stages()->count())->toBe(0);
});

it('update: duplicate stages.*.key in the same request -> 422 (distinct)', function () {
    Sanctum::actingAs(taskTemplateStageUserWith(['update']));

    $template = TaskTemplate::factory()->create();

    $this->patchJson("/api/task-templates/{$template->id}", [
        'stages' => [
            ['key' => 'dup', 'name' => 'Prima'],
            ['key' => 'dup', 'name' => 'Seconda'],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors(['stages.0.key', 'stages.1.key']);
});
