<?php

use App\Models\EmploymentProfile;
use App\Models\Task;
use App\Models\User;
use App\Policies\TaskPolicy;
use App\Services\Tasks\TaskVisibilityScope;
use App\Services\TimeEntries\TimeEntrySubordinateResolver;
use App\Tables\Tasks\TaskAdvancedFilterCatalog;
use Database\Seeders\QualificaCatalog\OperatorRoleCatalogue;
use Database\Seeders\QualificaOperatorSeeder;
use Database\Seeders\QualificaStaffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/*
|--------------------------------------------------------------------------
| Spec 0214 — the `tasks.viewTeam` visibility tier
|--------------------------------------------------------------------------
|
| Besides their own Tasks, an actor sees the NON private Tasks with at least
| one assignee among their sottoposti at any depth, deactivated users
| included. Read-only, in union with membership and viewSite.
*/

uses(RefreshDatabase::class);

/**
 * @param  array<int, string>  $abilities
 */
function teamTaskActor(array $abilities): User
{
    foreach (['viewAny', 'view', 'update', 'delete', 'viewAll', 'viewSite', 'viewTeam', 'manageAll'] as $ability) {
        Permission::findOrCreate("tasks.{$ability}");
    }

    Permission::findOrCreate('notes.create');

    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        $user->givePermissionTo("tasks.{$ability}");
    }

    return $user;
}

/** Makes $subordinate report to $manager (an additional manager is allowed). */
function teamTaskReportsTo(User $subordinate, User $manager): User
{
    $profile = EmploymentProfile::query()->where('user_id', $subordinate->id)->first()
        ?? EmploymentProfile::factory()->create(['user_id' => $subordinate->id]);
    $profile->reportsTo()->attach($manager->id);

    return $subordinate;
}

function teamTaskAssignedTo(string $title, User ...$assignees): Task
{
    $task = Task::factory()->create(['title' => $title]);

    foreach ($assignees as $assignee) {
        $task->assignees()->attach($assignee->id);
    }

    return $task;
}

/**
 * @return array<int, string>
 */
function teamTaskGridTitles(): array
{
    $items = test()->postJson('/api/tables/tasks/rows', ['startRow' => 0, 'endRow' => 50, 'advancedFilters' => ['assignment' => ['visible']]])
        ->assertOk()->json('items');

    return collect($items)->pluck('title')->sort()->values()->all();
}

it('exposes viewTeam as an assignable tasks ability', function () {
    expect(TaskPolicy::abilities())->toContain('viewTeam');
});

it('AC-001 shows the task of a direct subordinate in the grid and on show', function () {
    $manager = teamTaskActor(['viewAny', 'view', 'viewTeam']);
    $report = teamTaskReportsTo(User::factory()->create(), $manager);
    $task = teamTaskAssignedTo('Del team', $report);
    Sanctum::actingAs($manager);

    $this->getJson("/api/tasks/{$task->id}")->assertOk();

    expect(teamTaskGridTitles())->toBe(['Del team']);
});

it('AC-002 follows the chain at any depth', function () {
    $manager = teamTaskActor(['viewAny', 'view', 'viewTeam']);
    $middle = teamTaskReportsTo(User::factory()->create(), $manager);
    $leaf = teamTaskReportsTo(User::factory()->create(), $middle);
    teamTaskAssignedTo('Foglia', $leaf);
    Sanctum::actingAs($manager);

    expect(teamTaskGridTitles())->toBe(['Foglia']);
});

it('AC-003 grants nothing without viewTeam', function () {
    $manager = teamTaskActor(['viewAny', 'view']);
    $report = teamTaskReportsTo(User::factory()->create(), $manager);
    $task = teamTaskAssignedTo('Del team', $report);
    Sanctum::actingAs($manager);

    $this->getJson("/api/tasks/{$task->id}")->assertForbidden();

    expect(teamTaskGridTitles())->toBe([]);
});

