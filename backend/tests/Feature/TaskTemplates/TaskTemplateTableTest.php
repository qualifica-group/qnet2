<?php

use App\Jobs\GenerateExportJob;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// Backend-driven table for the `task-templates` domain (spec 0124, AC-008):
// GET /api/tables/task-templates/columns + POST /api/tables/task-templates/rows
// + POST /api/exports/task-templates.

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
// columns config — AC-008
// ---------------------------------------------------------------------------

it('exposes the 6 declared columns with the contracted flags, 403 without viewAny (AC-008)', function () {
    Sanctum::actingAs(taskTemplateUserWith([]));
    $this->getJson('/api/tables/task-templates/columns')->assertForbidden();

    Sanctum::actingAs(taskTemplateUserWith(['viewAny']));

    $data = $this->getJson('/api/tables/task-templates/columns')->assertOk()->json('data');

    expect($data['resource'])->toBe('task-templates')
        ->and($data['defaultSort'])->toBe([['columnId' => 'name', 'direction' => 'asc']])
        ->and($data['defaultPagination']['limit'])->toBe(25);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe(['id', 'name', 'description', 'items_count', 'is_active', 'created_at', 'updated_at']);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['name']['sortable'])->toBeTrue()
        ->and($columns['name']['filterType'])->toBe('text')
        ->and($columns['description']['filterType'])->toBe('text')
        ->and($columns['description']['sortable'])->toBeFalse()
        ->and($columns['items_count']['sortable'])->toBeTrue()
        ->and($columns['items_count']['filterType'])->toBe('number')
        ->and($columns['is_active']['sortable'])->toBeTrue()
        ->and($columns['is_active']['filterType'])->toBe('boolean')
        ->and($columns['created_at']['filterType'])->toBe('date')
        ->and($columns['updated_at']['filterType'])->toBe('date');
});

it('hides action keys the actor has no permission for', function () {
    $actor = taskTemplateUserWith(['viewAny', 'view']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/task-templates/columns')->json('data');
    $actionKeys = collect($data['actions'])->pluck('key')->all();

    expect($actionKeys)->toContain('view')
        ->and($actionKeys)->not->toContain('edit')
        ->and($actionKeys)->not->toContain('delete');
});

// ---------------------------------------------------------------------------
// rows — sort/filter by items_count and is_active (AC-008)
// ---------------------------------------------------------------------------

it('sorts by items_count (the withCount aggregate)', function () {
    $actor = taskTemplateUserWith(['viewAny']);
    $few = TaskTemplate::factory()->create(['name' => 'Few']);
    TaskTemplateItem::factory()->forTemplate($few)->create();
    $many = TaskTemplate::factory()->create(['name' => 'Many']);
    TaskTemplateItem::factory()->count(3)->forTemplate($many)->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/task-templates/rows', [
        'startRow' => 0, 'endRow' => 25,
        'sortModel' => [['colId' => 'items_count', 'sort' => 'desc']],
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('name')->all())->toBe(['Many', 'Few']);
});

it('filters by items_count via a relation-count comparison', function () {
    $actor = taskTemplateUserWith(['viewAny']);
    $busy = TaskTemplate::factory()->create(['name' => 'Busy']);
    TaskTemplateItem::factory()->count(2)->forTemplate($busy)->create();
    TaskTemplate::factory()->create(['name' => 'Empty']); // 0 items
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/task-templates/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['items_count' => ['filterType' => 'number', 'type' => 'greaterThan', 'filter' => 1]],
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('name')->all())->toBe(['Busy']);
});

it('filters by is_active', function () {
    $actor = taskTemplateUserWith(['viewAny']);
    TaskTemplate::factory()->create(['name' => 'Active', 'is_active' => true]);
    TaskTemplate::factory()->create(['name' => 'Inactive', 'is_active' => false]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/task-templates/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['is_active' => ['filterType' => 'boolean', 'filter' => false]],
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('name')->all())->toBe(['Inactive']);
});

it('rows expose id/name/description/items_count/is_active + per-row actions', function () {
    $actor = taskTemplateUserWith(['viewAny', 'view', 'update', 'delete']);
    $template = TaskTemplate::factory()->create(['name' => 'Onboarding', 'description' => 'Standard']);
    TaskTemplateItem::factory()->count(2)->forTemplate($template)->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/task-templates/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('name', 'Onboarding');

    expect($row)->not->toBeNull()
        ->and($row['description'])->toBe('Standard')
        ->and($row['items_count'])->toBe(2)
        ->and($row['is_active'])->toBeTrue()
        ->and($row['actions'])->toEqualCanonicalizing(['view', 'edit', 'delete']);
});

// ---------------------------------------------------------------------------
// export — "for free" via config/tables.php registration (AC-008)
// ---------------------------------------------------------------------------

it('export: task-templates is registered in the generic export engine, no dedicated code (AC-008)', function () {
    Queue::fake();
    $actor = taskTemplateUserWith(['export']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/task-templates', [
        'format' => 'csv',
        'columns' => [['colId' => 'name', 'header' => 'Name']],
    ])
        ->assertCreated()
        ->assertJsonPath('data.export_run.resource', 'task-templates');

    Queue::assertPushed(GenerateExportJob::class);
});

it('export: 403 without task-templates.export, no export job pushed', function () {
    Queue::fake();
    Sanctum::actingAs(taskTemplateUserWith([]));

    $this->postJson('/api/exports/task-templates', [
        'format' => 'csv',
        'columns' => [['colId' => 'name', 'header' => 'Name']],
    ])->assertForbidden();

    Queue::assertNotPushed(GenerateExportJob::class);
});

// ---------------------------------------------------------------------------
// bulk-delete propagates the delete guard — AC-011
// ---------------------------------------------------------------------------

it('bulk-delete: a template referenced by a Commessa is NOT removed, an unreferenced one is (AC-011)', function () {
    $actor = taskTemplateUserWith(['viewAny', 'delete']);
    $used = TaskTemplate::factory()->create();
    WorkOrder::factory()->create(['task_template_id' => $used->id]);
    $free = TaskTemplate::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/task-templates/bulk-delete', ['ids' => [$used->id, $free->id]])
        ->assertOk();

    $this->assertDatabaseHas('task_templates', ['id' => $used->id]);
    $this->assertDatabaseMissing('task_templates', ['id' => $free->id]);
});
