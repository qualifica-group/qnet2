<?php

use App\Enums\TaskStatusGroup;
use App\Enums\TaskStatusSystemKey;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskClosed;
use App\Notifications\TaskFeedbackInserted;
use App\Notifications\TaskLocked;
use App\Notifications\TaskObserver;
use App\Notifications\TaskUnCompleted;
use App\Notifications\TaskUnLocked;
use App\Notifications\TaskUpdateRequested;
use App\Notifications\TaskValidationApproved;
use App\Notifications\TaskValidationRejected;
use App\Notifications\TaskValidationReopened;
use App\Notifications\TaskValidationRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Voci 1..6 e 9..11 della mappa notifiche, sui sei write path di
| TaskActionService (spec 0119, D-12, AC-019..AC-028)
|--------------------------------------------------------------------------
|
| Questa suite esercita gli INNESTI, non le classi di notifica: canali, deep
| link e scheda dettagli sono territorio di TaskNotificationClassesTest, e le
| regole di audience (esclusione attore, deduplica, ripiego D-5) sono gia'
| pinnate da TaskNotificationAudienceTest. Qui conta una cosa sola: che il
| write path scelga il RAMO giusto e lo faccia passando dagli endpoint reali,
| perche' la biforcazione dipende da cio' che il write path ha gia' deciso e
| chiamare il Service a mano la proverebbe su un percorso che nessun client
| usa.
|
| Le due biforcazioni delicate sono quelle di D-12: `complete()` guarda il
| flag `validation_status_id` e poi il feedback RISULTANTE, `uncomplete()`
| guarda la fase DI PARTENZA, che dopo la scrittura non esiste piu'.
*/