it('AC-004 hides private tasks unless the actor is a member', function () {
    $manager = teamTaskActor(['viewAny', 'view', 'viewTeam']);
    $report = teamTaskReportsTo(User::factory()->create(), $manager);
    $private = Task::factory()->private()->create(['title' => 'Privato']);
    $private->assignees()->attach($report->id);
    Sanctum::actingAs($manager);

    $this->getJson("/api/tasks/{$private->id}")->assertForbidden();
    expect(teamTaskGridTitles())->toBe([]);

    $private->watchers()->attach($manager->id);

    $this->getJson("/api/tasks/{$private->id}")->assertOk();
    expect(teamTaskGridTitles())->toBe(['Privato']);
});

it('AC-005 ignores a subordinate who is only requester, creator or watcher', function () {
    $manager = teamTaskActor(['viewAny', 'view', 'viewTeam']);
    $report = teamTaskReportsTo(User::factory()->create(), $manager);
    $asRequester = Task::factory()->create(['title' => 'Richiedente', 'requester_id' => $report->id]);
    Task::factory()->forCreator($report)->create(['title' => 'Creatore']);
    $asWatcher = Task::factory()->create(['title' => 'Osservatore']);
    $asWatcher->watchers()->attach($report->id);
    Sanctum::actingAs($manager);

    $this->getJson("/api/tasks/{$asRequester->id}")->assertForbidden();

    expect(teamTaskGridTitles())->toBe([]);
});

it('AC-006 counts deactivated subordinates and intermediates, while time entries stay active-only', function () {
    $manager = teamTaskActor(['viewAny', 'view', 'viewTeam']);
    $middle = teamTaskReportsTo(User::factory()->create(['is_active' => false]), $manager);
    $leaf = teamTaskReportsTo(User::factory()->create(['is_active' => false]), $middle);
    teamTaskAssignedTo('Intermedio', $middle);
    teamTaskAssignedTo('Foglia', $leaf);
    Sanctum::actingAs($manager);

    expect(teamTaskGridTitles())->toBe(['Foglia', 'Intermedio'])
        ->and((new TimeEntrySubordinateResolver)->descendantIds($manager->id))->toBe([])
        ->and((new TimeEntrySubordinateResolver)->allDescendantIds($manager->id))->toEqualCanonicalizing([$middle->id, $leaf->id]);
});

it('AC-007 is read-only: no update nor delete without a record role or manageAll', function () {
    $manager = teamTaskActor(['viewAny', 'view', 'update', 'delete', 'viewTeam']);
    $report = teamTaskReportsTo(User::factory()->create(), $manager);
    $task = teamTaskAssignedTo('Del team', $report);
    Sanctum::actingAs($manager);

    expect($this->patchJson("/api/tasks/{$task->id}", ['title' => 'Cambiato'])->status())->toBeIn([403, 422]);
    $this->deleteJson("/api/tasks/{$task->id}")->assertForbidden();

    expect($manager->can('update', $task))->toBeFalse()
        ->and($manager->can('view', $task))->toBeTrue()
        ->and($task->fresh()->title)->toBe('Del team');
});

it('AC-008 survives a cycle in the data: each side sees the other once', function () {
    $a = teamTaskActor(['viewAny', 'view', 'viewTeam']);
    $b = teamTaskReportsTo(User::factory()->create(), $a);
    teamTaskReportsTo($a, $b);
    teamTaskAssignedTo('Di B', $b);
    teamTaskAssignedTo('Di A', $a);
    Sanctum::actingAs($a);

    expect(teamTaskGridTitles())->toBe(['Di A', 'Di B'])
        ->and((new TimeEntrySubordinateResolver)->allDescendantIds($a->id))->toBe([$b->id]);
});

