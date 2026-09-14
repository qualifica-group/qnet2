<?php

use App\Enums\TaskStatusGroup;
use App\Models\TaskStatus;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// GET/POST/PUT/PATCH /api/task-templates: the "Modello di Task" CRUD (spec
// 0124, D-1). AC-001..003, AC-013.

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
// create — AC-001, AC-002, AC-003
// ---------------------------------------------------------------------------

it('create: 201, items_count = 3, items in submission order with sort_order 0,1,2 (AC-001)', function () {
    Sanctum::actingAs(taskTemplateUserWith(['create']));

    $response = $this->postJson('/api/task-templates', [
        'name' => 'Onboarding cliente',
        'description' => 'Modello standard',
        'items' => [
            ['title' => 'Prima riga', 'due_offset_days' => 0],
            ['title' => 'Seconda riga', 'due_offset_days' => 2],
            ['title' => 'Terza riga', 'due_offset_days' => 5],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Onboarding cliente')
        ->assertJsonPath('data.items_count', 3)
        ->assertJsonPath('data.items.0.title', 'Prima riga')
        ->assertJsonPath('data.items.0.sort_order', 0)
        ->assertJsonPath('data.items.1.title', 'Seconda riga')
        ->assertJsonPath('data.items.1.sort_order', 1)
        ->assertJsonPath('data.items.2.title', 'Terza riga')
        ->assertJsonPath('data.items.2.sort_order', 2);

    $this->assertDatabaseHas('task_templates', ['name' => 'Onboarding cliente']);
    expect(TaskTemplate::where('name', 'Onboarding cliente')->first()->items()->count())->toBe(3);
});

it('create: is_active defaults to true, description defaults to null', function () {
    Sanctum::actingAs(taskTemplateUserWith(['create']));

    $this->postJson('/api/task-templates', [
        'name' => 'Minimo',
        'items' => [['title' => 'Riga', 'due_offset_days' => 0]],
    ])->assertCreated()
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.description', null);
});

it('create: 422 without items / empty items / duplicate name / row without title / negative due_offset_days, no record persisted (AC-002)', function () {
    Sanctum::actingAs(taskTemplateUserWith(['create']));

    $this->postJson('/api/task-templates', ['name' => 'No items'])
        ->assertStatus(422)->assertJsonValidationErrors('items');

    $this->postJson('/api/task-templates', ['name' => 'Empty items', 'items' => []])
        ->assertStatus(422)->assertJsonValidationErrors('items');

    TaskTemplate::factory()->create(['name' => 'Taken']);
    $this->postJson('/api/task-templates', [
        'name' => 'Taken',
        'items' => [['title' => 'A', 'due_offset_days' => 0]],
    ])->assertStatus(422)->assertJsonValidationErrors('name');

    $this->postJson('/api/task-templates', [
        'name' => 'Missing title',
        'items' => [['due_offset_days' => 0]],
    ])->assertStatus(422)->assertJsonValidationErrors('items.0.title');

    $this->postJson('/api/task-templates', [
        'name' => 'Negative offset',
        'items' => [['title' => 'A', 'due_offset_days' => -1]],
    ])->assertStatus(422)->assertJsonValidationErrors('items.0.due_offset_days');

    expect(TaskTemplate::where('name', '!=', 'Taken')->count())->toBe(0);
});

it('create: items.*.id is prohibited (D-1)', function () {
    Sanctum::actingAs(taskTemplateUserWith(['create']));

    $this->postJson('/api/task-templates', [
        'name' => 'Con id',
        'items' => [['id' => 999, 'title' => 'A', 'due_offset_days' => 0]],
    ])->assertStatus(422)->assertJsonValidationErrors('items.0.id');
});

it('create: 422 items.N.task_status_id for in_validation/closed_positive/inactive statuses, 201 for open/pending (AC-003)', function () {
    Sanctum::actingAs(taskTemplateUserWith(['create']));

    $badStatuses = [
        'in_validation' => TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create(),
        'closed_positive' => TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->create(),
        'inactive_open' => TaskStatus::factory()->group(TaskStatusGroup::Open)->create(['is_active' => false]),
    ];

    foreach ($badStatuses as $label => $status) {
        $this->postJson('/api/task-templates', [
            'name' => "Bad {$label}",
            'items' => [['title' => 'Row', 'due_offset_days' => 0, 'task_status_id' => $status->id]],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.task_status_id');
    }

    $goodStatuses = [
        'open' => TaskStatus::factory()->group(TaskStatusGroup::Open)->create(),
        'pending' => TaskStatus::factory()->group(TaskStatusGroup::Pending)->create(),
    ];

    foreach ($goodStatuses as $label => $status) {
        $this->postJson('/api/task-templates', [
            'name' => "Good {$label}",
            'items' => [['title' => 'Row', 'due_offset_days' => 0, 'task_status_id' => $status->id]],
        ])->assertCreated()->assertJsonPath('data.items.0.task_status.id', $status->id);
    }
});

// ---------------------------------------------------------------------------
// show / update — header-only edits
// ---------------------------------------------------------------------------

it('show: returns the template with its ordered items and the task_status projection', function () {
    $actor = taskTemplateUserWith(['view']);
    $status = TaskStatus::factory()->group(TaskStatusGroup::Open)->create(['name' => 'Aperta', 'color' => 'blue']);
    $template = TaskTemplate::factory()->create();
    TaskTemplateItem::factory()->forTemplate($template)->atPosition(0)->inStatus($status)->create(['title' => 'Row']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/task-templates/{$template->id}")
        ->assertOk()
        ->assertJsonPath('data.items.0.title', 'Row')
        ->assertJsonPath('data.items.0.task_status.id', $status->id)
        ->assertJsonPath('data.items.0.task_status.name', 'Aperta')
        ->assertJsonPath('data.items.0.task_status.group', 'open');
});

it('update: renames the header without touching items when items is omitted', function () {
    Sanctum::actingAs(taskTemplateUserWith(['update']));
    $template = TaskTemplate::factory()->create(['name' => 'Old']);
    TaskTemplateItem::factory()->forTemplate($template)->create();

    $this->patchJson("/api/task-templates/{$template->id}", ['name' => 'New'])
        ->assertOk()
        ->assertJsonPath('data.name', 'New')
        ->assertJsonPath('data.items_count', 1);
});

// ---------------------------------------------------------------------------
// attachments on a row — AC-013
// ---------------------------------------------------------------------------

it('an attachment uploaded on a template item appears in items.*.attachments of show (AC-013)', function () {
    Storage::fake('local');
    $actor = taskTemplateUserWith(['view']);
    $template = TaskTemplate::factory()->create();
    $item = TaskTemplateItem::factory()->forTemplate($template)->create();
    $item->attach(UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'), 'documents');
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/task-templates/{$template->id}")->assertOk();

    $row = collect($response->json('data.items'))->firstWhere('id', $item->id);
    expect($row['attachments'])->toHaveCount(1)
        ->and($row['attachments'][0]['original_name'])->toBe('doc.pdf');
});
