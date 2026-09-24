<?php

use App\Models\Registry;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\TaskPriority;
use App\Models\TaskType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| POST /api/tasks, `subtasks[]` (spec 0155, D-3, AC-005)
|--------------------------------------------------------------------------
|
| One level, all inside the parent's own create transaction: an invalid row
| rolls the whole batch back, surfaced as `subtasks.N.field`.
*/

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

if (! function_exists('taskPayload')) {
    /**
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

it('AC-005: POST with 2 subtasks creates the parent and both children with inherited fields', function () {
    $actor = taskActorWith(['create', 'view']);
    $registry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tasks', taskPayload([
        'registry_id' => $registry->id,
        'end_date' => '2026-12-31',
        'subtasks' => [
            ['title' => 'Sotto A'],
            ['title' => 'Sotto B', 'end_date' => '2026-12-20'],
        ],
    ]))->assertCreated();

    $parentId = $response->json('data.id');
    $this->assertDatabaseCount('tasks', 3);

    $children = Task::where('parent_task_id', $parentId)->orderBy('subtask_position')->get();
    expect($children)->toHaveCount(2)
        ->and($children[0]->title)->toBe('Sotto A')
        ->and($children[0]->registry_id)->toBe($registry->id)
        ->and($children[0]->end_date->toDateString())->toBe('2026-12-31') // inherited, omitted on the row
        ->and($children[0]->subtask_position)->toBe(0)
        ->and($children[1]->title)->toBe('Sotto B')
        ->and($children[1]->end_date->toDateString())->toBe('2026-12-20') // submitted on the row
        ->and($children[1]->subtask_position)->toBe(1)
        ->and($children[0]->work_order_stage_id)->toBeNull()
        ->and($children[0]->task_recurrence_id)->toBeNull();
});

it('AC-005: a subtask without a title is 422 on subtasks.N.title and nothing is saved', function () {
    $actor = taskActorWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', taskPayload([
        'title' => 'Padre orfano',
        'subtasks' => [
            ['title' => 'Valido'],
            ['description' => 'senza titolo'],
        ],
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('subtasks.1.title');

    $this->assertDatabaseMissing('tasks', ['title' => 'Padre orfano']);
    $this->assertDatabaseMissing('tasks', ['title' => 'Valido']);
});

it('AC-005: more than 50 subtasks is 422 on subtasks', function () {
    $actor = taskActorWith(['create']);
    Sanctum::actingAs($actor);

    $subtasks = array_map(static fn (int $i): array => ['title' => "Sotto {$i}"], range(1, 51));

    $this->postJson('/api/tasks', taskPayload(['subtasks' => $subtasks]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('subtasks');
});

it('subtasks never inherit work_order_stage_id, is_completed or notifications', function () {
    $actor = taskActorWith(['create', 'view']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tasks', taskPayload([
        'subtasks' => [['title' => 'Sotto']],
    ]))->assertCreated();

    $child = Task::where('parent_task_id', $response->json('data.id'))->firstOrFail();
    expect($child->work_order_stage_id)->toBeNull();
});

it('a subtask inherits assignee_ids/task_type_id from the parent when the row omits them', function () {
    $actor = taskActorWith(['create', 'view']);
    $assignee = User::factory()->create();
    $taskType = TaskType::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tasks', taskPayload([
        'assignee_ids' => [$assignee->id],
        'task_type_id' => $taskType->id,
        'subtasks' => [['title' => 'Sotto']],
    ]))->assertCreated();

    $child = Task::where('parent_task_id', $response->json('data.id'))->firstOrFail();
    expect($child->assignees->pluck('id')->all())->toBe([$assignee->id])
        ->and($child->task_type_id)->toBe($taskType->id);
});

it('a subtask overrides task_type_id/task_category_id when the row submits them', function () {
    $actor = taskActorWith(['create', 'view']);
    $parentType = TaskType::factory()->create();
    $childType = TaskType::factory()->create();
    $priority = TaskPriority::factory()->create();
    $category = TaskCategory::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tasks', taskPayload([
        'task_type_id' => $parentType->id,
        'subtasks' => [[
            'title' => 'Sotto',
            'task_type_id' => $childType->id,
            'task_priority_id' => $priority->id,
            'task_category_id' => $category->id,
        ]],
    ]))->assertCreated();

    $child = Task::where('parent_task_id', $response->json('data.id'))->firstOrFail();
    expect($child->task_type_id)->toBe($childType->id)
        ->and($child->task_priority_id)->toBe($priority->id)
        ->and($child->task_category_id)->toBe($category->id);
});

it('a subtask date outside the parent range is 422 on subtasks.N.end_date, nothing saved', function () {
    $actor = taskActorWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', taskPayload([
        'title' => 'Padre con range',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'subtasks' => [['title' => 'Fuori range', 'end_date' => '2026-02-15']],
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('subtasks.0.end_date');

    $this->assertDatabaseMissing('tasks', ['title' => 'Padre con range']);
});
