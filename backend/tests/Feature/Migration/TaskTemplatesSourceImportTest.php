<?php

use App\Jobs\RunMigrationJob;
use App\Models\MigrationRun;
use App\Models\Role;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\User;
use App\Services\MigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// The shared helpers (fakeMigrationsBaseUrl/seedMigrationsConfig) are defined
// once in tests/Helpers/MigrationHelpers.php; migrationsSuperAdminActor/
// runMigrationJobFor follow the SectorsSourceImportTest convention (each
// import-test file declares its own, guarded by function_exists).

if (! function_exists('migrationsSuperAdminActor')) {
    function migrationsSuperAdminActor(): User
    {
        Role::query()->firstOrCreate(['name' => 'super-admin']);

        $actor = User::factory()->create();
        $actor->assignRole('super-admin');

        return $actor;
    }
}

if (! function_exists('runMigrationJobFor')) {
    function runMigrationJobFor(MigrationRun $run): void
    {
        (new RunMigrationJob($run->id))->handle(app(MigrationService::class));
    }
}

// ---------------------------------------------------------------------------
// AC-011 — listed among the sources, preview with counts, nested sample
// ---------------------------------------------------------------------------

it('AC-011: task-templates is listed among the migration sources', function () {
    Sanctum::actingAs(migrationsSuperAdminActor());

    $this->getJson('/api/migrations')
        ->assertOk()
        ->assertJsonFragment(['key' => 'task-templates', 'label' => 'Task templates']);
});

it('AC-011: preview maps one nested record into id/name + stage/action/sub-action counts', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/task-templates*' => Http::response([
            'items' => [[
                'id' => 1,
                'name' => 'Model One',
                'stages' => [[
                    'id' => 10,
                    'name' => 'Stage A',
                    'position' => 0,
                    'items' => [
                        ['id' => 100, 'parent_id' => null, 'title' => 'Root', 'description' => null, 'estimated_hours' => null, 'estimated_minutes' => null, 'position' => 0],
                        ['id' => 101, 'parent_id' => 100, 'title' => 'Child', 'description' => null, 'estimated_hours' => null, 'estimated_minutes' => null, 'position' => 0],
                    ],
                ]],
            ]],
            'pagination' => ['total' => 1, 'offset' => 0, 'limit' => 10, 'total_pages' => 1],
        ]),
    ]);
    Sanctum::actingAs(migrationsSuperAdminActor());

    $this->getJson('/api/migrations/task-templates/preview?page=1&per_page=10')
        ->assertOk()
        ->assertJsonPath('data.rows.0.id', 1)
        ->assertJsonPath('data.rows.0.name', 'Model One')
        ->assertJsonPath('data.rows.0.stages_count', 1)
        ->assertJsonPath('data.rows.0.items_count', 2)
        ->assertJsonPath('data.rows.0.sub_items_count', 1);
});

it('AC-011: sampleResponse() mirrors the nested contract shape', function () {
    Sanctum::actingAs(migrationsSuperAdminActor());

    $this->getJson('/api/migrations/task-templates/columns')
        ->assertOk()
        ->assertJsonPath('data.sample.items.0.name', 'Sample task template')
        ->assertJsonPath('data.sample.items.0.stages.0.name', 'Sample stage')
        ->assertJsonPath('data.sample.items.0.stages.0.items.0.parent_id', null)
        ->assertJsonPath('data.sample.items.0.stages.0.items.1.parent_id', 100)
        ->assertJsonPath('data.sample.pagination.total', 1);
});

// ---------------------------------------------------------------------------
// AC-012 — full import: stage/item order, parent_id, no stage on children,
// old_id, estimated_minutes, description escaped to plain text
// ---------------------------------------------------------------------------