it('AC-009 the in-memory record shape agrees with the query shape', function () {
    $manager = teamTaskActor(['view', 'viewTeam']);
    $middle = teamTaskReportsTo(User::factory()->create(), $manager);
    $leaf = teamTaskReportsTo(User::factory()->create(), $middle);
    $outsider = User::factory()->create();
    teamTaskAssignedTo('Intermedio', $middle);
    teamTaskAssignedTo('Foglia e altro', $leaf, $outsider);
    teamTaskAssignedTo('Estraneo', $outsider);
    teamTaskAssignedTo('Senza assegnatari');
    Task::factory()->private()->create(['title' => 'Privato'])->assignees()->attach($middle->id);

    $loaded = Task::query()->with(['assignees', 'watchers'])->get();
    $queried = TaskVisibilityScope::scopeToActor(Task::query(), $manager)->pluck('id')->all();

    foreach ($loaded as $task) {
        expect(TaskVisibilityScope::isVisibleTo($manager, $task))->toBe(in_array($task->id, $queried, true))
            ->and(TaskVisibilityScope::isVisibleTo($manager, Task::query()->findOrFail($task->id)))->toBe(in_array($task->id, $queried, true));
    }

    expect($loaded->filter(fn (Task $task) => TaskVisibilityScope::isVisibleTo($manager, $task))->pluck('title')->sort()->values()->all())
        ->toBe(['Foglia e altro', 'Intermedio']);
});

it('AC-010 resolves the chain once per request, not once per row', function () {
    $manager = teamTaskActor(['viewAny', 'view', 'viewTeam']);
    $report = teamTaskReportsTo(User::factory()->create(), $manager);

    foreach (range(1, 6) as $number) {
        teamTaskAssignedTo("Task {$number}", $report);
    }

    Sanctum::actingAs($manager);
    DB::enableQueryLog();

    expect(teamTaskGridTitles())->toHaveCount(6);

    $chainQueries = collect(DB::getQueryLog())
        ->filter(fn (array $entry) => str_contains($entry['query'], 'from "employment_profile_manager"') || str_contains($entry['query'], 'from `employment_profile_manager`'))
        ->count();

    expect($chainQueries)->toBe(1);
});

it('AC-011 offers the "visible" assignment option to a viewTeam holder only', function () {
    $excluded = fn (User $actor) => collect(TaskAdvancedFilterCatalog::advancedFilters($actor))
        ->firstWhere('name', 'assignment')['excludedValues'];

    expect($excluded(teamTaskActor(['viewAny', 'view', 'viewTeam'])))->not->toContain('visible')
        ->and($excluded(teamTaskActor(['viewAny', 'view'])))->toContain('visible');
});

it('AC-012 makes a manager above an assignee mentionable, on non private tasks only', function () {
    $creator = teamTaskActor(['view']);
    $creator->givePermissionTo('notes.create');
    $manager = teamTaskActor(['view', 'viewTeam']);
    $middle = teamTaskReportsTo(User::factory()->create(), $manager);
    $leaf = teamTaskReportsTo(User::factory()->create(), $middle);
    $noTeamGrant = teamTaskReportsTo(teamTaskActor(['view']), $middle);
    $outsiderAssignee = User::factory()->create();

    $open = Task::factory()->forCreator($creator)->create(['requester_id' => $creator->id]);
    $open->assignees()->attach($leaf->id);
    $closed = Task::factory()->private()->forCreator($creator)->create(['requester_id' => $creator->id]);
    $closed->assignees()->attach($leaf->id);
    $other = Task::factory()->forCreator($creator)->create(['requester_id' => $creator->id]);
    $other->assignees()->attach($outsiderAssignee->id);
    Sanctum::actingAs($creator);

    $ids = fn (Task $task) => collect($this->getJson("/api/notes/mentionable-users?entity_type=tasks&entity_id={$task->id}")
        ->assertOk()->json('items'))->pluck('id')->all();

    expect($ids($open))->toContain($manager->id)
        ->and($ids($open))->not->toContain($noTeamGrant->id)
        ->and($ids($closed))->not->toContain($manager->id)
        ->and($ids($other))->not->toContain($manager->id);
});

it('AC-013 grants viewTeam to every role with the own-tasks block', function () {
    $this->seed(QualificaOperatorSeeder::class);
    $this->seed(QualificaStaffSeeder::class);

    expect(OperatorRoleCatalogue::OWN_TASKS_DENIED_ABILITIES)->not->toContain('viewTeam');

    foreach (OperatorRoleCatalogue::ROLES as $name => $role) {
        expect(User::role($name)->firstOrFail()->can('tasks.viewTeam'))->toBeTrue("{$name} tasks.viewTeam");
    }
});
