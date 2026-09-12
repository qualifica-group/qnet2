<?php

use App\Enums\NotificationLevelEnum;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskClosed;
use App\Notifications\TaskFeedbackInserted;
use App\Notifications\TaskLocked;
use App\Notifications\TaskNotification;
use App\Notifications\TaskObserver;
use App\Notifications\TaskUnCompleted;
use App\Notifications\TaskUnLocked;
use App\Notifications\TaskValidationApproved;
use App\Notifications\TaskValidationRejected;
use App\Notifications\TaskValidationReopened;
use App\Notifications\TaskValidationRequested;
use App\Services\Tasks\TaskNotable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Le 11 classi di App\Notifications\Task* (spec 0119, D-1/D-7/D-11,
| AC-001..AC-005, AC-030..AC-032)
|--------------------------------------------------------------------------
|
| Le classi in ISOLAMENTO: nomi, canali, payload, livello, deep link per
| destinatario e localizzazione. Chi le spedisce (TaskNotifier, A-03) e i
| write path che lo chiamano (A-04/A-05) sono fuori da questo file.
*/

/**
 * Le 11 voci del documento, con il livello che D-11 assegna a ciascuna.
 *
 * @return array<class-string<TaskNotification>, NotificationLevelEnum>
 */
function taskNotificationCatalogue(): array
{
    return [
        TaskValidationRequested::class => NotificationLevelEnum::Info,
        TaskClosed::class => NotificationLevelEnum::Info,
        TaskFeedbackInserted::class => NotificationLevelEnum::Info,
        TaskValidationApproved::class => NotificationLevelEnum::Success,
        TaskValidationRejected::class => NotificationLevelEnum::Warning,
        TaskValidationReopened::class => NotificationLevelEnum::Warning,
        TaskAssigned::class => NotificationLevelEnum::Info,
        TaskObserver::class => NotificationLevelEnum::Info,
        TaskUnCompleted::class => NotificationLevelEnum::Warning,
        TaskLocked::class => NotificationLevelEnum::Warning,
        TaskUnLocked::class => NotificationLevelEnum::Info,
    ];
}

/** Un destinatario che PUO' leggere $task: ha `tasks.view` ed e' un suo assegnatario. */
function taskNotificationReader(Task $task): User
{
    Permission::findOrCreate('tasks.view');

    $user = User::factory()->create();
    $user->givePermissionTo('tasks.view');
    $task->assignees()->attach($user->id);

    return $user;
}

it('ships exactly the eleven classes the document names, none renamed (AC-001, D-1)', function () {
    foreach (array_keys(taskNotificationCatalogue()) as $class) {
        expect(class_exists($class))->toBeTrue("missing notification class {$class}");
    }

    // I due nomi che il documento scrive con la maiuscola in mezzo sono dati
    // persistiti nella colonna `type`, non dettagli estetici. Vanno letti dal
    // FILESYSTEM: il file system di macOS e' case-insensitive e class_exists()
    // risolverebbe felicemente anche `TaskUncompleted`.
    $files = array_map('basename', glob(app_path('Notifications/Task*.php')) ?: []);

    expect($files)->toContain('TaskUnCompleted.php')
        ->and($files)->toContain('TaskUnLocked.php');
});

it('queues every one of them on the database and mail channels (AC-001)', function () {
    $task = Task::factory()->create();
    $actor = User::factory()->create();
    $recipient = taskNotificationReader($task);

    foreach (array_keys(taskNotificationCatalogue()) as $class) {
        $notification = new $class($task, $actor);

        expect($notification)->toBeInstanceOf(ShouldQueue::class, $class)
            ->and($notification->via($recipient))->toBe(['database', 'mail'], $class);
    }
});

it('emits the four payload keys with a valid level (AC-002, D-11)', function () {
    $task = Task::factory()->create();
    $actor = User::factory()->create();
    $recipient = taskNotificationReader($task);

    foreach (taskNotificationCatalogue() as $class => $expectedLevel) {
        $payload = (new $class($task, $actor))->toArray($recipient);

        expect(array_keys($payload))->toBe(['title', 'message', 'level', 'action_url'], $class)
            ->and($payload['level'])->toBe($expectedLevel->value, $class)
            ->and($payload['title'])->not->toBeEmpty($class)
            ->and($payload['message'])->not->toBeEmpty($class);
    }
});

it('names the task in every in-app message so the bell reads without opening it (AC-032)', function () {
    $task = Task::factory()->create(['title' => 'Rinnovo contratto Acme']);
    $actor = User::factory()->create(['name' => 'Mario Rossi']);
    $recipient = taskNotificationReader($task);

    foreach (array_keys(taskNotificationCatalogue()) as $class) {
        $payload = (new $class($task, $actor))->toArray($recipient);

        expect($payload['message'])->toContain('Rinnovo contratto Acme')
            ->and($payload['message'])->toContain('Mario Rossi');
    }
});

it('names the system when no actor performed the write', function () {
    $task = Task::factory()->create();
    $recipient = taskNotificationReader($task);

    $payload = (new TaskAssigned($task, null))->toArray($recipient);

    expect($payload['message'])->toContain('system');
});

it('resolves a relative deep link and a mail button for a recipient who can read the task (AC-004, D-7)', function () {
    $task = Task::factory()->create();
    $actor = User::factory()->create();
    $recipient = taskNotificationReader($task);

    foreach (array_keys(taskNotificationCatalogue()) as $class) {
        $notification = new $class($task, $actor);

        $payload = $notification->toArray($recipient);
        expect($payload['action_url'])->toBe("/tasks/{$task->id}", $class)
            ->and($payload['action_url'])->not->toStartWith('http', $class);

        $mail = $notification->toMail($recipient);
        expect($mail->actionUrl)->not->toBeNull($class)
            ->and($mail->actionUrl)->toContain("/tasks/{$task->id}")
            // Nessuna doppia barra fra host e path.
            ->and($mail->actionUrl)->not->toContain('//tasks');
    }
});

