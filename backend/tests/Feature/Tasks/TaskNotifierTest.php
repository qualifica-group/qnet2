<?php

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskLocked;
use App\Notifications\TaskObserver;
use App\Notifications\TaskValidationApproved;
use App\Notifications\TaskValidationRequested;
use App\Services\Notifications\TaskNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| App\Services\Notifications\TaskNotifier (spec 0119, D-2, AC-006,
| AC-009..AC-011)
|--------------------------------------------------------------------------
|
| L'orchestratore in isolamento: superficie pubblica, costo in query,
| timing rispetto alla transazione, robustezza sugli id morti. Le regole di
| AUDIENCE sono gia' pinnate da TaskNotificationAudienceTest e non si
| ripetono qui; i write path che lo chiamano sono A-04/A-05.
*/

/**
 * Un Task con i quattro ruoli distinti, gia' caricato.
 *
 * @return array{0: Task, 1: array<string, User>}
 */
function notifierFixture(): array
{
    $people = [
        'creator' => User::factory()->create(),
        'requester' => User::factory()->create(),
        'assignee' => User::factory()->create(),
        'watcher' => User::factory()->create(),
        'actor' => User::factory()->create(),
    ];

    $task = Task::factory()->create([
        'creator_id' => $people['creator']->id,
        'requester_id' => $people['requester']->id,
    ]);
    $task->assignees()->attach($people['assignee']->id);
    $task->watchers()->attach($people['watcher']->id);

    return [$task->fresh(), $people];
}

it('exposes one public method per event and nothing else (AC-006)', function () {
    $methods = collect((new ReflectionClass(TaskNotifier::class))->getMethods(ReflectionMethod::IS_PUBLIC))
        ->reject(fn (ReflectionMethod $method): bool => $method->isConstructor())
        ->map(fn (ReflectionMethod $method): string => $method->getName())
        ->sort()
        ->values()
        ->all();

    expect($methods)->toBe([
        'assigned',
        'closed',
        'feedbackInserted',
        'locked',
        'uncompleted',
        'unlocked',
        'validationApproved',
        'validationRejected',
        'validationReopened',
        'validationRequested',
        'watching',
    ]);
});

it('delivers to the whole audience of an everyone-event', function () {
    Notification::fake();
    [$task, $people] = notifierFixture();

    app(TaskNotifier::class)->locked($task, $people['actor']);

    Notification::assertSentTo(
        [$people['creator'], $people['requester'], $people['assignee'], $people['watcher']],
        TaskLocked::class,
    );
    Notification::assertNotSentTo($people['actor'], TaskLocked::class);
});

it('delivers an assignee-only event to the assignees alone', function () {
    Notification::fake();
    [$task, $people] = notifierFixture();

    app(TaskNotifier::class)->validationApproved($task, $people['actor']);

    Notification::assertSentTo($people['assignee'], TaskValidationApproved::class);
    Notification::assertNotSentTo($people['watcher'], TaskValidationApproved::class);
    Notification::assertNotSentTo($people['creator'], TaskValidationApproved::class);
});

it('delivers a validation request to the requester alone', function () {
    Notification::fake();
    [$task, $people] = notifierFixture();

    app(TaskNotifier::class)->validationRequested($task, $people['assignee']);

    Notification::assertSentTo($people['requester'], TaskValidationRequested::class);
    Notification::assertNotSentTo($people['creator'], TaskValidationRequested::class);
});

it('restricts the audience to the ids the caller names (D-9)', function () {
    Notification::fake();
    [$task, $people] = notifierFixture();
    $newcomer = User::factory()->create();

    app(TaskNotifier::class)->assigned($task, $people['actor'], [$newcomer->id]);

    Notification::assertSentTo($newcomer, TaskAssigned::class);
    Notification::assertNotSentTo($people['assignee'], TaskAssigned::class);
});

it('drops the actor even out of a restricted audience (D-3 + D-9)', function () {
    Notification::fake();
    [$task, $people] = notifierFixture();

    app(TaskNotifier::class)->watching($task, $people['actor'], [$people['actor']->id]);

    Notification::assertNothingSent();
});

