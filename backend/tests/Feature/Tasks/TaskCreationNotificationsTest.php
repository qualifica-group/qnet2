<?php

use App\Enums\TaskStatusGroup;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Voci 7 e 8 della mappa notifiche, sui due write path di TaskService
| (spec 0119, D-9/D-10, AC-012..AC-018)
|--------------------------------------------------------------------------
|
| Questa suite esercita gli INNESTI, non le classi di notifica: che cosa
| contengono TaskAssigned/TaskObserver (canali, deep link, scheda dettagli)
| lo coprono le loro suite dedicate. Qui conta solo CHI riceve, e per farlo
| ogni caso passa dagli endpoint reali POST /api/tasks e PATCH
| /api/tasks/{task}, non dal Service: il delta D-9 e la rollback safety di
| DB::afterCommit sono garanzie del write path completo, e testarle sotto
| l'HTTP le proverebbe su un percorso che nessun client usa.
|
| Sul PATCH la regola e' asimmetrica: si notifica solo chi ENTRA, mai chi
| c'era gia' e mai chi esce (D-9).
*/

if (! function_exists('taskNotificationActor')) {
    /**
     * Un attore con $abilities su `tasks`, piu' `viewAll` cosi' che un 403
     * qui significhi sempre "permesso mancante" e mai "non e' membro".
     *
     * Helper LOCALE e con nome proprio: `taskActorWith()` e' definito
     * (guardato) dalle altre suite del modulo e questa spec non aggiunge
     * ability, quindi non se ne tocca ne' se ne duplica alcuna copia.
     *
     * @param  array<int, string>  $abilities
     */
    function taskNotificationActor(array $abilities): User
    {
        $user = User::factory()->create();

        foreach ([...$abilities, 'viewAll'] as $ability) {
            Permission::findOrCreate("tasks.{$ability}");
            $user->givePermissionTo("tasks.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('taskNotificationPayload')) {
    /**
     * I quattro campi obbligatori alla creazione (spec 0118 D-1).
     * `task_status_id` resta assente: e' `prohibited`, lo deriva il server.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function taskNotificationPayload(array $overrides = []): array
    {
        return [
            'title' => 'Attivita notificabile',
            'requester_id' => User::factory()->create()->id,
            'assignee_ids' => [User::factory()->create()->id],
            'end_date' => '2026-12-31',
            ...$overrides,
        ];
    }
}

// ---------------------------------------------------------------------------
// AC-012, AC-013 — creazione
// ---------------------------------------------------------------------------

it('AC-012: POST notifies the two assignees with TaskAssigned and the observer with TaskObserver, and nobody else', function () {
    $actor = taskNotificationActor(['create', 'view']);
    $requester = User::factory()->create();
    $firstAssignee = User::factory()->create();
    $secondAssignee = User::factory()->create();
    $watcher = User::factory()->create();
    Sanctum::actingAs($actor);
    Notification::fake();

    $this->postJson('/api/tasks', taskNotificationPayload([
        'requester_id' => $requester->id,
        'assignee_ids' => [$firstAssignee->id, $secondAssignee->id],
        'watcher_ids' => [$watcher->id],
    ]))->assertCreated();

    Notification::assertSentTo([$firstAssignee, $secondAssignee], TaskAssigned::class);
    Notification::assertSentTo($watcher, TaskObserver::class);
    Notification::assertSentTimes(TaskAssigned::class, 2);
    Notification::assertSentTimes(TaskObserver::class, 1);
    Notification::assertNotSentTo([$actor, $requester, $watcher], TaskAssigned::class);
    Notification::assertNotSentTo([$actor, $requester, $firstAssignee, $secondAssignee], TaskObserver::class);
});

it('AC-013: POST where the creator is the only assignee sends no TaskAssigned (D-3)', function () {
    $actor = taskNotificationActor(['create', 'view']);
    Sanctum::actingAs($actor);
    Notification::fake();

    $this->postJson('/api/tasks', taskNotificationPayload([
        'assignee_ids' => [$actor->id],
    ]))->assertCreated();

    Notification::assertNothingSent();
});

// ---------------------------------------------------------------------------
// AC-014..AC-017 — modifica: solo chi entra (D-9)
// ---------------------------------------------------------------------------

it('AC-014: PATCH adding one assignee notifies only the newcomer', function () {
    $actor = taskNotificationActor(['view', 'update']);
    $first = User::factory()->create();
    $second = User::factory()->create();
    $newcomer = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->create();
    $task->assignees()->attach([$first->id, $second->id]);
    Sanctum::actingAs($actor);
    Notification::fake();

    $this->patchJson("/api/tasks/{$task->id}", [
        'assignee_ids' => [$first->id, $second->id, $newcomer->id],
    ])->assertOk();

    Notification::assertSentTo($newcomer, TaskAssigned::class);
    Notification::assertSentTimes(TaskAssigned::class, 1);
    Notification::assertNotSentTo([$first, $second, $actor], TaskAssigned::class);
});

it('AC-015: PATCH removing an assignee notifies nobody: the removal is silent', function () {
    $actor = taskNotificationActor(['view', 'update']);
    $kept = User::factory()->create();
    $removed = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->create();
    $task->assignees()->attach([$kept->id, $removed->id]);
    Sanctum::actingAs($actor);
    Notification::fake();

    $this->patchJson("/api/tasks/{$task->id}", ['assignee_ids' => [$kept->id]])->assertOk();

    Notification::assertNothingSent();
});

it('AC-016: PATCH adding one watcher notifies only the newcomer with TaskObserver', function () {
    $actor = taskNotificationActor(['view', 'update']);
    $existing = User::factory()->create();
    $newcomer = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->create();
    $task->watchers()->attach($existing->id);
    Sanctum::actingAs($actor);
    Notification::fake();

    $this->patchJson("/api/tasks/{$task->id}", [
        'watcher_ids' => [$existing->id, $newcomer->id],
    ])->assertOk();

    Notification::assertSentTo($newcomer, TaskObserver::class);
    Notification::assertSentTimes(TaskObserver::class, 1);
    Notification::assertNotSentTo([$existing, $actor], TaskObserver::class);
    Notification::assertSentTimes(TaskAssigned::class, 0);
});

it('AC-017: PATCH touching neither pivot notifies nobody', function () {
    $actor = taskNotificationActor(['view', 'update']);
    $assignee = User::factory()->create();
    $watcher = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->create();
    $task->assignees()->attach($assignee->id);
    $task->watchers()->attach($watcher->id);
    Sanctum::actingAs($actor);
    Notification::fake();

    $this->patchJson("/api/tasks/{$task->id}", ['title' => 'Solo il titolo'])->assertOk();

    Notification::assertNothingSent();
});

// ---------------------------------------------------------------------------
// AC-018 — un PATCH rifiutato non notifica nulla
// ---------------------------------------------------------------------------

it('AC-018: a PATCH refused by the watcher overlap guard (422) notifies nobody', function () {
    $actor = taskNotificationActor(['view', 'update']);
    $assignee = User::factory()->create();
    $newcomer = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->create();
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($actor);
    Notification::fake();

    $this->patchJson("/api/tasks/{$task->id}", [
        'assignee_ids' => [$assignee->id, $newcomer->id],
        'watcher_ids' => [$newcomer->id],
    ])->assertStatus(422)->assertJsonValidationErrors('watcher_ids');

    Notification::assertNothingSent();
});

it('AC-018: a PATCH refused by the structural write lock (422) notifies nobody', function () {
    $actor = taskNotificationActor(['view', 'update']);
    $newcomer = User::factory()->create();
    $closed = TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->create();
    $task = Task::factory()->forCreator($actor)->inStatus($closed)->create();
    Sanctum::actingAs($actor);
    Notification::fake();

    $this->patchJson("/api/tasks/{$task->id}", ['assignee_ids' => [$newcomer->id]])
        ->assertStatus(422)->assertJsonValidationErrors('assignee_ids');

    Notification::assertNothingSent();
});
