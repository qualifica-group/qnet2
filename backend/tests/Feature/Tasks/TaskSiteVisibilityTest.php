<?php

use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\Task;
use App\Models\User;
use App\Policies\TaskPolicy;
use App\Services\Tasks\TaskVisibilityScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/*
|--------------------------------------------------------------------------
| Spec 0148 — the `tasks.viewSite` visibility tier
|--------------------------------------------------------------------------
|
| Three tiers: `viewAll` (every Task), `viewSite` (own Tasks PLUS those with
| an assignee sharing one of the actor's Sedi, physical or remote) and the
| default (own Tasks only: creatore, richiedente, assegnatario, osservatore).
*/

uses(RefreshDatabase::class);

/**
 * @param  array<int, string>  $abilities
 */
function siteTaskActor(array $abilities): User
{
    foreach (['viewAny', 'view', 'update', 'delete', 'viewAll', 'viewSite', 'manageAll', 'complete', 'validate', 'block', 'requestUpdate'] as $ability) {
        Permission::findOrCreate("tasks.{$ability}");
    }

    Permission::findOrCreate('notes.create');

    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        $user->givePermissionTo("tasks.{$ability}");
    }

    return $user;
}

function siteTaskMemberOf(User $user, ?OperationalSite $physical, OperationalSite ...$remote): User
{
    $factory = EmploymentProfile::factory();

    if ($physical !== null) {
        $factory = $factory->physicalSite($physical);
    }

    if ($remote !== []) {
        $factory = $factory->remoteSites(...$remote);
    }

    $factory->create(['user_id' => $user->id]);

    return $user;
}

function siteTaskAssignedTo(string $title, User ...$assignees): Task
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
function siteTaskGridTitles(): array
{
    $items = test()->postJson('/api/tables/tasks/rows', ['startRow' => 0, 'endRow' => 50])
        ->assertOk()->json('items');

    return collect($items)->pluck('title')->sort()->values()->all();
}

it('exposes viewSite as an assignable tasks ability', function () {
    expect(TaskPolicy::abilities())->toContain('viewSite');
});

it('viewSite shows the tasks of an assignee sharing the actor physical site, in the grid and on show', function () {
    $site = OperationalSite::factory()->create();
    $actor = siteTaskMemberOf(siteTaskActor(['viewAny', 'view', 'viewSite']), $site);
    $colleague = siteTaskMemberOf(User::factory()->create(), $site);
    $task = siteTaskAssignedTo('Della sede', $colleague);
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$task->id}")->assertOk();

    expect(siteTaskGridTitles())->toBe(['Della sede']);
});

it('viewSite matches remote memberships on either side', function () {
    $physical = OperationalSite::factory()->create();
    $remote = OperationalSite::factory()->create();
    $actor = siteTaskMemberOf(siteTaskActor(['viewAny', 'view', 'viewSite']), $physical, $remote);
    $remoteColleague = siteTaskMemberOf(User::factory()->create(), null, $physical);
    $physicalColleague = siteTaskMemberOf(User::factory()->create(), $remote);
    siteTaskAssignedTo('Remoto', $remoteColleague);
    siteTaskAssignedTo('Fisico su mia remota', $physicalColleague);
    Sanctum::actingAs($actor);

    expect(siteTaskGridTitles())->toBe(['Fisico su mia remota', 'Remoto']);
});

it('viewSite matches when ANY assignee shares a site', function () {
    $site = OperationalSite::factory()->create();
    $actor = siteTaskMemberOf(siteTaskActor(['viewAny', 'view', 'viewSite']), $site);
    $colleague = siteTaskMemberOf(User::factory()->create(), $site);
    $outsider = siteTaskMemberOf(User::factory()->create(), OperationalSite::factory()->create());
    siteTaskAssignedTo('Misto', $outsider, $colleague);
    Sanctum::actingAs($actor);

    expect(siteTaskGridTitles())->toBe(['Misto']);
});

it('viewSite hides the tasks of other sites and keeps the actor own tasks (union)', function () {
    $site = OperationalSite::factory()->create();
    $actor = siteTaskMemberOf(siteTaskActor(['viewAny', 'view', 'viewSite']), $site);
    $outsider = siteTaskMemberOf(User::factory()->create(), OperationalSite::factory()->create());
    $foreign = siteTaskAssignedTo('Altra sede', $outsider);
    Task::factory()->forCreator($actor)->create(['title' => 'Mio']);
    Task::factory()->create(['title' => 'Senza assegnatari']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$foreign->id}")->assertForbidden();

    expect(siteTaskGridTitles())->toBe(['Mio']);
});