it('sends nothing at all when the audience resolves to nobody', function () {
    Notification::fake();
    $solo = User::factory()->create();
    $task = Task::factory()->create(['creator_id' => $solo->id, 'requester_id' => null]);

    app(TaskNotifier::class)->closed($task, $solo);

    Notification::assertNothingSent();
});

it('loads every recipient in a single query (AC-009)', function () {
    Notification::fake();
    [$task, $people] = notifierFixture();

    // Otto destinatari distinti, non quattro: il conteggio deve restare 1.
    foreach (range(1, 4) as $ignored) {
        $task->watchers()->attach(User::factory()->create()->id);
    }
    $task->load(['assignees', 'watchers']);

    $userQueries = 0;
    DB::listen(function ($query) use (&$userQueries): void {
        if (str_contains($query->sql, 'from "users"') || str_contains($query->sql, 'from `users`')) {
            $userQueries++;
        }
    });

    app(TaskNotifier::class)->locked($task, $people['actor']);

    expect($userQueries)->toBe(1);
});

it('sends nothing when the surrounding transaction rolls back (AC-010)', function () {
    Notification::fake();
    [$task, $people] = notifierFixture();

    try {
        DB::transaction(function () use ($task, $people): void {
            app(TaskNotifier::class)->locked($task, $people['actor']);

            throw new RuntimeException('the write failed after the notifier was called');
        });
    } catch (RuntimeException) {
        // atteso
    }

    Notification::assertNothingSent();
});

it('sends once the surrounding transaction commits', function () {
    Notification::fake();
    [$task, $people] = notifierFixture();

    DB::transaction(function () use ($task, $people): void {
        app(TaskNotifier::class)->locked($task, $people['actor']);
    });

    Notification::assertSentTo($people['creator'], TaskLocked::class);
});

it('carries on when one recipient id no longer exists (AC-011)', function () {
    Notification::fake();
    [$task, $people] = notifierFixture();
    $ghostId = $people['watcher']->id;
    $people['watcher']->delete();

    app(TaskNotifier::class)->assigned($task, $people['actor'], [$people['assignee']->id, $ghostId]);

    Notification::assertSentTo($people['assignee'], TaskAssigned::class);
    Notification::assertSentTimes(TaskAssigned::class, 1);
});

it('distinguishes the assignee and watcher events on the same task', function () {
    Notification::fake();
    [$task, $people] = notifierFixture();

    app(TaskNotifier::class)->assigned($task, null);
    app(TaskNotifier::class)->watching($task, null);

    Notification::assertSentTo($people['assignee'], TaskAssigned::class);
    Notification::assertNotSentTo($people['assignee'], TaskObserver::class);
    Notification::assertSentTo($people['watcher'], TaskObserver::class);
    Notification::assertNotSentTo($people['watcher'], TaskAssigned::class);
});

/*
| I due casi seguenti girano SENZA Notification::fake(): la meta' di AC-010
| che parla di righe persistite non e' osservabile sotto il fake, che
| sopprime proprio la persistenza da controllare. In ambiente di test la
| coda e' `sync` e il mailer e' `array` (phpunit.xml), quindi la notifica
| percorre la sua strada vera senza spedire nulla.
*/

it('writes no row in notifications when the transaction rolls back (AC-010)', function () {
    Mail::fake();
    [$task, $people] = notifierFixture();

    try {
        DB::transaction(function () use ($task, $people): void {
            app(TaskNotifier::class)->locked($task, $people['actor']);

            throw new RuntimeException('the write failed after the notifier was called');
        });
    } catch (RuntimeException) {
        // atteso
    }

    expect(DB::table('notifications')->count())->toBe(0);
});

it('persists one row per recipient once the transaction commits (AC-010)', function () {
    Mail::fake();
    [$task, $people] = notifierFixture();

    DB::transaction(function () use ($task, $people): void {
        app(TaskNotifier::class)->locked($task, $people['actor']);
    });

    $rows = DB::table('notifications')->get();

    expect($rows)->toHaveCount(4)
        ->and($rows->pluck('type')->unique()->all())->toBe([TaskLocked::class])
        ->and($rows->pluck('notifiable_id')->all())->not->toContain($people['actor']->id);

    $payload = json_decode((string) $rows->first()->data, true, flags: JSON_THROW_ON_ERROR);
    expect(array_keys($payload))->toBe(['title', 'message', 'level', 'action_url']);
});