if (! function_exists('taskActionNotificationActor')) {
    /**
     * Un attore con $abilities su `tasks`, piu' `viewAll` cosi' che un 403
     * significhi sempre "matrice/permesso" e mai "fuori visibilita'".
     *
     * Helper LOCALE e con nome proprio, come quello di
     * TaskCreationNotificationsTest: `taskActorWith()` e' definito (guardato)
     * da tredici suite del modulo e PHP tiene solo la prima copia caricata,
     * quindi questo file — che le precede in ordine alfabetico — non ne
     * duplica ne' ne tocca alcuna. Questa spec non aggiunge ability.
     *
     * @param  array<int, string>  $abilities
     */
    function taskActionNotificationActor(array $abilities): User
    {
        $user = User::factory()->create();

        foreach ([...$abilities, 'viewAll'] as $ability) {
            Permission::findOrCreate("tasks.{$ability}");
            $user->givePermissionTo("tasks.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('taskActionNotificationCast')) {
    /**
     * Un Task con i quattro ruoli di record occupati da quattro utenti
     * DISTINTI, cosi' che un'asserzione sull'audience possa distinguere un
     * ruolo dall'altro. L'attore non e' mai uno di loro: chi deve esserlo lo
     * attacca da se'.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{0: Task, 1: array<string, User>}
     */
    function taskActionNotificationCast(?TaskStatus $status = null, array $attributes = []): array
    {
        $people = [
            'creator' => User::factory()->create(),
            'requester' => User::factory()->create(),
            'assignee' => User::factory()->create(),
            'watcher' => User::factory()->create(),
        ];

        $task = Task::factory()->create([
            'creator_id' => $people['creator']->id,
            'requester_id' => $people['requester']->id,
            ...($status === null ? [] : ['task_status_id' => $status->id]),
            ...$attributes,
        ]);

        $task->assignees()->attach($people['assignee']->id);
        $task->watchers()->attach($people['watcher']->id);

        return [$task->fresh(), $people];
    }
}

if (! function_exists('assertNoTaskNotificationsExcept')) {
    /**
     * "E nessun'altra classe parte": il complemento esplicito di ogni ramo
     * di D-12, senza il quale una biforcazione collassata su entrambi i rami
     * passerebbe comunque. Include `TaskUpdateRequested` (spec 0118), che
     * nessuna di queste sei azioni deve mai innescare.
     *
     * @param  array<int, class-string>  $expected
     */
    function assertNoTaskNotificationsExcept(array $expected): void
    {
        $catalogue = [
            TaskAssigned::class,
            TaskClosed::class,
            TaskFeedbackInserted::class,
            TaskLocked::class,
            TaskObserver::class,
            TaskUnCompleted::class,
            TaskUnLocked::class,
            TaskUpdateRequested::class,
            TaskValidationApproved::class,
            TaskValidationRejected::class,
            TaskValidationReopened::class,
            TaskValidationRequested::class,
        ];

        foreach (array_diff($catalogue, $expected) as $notification) {
            Notification::assertSentTimes($notification, 0);
        }
    }
}

// ---------------------------------------------------------------------------
// AC-019/AC-020 — complete, CASO 2: richiesta di validazione (voce 1)
// ---------------------------------------------------------------------------

// REQUIREMENT CHANGED (spec 0121, D-2/D-3): il percorso di validazione non e'
// piu' una libera scelta del client su un Task qualunque — richiede
// `requires_validation = true` E che l'attore non detenga il mandato.
// L'attore di questa suite e' gia' un assegnatario esterno al cast (non
// creatore/richiedente), quindi basta accendere il flag sul Task perche' la
// voce 1 resti raggiungibile esattamente come prima.
it('AC-019: /complete con validation_status_id notifica il solo richiedente, e nient altro', function () {
    Notification::fake();
    $actor = taskActionNotificationActor(['complete']);
    [$task, $people] = taskActionNotificationCast(attributes: ['requires_validation' => true]);
    $task->assignees()->attach($actor->id);
    $inValidation = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", ['validation_status_id' => $inValidation->id])
        ->assertOk();

    Notification::assertSentTo($people['requester'], TaskValidationRequested::class);
    Notification::assertSentTimes(TaskValidationRequested::class, 1);
    assertNoTaskNotificationsExcept([TaskValidationRequested::class]);
});

it('AC-020: senza requester_id la richiesta di validazione ripiega sul creatore (D-5)', function () {
    Notification::fake();
    $actor = taskActionNotificationActor(['complete']);
    [$task, $people] = taskActionNotificationCast(attributes: ['requester_id' => null, 'requires_validation' => true]);
    $task->assignees()->attach($actor->id);
    $inValidation = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", ['validation_status_id' => $inValidation->id])
        ->assertOk();

    Notification::assertSentTo($people['creator'], TaskValidationRequested::class);
    Notification::assertSentTimes(TaskValidationRequested::class, 1);
});

// ---------------------------------------------------------------------------
// AC-021/AC-022 — complete, CASO 1: chiusura (voci 2 e 3)
// ---------------------------------------------------------------------------

it('AC-021: /complete senza validation_status_id ne feedback notifica TaskClosed a tutti, meno l attore', function () {
    Notification::fake();
    $actor = taskActionNotificationActor(['complete']);
    [$task, $people] = taskActionNotificationCast();
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete")->assertOk();

    Notification::assertSentTo(
        [$people['creator'], $people['requester'], $people['assignee'], $people['watcher']],
        TaskClosed::class,
    );
    Notification::assertNotSentTo($actor, TaskClosed::class);
    Notification::assertSentTimes(TaskClosed::class, 4);
    assertNoTaskNotificationsExcept([TaskClosed::class]);
});

it('AC-022: /complete con closure_feedback notifica TaskFeedbackInserted agli stessi, e NON TaskClosed', function () {
    Notification::fake();
    $actor = taskActionNotificationActor(['complete']);
    [$task, $people] = taskActionNotificationCast();
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", ['closure_feedback' => 'Consegnato al cliente.'])
        ->assertOk();

    Notification::assertSentTo(
        [$people['creator'], $people['requester'], $people['assignee'], $people['watcher']],
        TaskFeedbackInserted::class,
    );
    Notification::assertSentTimes(TaskFeedbackInserted::class, 4);
    assertNoTaskNotificationsExcept([TaskFeedbackInserted::class]);
});

it('la chiusura guarda il feedback RISULTANTE, non il payload: un feedback gia sul record vale come inviato', function () {
    // Il ramo che una lettura di `$data->closureFeedbackSubmitted` sbaglierebbe:
    // qui il payload e' vuoto ma il Task porta gia' la sua motivazione, quindi
    // la chiusura e' una voce 3, non una voce 2 (D-12, "Regola pratica").
    Notification::fake();
    $actor = taskActionNotificationActor(['complete']);
    [$task] = taskActionNotificationCast(attributes: ['closure_feedback' => 'Motivazione gia in archivio.']);
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete")->assertOk();

    Notification::assertSentTimes(TaskFeedbackInserted::class, 4);
    assertNoTaskNotificationsExcept([TaskFeedbackInserted::class]);
});

// ---------------------------------------------------------------------------
// AC-023/AC-024 — approve e reject (voci 4 e 5)
// ---------------------------------------------------------------------------

it('AC-023: /approve notifica TaskValidationApproved ai soli assegnatari, e chiude il task per tutti gli altri', function () {
    Notification::fake();
    $actor = taskActionNotificationActor(['validate']);
    $inValidation = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    [$task, $people] = taskActionNotificationCast($inValidation, ['creator_id' => $actor->id]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/approve")->assertOk();

    Notification::assertSentTo($people['assignee'], TaskValidationApproved::class);
    Notification::assertSentTimes(TaskValidationApproved::class, 1);
    Notification::assertNotSentTo(
        [$people['requester'], $people['watcher'], $actor],
        TaskValidationApproved::class,
    );

    // La seconda notifica del data_contract: approve() CHIUDE il task, che e'
    // il trigger delle voci 2 e 3. Nessun feedback sul record, quindi voce 2.
    Notification::assertSentTimes(TaskClosed::class, 3);
    assertNoTaskNotificationsExcept([TaskValidationApproved::class, TaskClosed::class]);
});

it('AC-023 (variante): /approve su un task con closure_feedback chiude con la voce 3, non con la 2', function () {
    Notification::fake();
    $actor = taskActionNotificationActor(['validate']);
    $inValidation = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    [$task] = taskActionNotificationCast($inValidation, [
        'creator_id' => $actor->id,
        'closure_feedback' => 'Verificato in sede.',
    ]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/approve")->assertOk();

    Notification::assertSentTimes(TaskFeedbackInserted::class, 3);
    assertNoTaskNotificationsExcept([TaskValidationApproved::class, TaskFeedbackInserted::class]);
});

it('AC-024: /reject notifica TaskValidationRejected ai soli assegnatari', function () {
    Notification::fake();
    $actor = taskActionNotificationActor(['validate']);
    $inValidation = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    [$task, $people] = taskActionNotificationCast($inValidation, ['creator_id' => $actor->id]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/reject")->assertOk();

    Notification::assertSentTo($people['assignee'], TaskValidationRejected::class);
    Notification::assertSentTimes(TaskValidationRejected::class, 1);
    assertNoTaskNotificationsExcept([TaskValidationRejected::class]);
});

// ---------------------------------------------------------------------------
// AC-025/AC-026 — uncomplete, le due fasi DI PARTENZA (voci 6 e 9)
// ---------------------------------------------------------------------------

it('AC-025: /uncomplete da in_validation notifica TaskValidationReopened a richiedente e creatore, e NESSUN TaskUnCompleted', function () {
    Notification::fake();
    $actor = taskActionNotificationActor(['complete']);
    $inValidation = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    [$task, $people] = taskActionNotificationCast($inValidation);
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/uncomplete")->assertOk();

    Notification::assertSentTo(
        [$people['requester'], $people['creator']],
        TaskValidationReopened::class,
    );
    Notification::assertNotSentTo(
        [$people['assignee'], $people['watcher']],
        TaskValidationReopened::class,
    );
    Notification::assertSentTimes(TaskValidationReopened::class, 2);
    assertNoTaskNotificationsExcept([TaskValidationReopened::class]);
});

it('AC-026: /uncomplete da una fase di chiusura notifica TaskUnCompleted a tutti, e NESSUN TaskValidationReopened', function () {
    Notification::fake();
    $actor = taskActionNotificationActor(['complete']);
    $closed = TaskStatus::query()->where('system_key', TaskStatusSystemKey::ClosedPositive->value)->firstOrFail();
    [$task, $people] = taskActionNotificationCast($closed, ['completion_date' => now()->toDateString()]);
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/uncomplete")->assertOk();

    Notification::assertSentTo(
        [$people['creator'], $people['requester'], $people['assignee'], $people['watcher']],
        TaskUnCompleted::class,
    );
    Notification::assertNotSentTo($actor, TaskUnCompleted::class);
    Notification::assertSentTimes(TaskUnCompleted::class, 4);
    assertNoTaskNotificationsExcept([TaskUnCompleted::class]);
});

// ---------------------------------------------------------------------------
// AC-027/AC-028 — block e unblock (voci 10 e 11)
// ---------------------------------------------------------------------------

it('AC-027: /block notifica TaskLocked a tutti e /unblock notifica TaskUnLocked a tutti', function () {
    Notification::fake();
    // Un manager ESTERNO ai quattro ruoli: cosi' l'insieme TUTTI resta
    // completo e l'esclusione dell'attore (D-3) non ne sottrae un membro.
    $actor = taskActionNotificationActor(['manageAll', 'block']);
    [$task, $people] = taskActionNotificationCast();
    $everyone = [$people['creator'], $people['requester'], $people['assignee'], $people['watcher']];
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/block")->assertOk();

    Notification::assertSentTo($everyone, TaskLocked::class);
    Notification::assertSentTimes(TaskLocked::class, 4);
    assertNoTaskNotificationsExcept([TaskLocked::class]);

    $this->postJson("/api/tasks/{$task->id}/unblock")->assertOk();

    Notification::assertSentTo($everyone, TaskUnLocked::class);
    Notification::assertSentTimes(TaskUnLocked::class, 4);
    assertNoTaskNotificationsExcept([TaskLocked::class, TaskUnLocked::class]);
});

it('AC-028: un /block rifiutato con 403 non notifica nessuno', function () {
    Notification::fake();
    // Assegnatario semplice: la matrice riserva il blocco a creatore,
    // richiedente e manager (TaskActionsTest AC-029).
    $actor = taskActionNotificationActor(['block']);
    [$task] = taskActionNotificationCast();
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/block")->assertStatus(403);

    Notification::assertNothingSent();
});

it('AC-028: un /block rifiutato con 422 perche il task e gia bloccato non notifica nessuno', function () {
    Notification::fake();
    $actor = taskActionNotificationActor(['manageAll', 'block']);
    [$task] = taskActionNotificationCast(attributes: ['is_blocked' => true]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/block")->assertStatus(422);

    Notification::assertNothingSent();
});
