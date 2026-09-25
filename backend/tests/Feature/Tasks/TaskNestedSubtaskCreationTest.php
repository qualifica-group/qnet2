<?php

use App\Enums\TaskRecurrenceFrequency;
use App\Enums\TaskStatusGroup;
use App\Models\Task;
use App\Models\TaskRecurrence;
use App\Models\TaskStatus;
use App\Models\TaskType;
use App\Models\User;
use App\Services\Tasks\TaskOccurrenceFactory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| `subtasks.*.subtasks` recursive, up to 3 levels (spec 0161, D-1..D-4,
| AC-001..AC-006)
|--------------------------------------------------------------------------
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

if (! function_exists('recurrencePayload')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function recurrencePayload(array $overrides = []): array
    {
        return [
            'frequency' => 'weekly',
            'interval' => 1,
            'weekdays' => [1],
            'ends' => 'never',
            ...$overrides,
        ];
    }
}

if (! function_exists('openStatusFor0161')) {
    function openStatusFor0161(): TaskStatus
    {
        return TaskStatus::factory()->group(TaskStatusGroup::Open)->create();
    }
}

it('AC-001: POST with a 3-level tree creates every node with the correct parent_task_id, one transaction', function () {
    $actor = taskActorWith(['create', 'view']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tasks', taskPayload([
        'subtasks' => [[
            'title' => 'Figlio',
            'subtasks' => [[
                'title' => 'Nipote',
                'subtasks' => [[
                    'title' => 'Pronipote',
                ]],
            ]],
        ]],
    ]))->assertCreated();

    $this->assertDatabaseCount('tasks', 4);

    $parentId = $response->json('data.id');
    $child = Task::where('parent_task_id', $parentId)->sole();
    $grandchild = Task::where('parent_task_id', $child->id)->sole();
    $greatGrandchild = Task::where('parent_task_id', $grandchild->id)->sole();

    expect($child->title)->toBe('Figlio')
        ->and($grandchild->title)->toBe('Nipote')
        ->and($greatGrandchild->title)->toBe('Pronipote');
});

it('AC-002: a 4th level is refused with 422 on the nested key, nothing created', function () {
    $actor = taskActorWith(['create']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tasks', taskPayload([
        'subtasks' => [[
            'title' => 'Figlio',
            'subtasks' => [[
                'title' => 'Nipote',
                'subtasks' => [[
                    'title' => 'Pronipote',
                    'subtasks' => [[
                        'title' => 'Livello 4, vietato',
                    ]],
                ]],
            ]],
        ]],
    ]));

    $response->assertStatus(422)
        ->assertJsonValidationErrors('subtasks.0.subtasks.0.subtasks.0.subtasks');

    $this->assertDatabaseCount('tasks', 0);
});

it('AC-003: 51 nodes spread across levels is 422 on subtasks, nothing created', function () {
    $actor = taskActorWith(['create']);
    Sanctum::actingAs($actor);

    // 49 top-level children plus one with a single nested grandchild: 51
    // nodes total, spread across two levels, none of which alone hits 50.
    $subtasks = array_map(static fn (int $i): array => ['title' => "Sotto {$i}"], range(1, 49));
    $subtasks[] = ['title' => 'Con nipote', 'subtasks' => [['title' => 'Nipote 51']]];

    $this->postJson('/api/tasks', taskPayload(['subtasks' => $subtasks]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('subtasks');

    $this->assertDatabaseCount('tasks', 0);
});

it('AC-004: a grandchild without assignees inherits from its direct parent, which inherits from the task', function () {
    $actor = taskActorWith(['create', 'view']);
    $taskAssignee = User::factory()->create();
    $childAssignee = User::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tasks', taskPayload([
        'assignee_ids' => [$taskAssignee->id],
        'subtasks' => [[
            'title' => 'Figlio',
            'assignee_ids' => [$childAssignee->id],
            'subtasks' => [[
                'title' => 'Nipote',
            ]],
        ]],
    ]))->assertCreated();

    $parentId = $response->json('data.id');
    $child = Task::where('parent_task_id', $parentId)->sole();
    $grandchild = Task::where('parent_task_id', $child->id)->sole();

    expect($grandchild->assignees->pluck('id')->all())->toBe([$childAssignee->id]);
});