it('AC-012: imports stages and items in order, remapping parents and estimating minutes', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/task-templates*' => Http::response([
            'items' => [[
                'id' => 1,
                'name' => 'Model One',
                'stages' => [
                    [
                        'id' => 10,
                        'name' => 'Stage A',
                        'position' => 0,
                        'items' => [
                            ['id' => 100, 'parent_id' => null, 'title' => 'Root 1', 'description' => 'a < b', 'estimated_hours' => 1, 'estimated_minutes' => 30, 'position' => 1],
                            ['id' => 101, 'parent_id' => null, 'title' => 'Root 2', 'description' => null, 'estimated_hours' => null, 'estimated_minutes' => null, 'position' => 0],
                            ['id' => 102, 'parent_id' => 101, 'title' => 'Child of Root 2', 'description' => '', 'estimated_hours' => null, 'estimated_minutes' => null, 'position' => 0],
                            ['id' => 103, 'parent_id' => 102, 'title' => 'Grandchild', 'description' => null, 'estimated_hours' => null, 'estimated_minutes' => null, 'position' => 0],
                        ],
                    ],
                    [
                        'id' => 11,
                        'name' => 'Stage B',
                        'position' => 1,
                        'items' => [
                            ['id' => 104, 'parent_id' => null, 'title' => 'Root 3', 'description' => null, 'estimated_hours' => null, 'estimated_minutes' => null, 'position' => 0],
                        ],
                    ],
                ],
            ]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'task-templates']);

    runMigrationJobFor($run);

    $taskTemplate = TaskTemplate::query()->where('old_id', 1)->first();
    expect($taskTemplate)->not->toBeNull()
        ->and($taskTemplate->name)->toBe('Model One')
        ->and($taskTemplate->is_active)->toBeTrue();

    $stages = $taskTemplate->stages()->orderBy('sort_order')->get();
    expect($stages)->toHaveCount(2)
        ->and($stages[0]->name)->toBe('Stage A')
        ->and($stages[1]->name)->toBe('Stage B');

    // Display order (D-2): per stage, roots by position then id, each
    // followed depth-first by its own subtree — Root 2(0), Child(0),
    // Grandchild(0), Root 1(1), then Stage B's Root 3.
    $items = $taskTemplate->items()->orderBy('sort_order')->get();
    expect($items->pluck('title')->all())->toBe(['Root 2', 'Child of Root 2', 'Grandchild', 'Root 1', 'Root 3']);

    $root2 = $items->firstWhere('title', 'Root 2');
    $child = $items->firstWhere('title', 'Child of Root 2');
    $grandchild = $items->firstWhere('title', 'Grandchild');
    $root1 = $items->firstWhere('title', 'Root 1');
    $root3 = $items->firstWhere('title', 'Root 3');

    expect($root2->parent_id)->toBeNull()
        ->and($root2->task_template_stage_id)->toBe($stages[0]->id)
        ->and($child->parent_id)->toBe($root2->id)
        ->and($child->task_template_stage_id)->toBeNull()
        ->and($grandchild->parent_id)->toBe($child->id)
        ->and($grandchild->task_template_stage_id)->toBeNull()
        ->and($root1->parent_id)->toBeNull()
        ->and($root1->task_template_stage_id)->toBe($stages[0]->id)
        ->and($root1->estimated_minutes)->toBe(90)
        ->and($root1->description)->toBe('<p>a &lt; b</p>')
        ->and($root2->estimated_minutes)->toBeNull()
        ->and($root3->task_template_stage_id)->toBe($stages[1]->id);

    expect($run->fresh()->created_rows)->toBe(1);
});

// ---------------------------------------------------------------------------
// AC-013 — idempotent re-import
// ---------------------------------------------------------------------------

it('AC-013: re-importing the same model is skipped, no new rows created', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/task-templates*' => Http::response([
            'items' => [['id' => 2, 'name' => 'Repeatable Model', 'stages' => []]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    runMigrationJobFor(MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'task-templates']));

    $secondRun = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'task-templates']);
    runMigrationJobFor($secondRun);

    expect(TaskTemplate::query()->where('old_id', 2)->count())->toBe(1)
        ->and($secondRun->fresh()->skipped_rows)->toBe(1)
        ->and($secondRun->fresh()->created_rows)->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-014 — duplicate name (case-insensitive) suffixed with a warning
// ---------------------------------------------------------------------------