it('by default (no viewSite) sharing a site grants nothing: only own tasks', function () {
    $site = OperationalSite::factory()->create();
    $actor = siteTaskMemberOf(siteTaskActor(['viewAny', 'view']), $site);
    $colleague = siteTaskMemberOf(User::factory()->create(), $site);
    $task = siteTaskAssignedTo('Della sede', $colleague);
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$task->id}")->assertForbidden();

    expect(siteTaskGridTitles())->toBe([]);
});

it('viewSite on an actor with no site widens nothing', function () {
    $actor = siteTaskActor(['viewAny', 'view', 'viewSite']);
    $colleague = siteTaskMemberOf(User::factory()->create(), OperationalSite::factory()->create());
    siteTaskAssignedTo('Della sede di altri', $colleague);
    Sanctum::actingAs($actor);

    expect(siteTaskGridTitles())->toBe([]);
});

it('viewSite is read-only: a site task can be neither updated nor deleted', function () {
    $site = OperationalSite::factory()->create();
    $actor = siteTaskMemberOf(siteTaskActor(['viewAny', 'view', 'update', 'delete', 'viewSite']), $site);
    $colleague = siteTaskMemberOf(User::factory()->create(), $site);
    $task = siteTaskAssignedTo('Della sede', $colleague);
    Sanctum::actingAs($actor);

    // The field ceiling answers before the controller's policy check (422),
    // exactly as for a `viewAll` non-member: the write is refused either way.
    expect($this->patchJson("/api/tasks/{$task->id}", ['title' => 'Cambiato'])->status())->toBeIn([403, 422]);
    $this->deleteJson("/api/tasks/{$task->id}")->assertForbidden();

    expect($actor->can('update', $task))->toBeFalse()
        ->and($actor->can('view', $task))->toBeTrue()
        ->and($task->fresh()->title)->toBe('Della sede');
});

it('the in-memory record shape agrees with the query shape', function () {
    $site = OperationalSite::factory()->create();
    $actor = siteTaskMemberOf(siteTaskActor(['view', 'viewSite']), $site);
    $colleague = siteTaskMemberOf(User::factory()->create(), $site);
    $outsider = siteTaskMemberOf(User::factory()->create(), OperationalSite::factory()->create());
    $noProfile = User::factory()->create();
    siteTaskAssignedTo('Sede', $colleague);
    siteTaskAssignedTo('Altra', $outsider);
    siteTaskAssignedTo('Senza profilo', $noProfile);

    $loaded = Task::query()->with(['assignees.employment.operationalSites', 'watchers'])->get();
    $queried = TaskVisibilityScope::scopeToActor(Task::query(), $actor)->pluck('id')->all();

    foreach ($loaded as $task) {
        expect(TaskVisibilityScope::isVisibleTo($actor, $task))->toBe(in_array($task->id, $queried, true));
    }

    expect($loaded->filter(fn (Task $task) => TaskVisibilityScope::isVisibleTo($actor, $task))->pluck('title')->all())
        ->toBe(['Sede']);
});

it('a viewSite holder sharing an assignee site is mentionable in the task notes, others are not', function () {
    $site = OperationalSite::factory()->create();
    $creator = siteTaskActor(['view']);
    $creator->givePermissionTo('notes.create');
    $colleague = siteTaskMemberOf(siteTaskActor(['view']), $site);
    $siteReader = siteTaskMemberOf(siteTaskActor(['view', 'viewSite']), $site);
    $otherSiteReader = siteTaskMemberOf(siteTaskActor(['view', 'viewSite']), OperationalSite::factory()->create());
    $sameSiteNoGrant = siteTaskMemberOf(siteTaskActor(['view']), $site);

    $task = Task::factory()->forCreator($creator)->create(['requester_id' => $creator->id]);
    $task->assignees()->attach($colleague->id);
    Sanctum::actingAs($creator);

    $ids = collect($this->getJson("/api/notes/mentionable-users?entity_type=tasks&entity_id={$task->id}")
        ->assertOk()->json('items'))->pluck('id')->all();

    expect($ids)->toContain($siteReader->id)
        ->and($ids)->not->toContain($otherSiteReader->id)
        ->and($ids)->not->toContain($sameSiteNoGrant->id);
});