it('nulls the deep link and drops the mail button for a recipient who cannot read the task (AC-003, D-7)', function () {
    $task = Task::factory()->create();
    $actor = User::factory()->create();
    $outsider = User::factory()->create();

    foreach (array_keys(taskNotificationCatalogue()) as $class) {
        $notification = new $class($task, $actor);

        $payload = $notification->toArray($outsider);
        expect($payload['action_url'])->toBeNull($class)
            ->and($payload['message'])->toContain('ask an administrator');

        expect($notification->toMail($outsider)->actionUrl)->toBeNull($class);
    }
});

it('carries the detail card in the mail but not in the bell (AC-029, D-8)', function () {
    $task = Task::factory()->create(['title' => 'Rinnovo contratto Acme']);
    $recipient = taskNotificationReader($task);

    $notification = new TaskClosed($task, User::factory()->create());

    $mailBody = collect($notification->toMail($recipient)->introLines)
        ->map(static fn ($line): string => (string) $line)
        ->implode("\n");

    expect($mailBody)->toContain('| :--- | :--- |')
        ->and($mailBody)->toContain('Rinnovo contratto Acme');

    expect($notification->toArray($recipient)['message'])->not->toContain('| :--- |');
});

it('presents already-known facts instead of querying for more (AC-005)', function () {
    $task = Task::factory()->create();
    $actor = User::factory()->create();
    $recipient = taskNotificationReader($task);
    $task->load(['assignees', 'watchers', 'taskStatus', 'taskPriority', 'requester']);

    // Una Notification presenta fatti che il chiamante gia' possiede: con le
    // relazioni caricate e le cache dei permessi calde, comporre il payload
    // non deve costare NEPPURE UNA query. Il warm-up serve a neutralizzare la
    // prima risoluzione, che popola la cache di spatie; misurare senza di
    // esso confonderebbe il costo della cache fredda con quello della classe.
    //
    // UN SOLO listener, azzerato fra le fasi: registrarne due lascia il primo
    // attivo durante la seconda e falsa il confronto.
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    app(TaskNotable::class)->deepLinkPath($task, $recipient, null);

    $queries = 0;
    (new TaskClosed($task, $actor))->toArray($recipient);

    expect($queries)->toBe(0);
});

it('renders in each recipient own language (AC-030)', function () {
    $task = Task::factory()->create();
    $actor = User::factory()->create();
    $recipient = taskNotificationReader($task);

    app()->setLocale('it');
    $italian = (new TaskLocked($task, $actor))->toArray($recipient);

    app()->setLocale('en');
    $english = (new TaskLocked($task, $actor))->toArray($recipient);

    expect($italian['title'])->toBe('Attività bloccata')
        ->and($english['title'])->toBe('Task blocked')
        ->and($italian['message'])->not->toBe($english['message']);
});

it('has an italian translation for every string it introduces (AC-031)', function () {
    $json = json_decode((string) file_get_contents(lang_path('it.json')), true, flags: JSON_THROW_ON_ERROR);
    $italian = require lang_path('it/notifications.php');
    $english = require lang_path('en/notifications.php');

    // Il perimetro e' quello di QUESTA spec: le 11 classi, la loro base e la
    // scheda dettagli. `TaskUpdateRequested` e' della spec 0118 e resta
    // invariata per AC-033 — il suo buco di traduzione e' segnalato a parte,
    // non sanato qui (engineering.md §1.6: fuori scope si segnala).
    $sources = array_map(
        static fn (string $class): string => app_path('Notifications/'.class_basename($class).'.php'),
        array_keys(taskNotificationCatalogue()),
    );
    $sources[] = app_path('Notifications/TaskNotification.php');
    $sources[] = app_path('Support/Notifications/TaskDetails.php');

    $untranslated = [];
    $found = 0;

    foreach ($sources as $file) {
        $code = (string) file_get_contents($file);

        // DUE forme, non una. Le classi passano le stringhe a `__()`; TaskDetails
        // invece RESTITUISCE le chiavi puntate come stringhe letterali, e le
        // traduce DetailsTable a valle. Cercando il solo `__()` questa seconda
        // meta' non verificherebbe nulla: sei label sfuggirebbero in silenzio.
        preg_match_all("/__\('((?:[^'\\\\]|\\\\.)*)'/", $code, $calls);
        preg_match_all("/'(notifications\.[a-z_.]+)'/", $code, $literals);

        $keys = array_unique([...$calls[1], ...$literals[1]]);
        expect($keys)->not->toBeEmpty('nessuna chiave estratta da '.basename($file).': la scansione e\' vacua');

        foreach ($keys as $key) {
            $found++;
            $key = str_replace("\\'", "'", $key);

            if (str_starts_with($key, 'notifications.')) {
                $leaf = substr($key, strlen('notifications.'));
                // Le label della scheda vivono nei file PHP e devono esistere in
                // ENTRAMBE le lingue: la mail si rende nel locale del destinatario.
                foreach (['it' => $italian, 'en' => $english] as $locale => $catalogue) {
                    if (data_get($catalogue, $leaf) === null) {
                        $untranslated[] = basename($file).": {$key} ({$locale})";
                    }
                }

                continue;
            }

            if (! isset($json[$key])) {
                $untranslated[] = basename($file).': '.$key;
            }
        }
    }

    expect($untranslated)->toBe([])
        ->and($found)->toBeGreaterThan(20);
});