it('AC-014: a name that already exists (different case) is imported suffixed with old_id', function () {
    TaskTemplate::factory()->create(['name' => 'existing template']);

    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/task-templates*' => Http::response([
            'items' => [['id' => 5, 'name' => 'Existing Template', 'stages' => []]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'task-templates']);

    runMigrationJobFor($run);

    $created = TaskTemplate::query()->where('old_id', 5)->first();
    expect($created)->not->toBeNull()
        ->and($created->name)->toBe('Existing Template (old_id 5)');

    $warnings = collect($run->fresh()->report)->where('level', 'warning')->pluck('message');
    expect($warnings->contains(fn (string $message): bool => str_contains($message, 'Duplicate task template name')))->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-015 — anomalous sub-actions: unresolved parent, self-reference, depth
// cap, cross-stage child
// ---------------------------------------------------------------------------

it('AC-015: an unresolved parent, a self-reference and a 4th nesting level are all promoted to root', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/task-templates*' => Http::response([
            'items' => [[
                'id' => 6,
                'name' => 'Anomalies',
                'stages' => [[
                    'id' => 20,
                    'name' => 'Stage X',
                    'position' => 0,
                    'items' => [
                        ['id' => 200, 'parent_id' => 9999, 'title' => 'Orphan', 'description' => null, 'estimated_hours' => null, 'estimated_minutes' => null, 'position' => 0],
                        ['id' => 201, 'parent_id' => 201, 'title' => 'Self Ref', 'description' => null, 'estimated_hours' => null, 'estimated_minutes' => null, 'position' => 1],
                        ['id' => 210, 'parent_id' => null, 'title' => 'D0', 'description' => null, 'estimated_hours' => null, 'estimated_minutes' => null, 'position' => 2],
                        ['id' => 211, 'parent_id' => 210, 'title' => 'D1', 'description' => null, 'estimated_hours' => null, 'estimated_minutes' => null, 'position' => 0],
                        ['id' => 212, 'parent_id' => 211, 'title' => 'D2', 'description' => null, 'estimated_hours' => null, 'estimated_minutes' => null, 'position' => 0],
                        ['id' => 213, 'parent_id' => 212, 'title' => 'D3', 'description' => null, 'estimated_hours' => null, 'estimated_minutes' => null, 'position' => 0],
                        ['id' => 214, 'parent_id' => 213, 'title' => 'D4 too deep', 'description' => null, 'estimated_hours' => null, 'estimated_minutes' => null, 'position' => 0],
                    ],
                ]],
            ]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'task-templates']);

    runMigrationJobFor($run);

    $taskTemplate = TaskTemplate::query()->where('old_id', 6)->first();
    $items = $taskTemplate->items()->get()->keyBy(fn (TaskTemplateItem $item): string => $item->title);

    expect($items['Orphan']->parent_id)->toBeNull()
        ->and($items['Self Ref']->parent_id)->toBeNull()
        ->and($items['D0']->parent_id)->toBeNull()
        ->and($items['D1']->parent_id)->toBe($items['D0']->id)
        ->and($items['D2']->parent_id)->toBe($items['D1']->id)
        ->and($items['D3']->parent_id)->toBe($items['D2']->id)
        ->and($items['D4 too deep']->parent_id)->toBeNull();

    $warnings = collect($run->fresh()->report)->where('level', 'warning')->pluck('message')->implode(' | ');
    expect($warnings)->toContain('Action 200 promoted')
        ->toContain('parent_id 9999')
        ->toContain('Action 201 promoted')
        ->toContain('self-referencing')
        ->toContain('Action 214 promoted')
        ->toContain('nesting deeper than 3 levels');
});

it('AC-015: a child listed under a different legacy stage than its root still follows the root, with a warning', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/task-templates*' => Http::response([
            'items' => [[
                'id' => 7,
                'name' => 'Cross Stage',
                'stages' => [
                    ['id' => 30, 'name' => 'Alpha', 'position' => 0, 'items' => [
                        ['id' => 300, 'parent_id' => null, 'title' => 'Alpha Root', 'description' => null, 'estimated_hours' => null, 'estimated_minutes' => null, 'position' => 0],
                    ]],
                    ['id' => 31, 'name' => 'Beta', 'position' => 1, 'items' => [
                        ['id' => 301, 'parent_id' => 300, 'title' => 'Beta Child', 'description' => null, 'estimated_hours' => null, 'estimated_minutes' => null, 'position' => 0],
                    ]],
                ],
            ]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'task-templates']);

    runMigrationJobFor($run);

    $taskTemplate = TaskTemplate::query()->where('old_id', 7)->first();
    $root = $taskTemplate->items()->where('title', 'Alpha Root')->first();
    $child = $taskTemplate->items()->where('title', 'Beta Child')->first();

    expect($child->parent_id)->toBe($root->id)
        ->and($child->task_template_stage_id)->toBeNull();

    $warnings = collect($run->fresh()->report)->where('level', 'warning')->pluck('message')->implode(' | ');
    expect($warnings)->toContain('Sub-action 301 belongs to a different legacy stage');
});