it('AC-005: an invalid node at level 3 (outside its direct parent\'s date range) rolls back the whole creation', function () {
    $actor = taskActorWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', taskPayload([
        'title' => 'Padre con range',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'subtasks' => [[
            'title' => 'Figlio',
            'start_date' => '2026-01-02',
            'end_date' => '2026-01-20',
            'subtasks' => [[
                'title' => 'Nipote',
                'start_date' => '2026-01-03',
                'end_date' => '2026-01-15',
                'subtasks' => [[
                    'title' => 'Pronipote fuori range',
                    'end_date' => '2026-02-01',
                ]],
            ]],
        ]],
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('subtasks.0.subtasks.0.subtasks.0.end_date');

    $this->assertDatabaseMissing('tasks', ['title' => 'Padre con range']);
    $this->assertDatabaseCount('tasks', 0);
});

it('AC-006: the recurrence copies a 3-level tree with dates shifted by the same offset', function () {
    $taskType = TaskType::factory()->create();
    $originator = Task::factory()->create(['end_date' => '2026-03-15']);
    $child = Task::factory()->childOf($originator)->create(['title' => 'Figlio', 'start_date' => '2026-03-10', 'end_date' => '2026-03-12']);
    $grandchild = Task::factory()->childOf($child)->create(['title' => 'Nipote', 'start_date' => '2026-03-10', 'end_date' => '2026-03-11', 'task_type_id' => $taskType->id]);
    $greatGrandchild = Task::factory()->childOf($grandchild)->create(['title' => 'Pronipote', 'start_date' => '2026-03-10', 'end_date' => '2026-03-10']);

    $occurrence = app(TaskOccurrenceFactory::class)->materialize($originator, CarbonImmutable::parse('2026-04-15'));

    $copiedChild = $occurrence->subtasks()->sole();
    $copiedGrandchild = $copiedChild->subtasks()->sole();
    $copiedGreatGrandchild = $copiedGrandchild->subtasks()->sole();

    expect($copiedChild->title)->toBe('Figlio')
        ->and($copiedChild->end_date->toDateString())->toBe('2026-04-12')
        ->and($copiedGrandchild->title)->toBe('Nipote')
        ->and($copiedGrandchild->end_date->toDateString())->toBe('2026-04-11')
        ->and($copiedGrandchild->task_type_id)->toBe($taskType->id)
        ->and($copiedGreatGrandchild->title)->toBe('Pronipote')
        ->and($copiedGreatGrandchild->end_date->toDateString())->toBe('2026-04-10')
        ->and($copiedGreatGrandchild->id)->not->toBe($greatGrandchild->id);
});

it('AC-006: pruning a rule change deletes an intact 3-level occurrence tree, and keeps one whose grandchild was touched', function () {
    $creator = taskActorWith(['create', 'update', 'view']);
    Sanctum::actingAs($creator);
    $status = openStatusFor0161();

    $recurrence = TaskRecurrence::factory()->create(['frequency' => TaskRecurrenceFrequency::Daily->value]);
    $capostipite = Task::factory()->forCreator($creator)->inStatus($status)
        ->create(['end_date' => now()->subDays(30)->toDateString(), 'task_recurrence_id' => $recurrence->id]);

    // Occurrence A: an entirely untouched 3-level tree — pruned whole.
    $occurrenceA = Task::factory()->inStatus($status)
        ->create(['end_date' => now()->addDays(10)->toDateString(), 'task_recurrence_id' => $recurrence->id]);
    $childA = Task::factory()->childOf($occurrenceA)->inStatus($status)->create();
    $grandchildA = Task::factory()->childOf($childA)->inStatus($status)->create();
    $greatGrandchildA = Task::factory()->childOf($grandchildA)->inStatus($status)->create();

    // Occurrence B: same depth, but its great-grandchild was blocked by a
    // real user — the whole tree, occurrence included, must survive.
    $occurrenceB = Task::factory()->inStatus($status)
        ->create(['end_date' => now()->addDays(12)->toDateString(), 'task_recurrence_id' => $recurrence->id]);
    $childB = Task::factory()->childOf($occurrenceB)->inStatus($status)->create();
    $grandchildB = Task::factory()->childOf($childB)->inStatus($status)->create();
    $greatGrandchildB = Task::factory()->childOf($grandchildB)->inStatus($status)->create(['is_blocked' => true]);

    $this->patchJson("/api/tasks/{$capostipite->id}", ['recurrence' => recurrencePayload()])->assertOk();

    expect(Task::query()->find($occurrenceA->id))->toBeNull()
        ->and(Task::query()->find($childA->id))->toBeNull()
        ->and(Task::query()->find($grandchildA->id))->toBeNull()
        ->and(Task::query()->find($greatGrandchildA->id))->toBeNull()
        ->and(Task::query()->find($occurrenceB->id))->not->toBeNull()
        ->and(Task::query()->find($childB->id))->not->toBeNull()
        ->and(Task::query()->find($grandchildB->id))->not->toBeNull()
        ->and(Task::query()->find($greatGrandchildB->id))->not->toBeNull();
});
