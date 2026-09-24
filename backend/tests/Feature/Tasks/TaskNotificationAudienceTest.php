<?php

use App\Models\Task;
use App\Models\User;
use App\Services\Notifications\TaskNotificationAudience;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| App\Services\Notifications\TaskNotificationAudience (spec 0119, D-3/D-4/
| D-5/D-6, AC-007/AC-008; spec 0153, D-11/D-13)
|--------------------------------------------------------------------------
|
| WHO hears about a Task event. Pure by construction: every case below is
| built from plain ids, so the two rules that matter — drop the actor,
| deduplicate — are tested without a single query. Only the `of()` factory
| touches the database.
*/

/**
 * The four sets as plain ids, so a case reads as the situation it describes.
 *
 * @param  array<int, int>  $assignees
 * @param  array<int, int>  $watchers
 */
function audience(int $creator, ?int $requester, array $assignees, array $watchers): TaskNotificationAudience
{
    return new TaskNotificationAudience($creator, $requester, $assignees, $watchers);
}

/** An actor is only ever used for its id here. */
function actorWithId(int $id): User
{
    $user = new User;
    $user->id = $id;

    return $user;
}

it('merges the four roles into one audience (D-4)', function () {
    $audience = audience(creator: 1, requester: 2, assignees: [3, 4], watchers: [5]);

    expect($audience->everyone(null))->toEqualCanonicalizing([1, 2, 3, 4, 5]);
});

it('sends one notification to a user holding several roles at once (AC-007)', function () {
    // The same person created the Task, requested it and carries it out.
    $audience = audience(creator: 7, requester: 7, assignees: [7, 8], watchers: []);

    $recipients = $audience->everyone(null);

    expect($recipients)->toEqualCanonicalizing([7, 8])
        ->and(array_count_values($recipients)[7])->toBe(1);
});

// `watchers()` is DELIBERATELY absent here since spec 0153, D-13 — see its
// own "excludes nobody" test below.
it('drops the actor from every audience (AC-008, D-3)', function () {
    $audience = audience(creator: 1, requester: 2, assignees: [2, 3], watchers: [4]);
    $actor = actorWithId(2);

    expect($audience->everyone($actor))->toEqualCanonicalizing([1, 3, 4])
        ->and($audience->assignees($actor))->toEqualCanonicalizing([3])
        ->and($audience->requesterAndCreator($actor))->toEqualCanonicalizing([1]);
});

it('drops the creator, not the actor, from the "assigned" audience (spec 0153, D-13)', function () {
    $audience = audience(creator: 1, requester: 2, assignees: [1, 3], watchers: []);

    expect($audience->assigned())->toEqualCanonicalizing([3])
        ->and($audience->restrictAssigned([1, 3]))->toEqualCanonicalizing([3]);
});

it('excludes nobody from the "watching" audience, not even the actor (spec 0153, D-13)', function () {
    $audience = audience(creator: 1, requester: 2, assignees: [], watchers: [1, 2, 5]);

    expect($audience->watchers())->toEqualCanonicalizing([1, 2, 5])
        ->and($audience->restrictWatchers([1, 2, 5]))->toEqualCanonicalizing([1, 2, 5]);
});

it('closure() is requester + watchers, plus assignees only when told to, never the creator as such (spec 0153, D-11)', function () {
    $audience = audience(creator: 1, requester: 2, assignees: [3, 4], watchers: [5]);
    $actor = actorWithId(5);

    expect($audience->closure(null, includeAssignees: false))->toEqualCanonicalizing([2, 5])
        ->and($audience->closure(null, includeAssignees: true))->toEqualCanonicalizing([2, 3, 4, 5])
        ->and($audience->closure($actor, includeAssignees: true))->toEqualCanonicalizing([2, 3, 4]);
});

it('leaves the audience whole when there is no actor, as for a system write', function () {
    $audience = audience(creator: 1, requester: 2, assignees: [3], watchers: []);

    expect($audience->everyone(null))->toEqualCanonicalizing([1, 2, 3]);
});

it('can resolve to nobody when the actor is the only member', function () {
    $audience = audience(creator: 9, requester: null, assignees: [9], watchers: []);

    expect($audience->everyone(actorWithId(9)))->toBe([]);
});

it('contributes nobody for a null requester (D-5)', function () {
    $audience = audience(creator: 1, requester: null, assignees: [2], watchers: []);

    expect($audience->everyone(null))->toEqualCanonicalizing([1, 2])
        ->and($audience->requesterAndCreator(null))->toEqualCanonicalizing([1]);
});

it('falls back to the creator when the requester is the sole intended recipient (D-5)', function () {
    $withRequester = audience(creator: 1, requester: 2, assignees: [], watchers: []);
    $without = audience(creator: 1, requester: null, assignees: [], watchers: []);

    expect($withRequester->requesterOrCreator(null))->toBe([2])
        ->and($without->requesterOrCreator(null))->toBe([1]);
});

it('returns a list with no holes, never a map keyed by the original position', function () {
    $audience = audience(creator: 1, requester: 2, assignees: [3], watchers: []);

    expect(array_keys($audience->everyone(actorWithId(1))))->toBe([0, 1]);
});

it('reads the two pivots off a loaded Task (of)', function () {
    $creator = User::factory()->create();
    $requester = User::factory()->create();
    $assignee = User::factory()->create();
    $watcher = User::factory()->create();

    $task = Task::factory()->create([
        'creator_id' => $creator->id,
        'requester_id' => $requester->id,
    ]);
    $task->assignees()->attach($assignee->id);
    $task->watchers()->attach($watcher->id);

    $audience = TaskNotificationAudience::of($task->load(['assignees', 'watchers']));

    expect($audience->everyone(null))->toEqualCanonicalizing([
        $creator->id, $requester->id, $assignee->id, $watcher->id,
    ])->and($audience->assignees(null))->toBe([$assignee->id])
        ->and($audience->watchers())->toBe([$watcher->id]);
});