// ---------------------------------------------------------------------------
// AC-016 — is_active derived from the title
// ---------------------------------------------------------------------------

it('AC-016: is_active is false only when the title contains "non attivo"', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/task-templates*' => Http::response([
            'items' => [
                ['id' => 8, 'name' => 'Process (NON Attivo)', 'stages' => []],
                ['id' => 9, 'name' => 'Process (Attivo)', 'stages' => []],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'task-templates']);

    runMigrationJobFor($run);

    expect(TaskTemplate::query()->where('old_id', 8)->value('is_active'))->toBeFalsy()
        ->and(TaskTemplate::query()->where('old_id', 8)->value('name'))->toBe('Process (NON Attivo)')
        ->and(TaskTemplate::query()->where('old_id', 9)->value('is_active'))->toBeTruthy();
});

// ---------------------------------------------------------------------------
// AC-017 — title truncation, model without actions, missing id isolates
// the row
// ---------------------------------------------------------------------------

it('AC-017: truncates an overlong title, creates an action-less model, and isolates a row with no id', function () {
    seedMigrationsConfig();
    $longTitle = str_repeat('A', 193);

    Http::fake([
        fakeMigrationsBaseUrl().'/task-templates*' => Http::response([
            'items' => [
                ['name' => 'Missing id', 'stages' => []],
                ['id' => 30, 'name' => 'No Actions', 'stages' => []],
                ['id' => 31, 'name' => 'Long Title', 'stages' => [[
                    'id' => 40, 'name' => 'Only Stage', 'position' => 0, 'items' => [
                        ['id' => 400, 'parent_id' => null, 'title' => $longTitle, 'description' => null, 'estimated_hours' => null, 'estimated_minutes' => null, 'position' => 0],
                    ],
                ]]],
            ],
            'pagination' => ['total' => 3],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'task-templates']);

    runMigrationJobFor($run);

    $fresh = $run->fresh();
    expect($fresh->created_rows)->toBe(2)
        ->and($fresh->failed_rows)->toBe(1);

    $noActions = TaskTemplate::query()->where('old_id', 30)->first();
    expect($noActions)->not->toBeNull()
        ->and($noActions->items()->count())->toBe(0);

    $longTitleTemplate = TaskTemplate::query()->where('old_id', 31)->first();
    $item = $longTitleTemplate->items()->first();
    expect(mb_strlen($item->title))->toBe(191)
        ->and($item->title)->toBe(mb_substr($longTitle, 0, 191));

    $messages = collect($fresh->report)->pluck('message')->implode(' | ');
    expect($messages)->toContain('Failed to import the record')
        ->toContain('Task template has no actions')
        ->toContain('Action title truncated to 191 characters');
});

it('drops an action with a blank title, with a warning', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/task-templates*' => Http::response([
            'items' => [[
                'id' => 50,
                'name' => 'Blank Title Model',
                'stages' => [[
                    'id' => 60, 'name' => 'Stage', 'position' => 0, 'items' => [
                        ['id' => 500, 'parent_id' => null, 'title' => '   ', 'description' => null, 'estimated_hours' => null, 'estimated_minutes' => null, 'position' => 0],
                        ['id' => 501, 'parent_id' => null, 'title' => 'Kept', 'description' => null, 'estimated_hours' => null, 'estimated_minutes' => null, 'position' => 1],
                    ],
                ]],
            ]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'task-templates']);

    runMigrationJobFor($run);

    $taskTemplate = TaskTemplate::query()->where('old_id', 50)->first();
    expect($taskTemplate->items()->count())->toBe(1)
        ->and($taskTemplate->items()->first()->title)->toBe('Kept');

    $warnings = collect($run->fresh()->report)->where('level', 'warning')->pluck('message')->implode(' | ');
    expect($warnings)->toContain('Action without a title (external id 500)');
});
