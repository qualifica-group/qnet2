<?php

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskUpdateRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| App\Notifications\TaskUpdateRequested (spec 0153, D-14, superseding spec
| 0118 D-12/D-13's optional message)
|--------------------------------------------------------------------------
|
| The notification class in isolation: channels, the per-recipient deep
| link via TaskNotable::deepLinkPath(), the message body (now REQUIRED) and
| the `is_cc` flag the D-14 copy group carries. The endpoint that dispatches
| it (TaskActionService::requestUpdate()) is out of scope here.
*/

if (! function_exists('taskUpdateReader')) {
    /**
     * A recipient who CAN read $task: holds `tasks.view` and is one of its
     * membership roles (here, an assignee) — the AND that
     * TaskNotable::authorizeRead() applies.
     */
    function taskUpdateReader(Task $task): User
    {
        Permission::findOrCreate('tasks.view');

        $user = User::factory()->create();
        $user->givePermissionTo('tasks.view');
        $task->assignees()->attach($user->id);

        return $user;
    }
}

if (! function_exists('taskUpdateOutsider')) {
    /**
     * A recipient who canNOT read $task: no `tasks.view`, no membership row.
     */
    function taskUpdateOutsider(): User
    {
        return User::factory()->create();
    }
}

it('is delivered on the database and mail channels (AC-055)', function () {
    $task = Task::factory()->create();
    $requester = User::factory()->create();
    $recipient = taskUpdateReader($task);

    $notification = new TaskUpdateRequested($task, $requester, 'Serve un aggiornamento.');

    expect($notification->via($recipient))->toBe(['database', 'mail']);
});

it('resolves a relative deep link and a mail button for a recipient who can read the task (AC-056)', function () {
    $task = Task::factory()->create();
    $requester = User::factory()->create();
    $recipient = taskUpdateReader($task);

    $notification = new TaskUpdateRequested($task, $requester, 'Serve un aggiornamento.');

    $payload = $notification->toArray($recipient);
    expect($payload['action_url'])->toBe("/tasks/{$task->id}")
        ->and($payload['action_url'])->not->toStartWith('http');

    $mail = $notification->toMail($recipient);
    expect($mail->actionUrl)->not->toBeNull()
        ->and($mail->actionUrl)->toContain("/tasks/{$task->id}");
});

it('nulls the deep link and drops the mail button for a recipient who cannot read the task (AC-057)', function () {
    $task = Task::factory()->create();
    $requester = User::factory()->create();
    $recipient = taskUpdateOutsider();

    $notification = new TaskUpdateRequested($task, $requester, 'Serve un aggiornamento.');

    $payload = $notification->toArray($recipient);
    expect($payload['action_url'])->toBeNull();
    expect($payload['message'])->toContain('ask an administrator to grant you access to the module');

    $mail = $notification->toMail($recipient);
    expect($mail->actionUrl)->toBeNull();
});

it('always names the requester and the task, then appends the REQUIRED message (AC-053, spec 0153 D-14)', function () {
    $task = Task::factory()->create(['title' => 'Rinnovo contratto Acme']);
    $requester = User::factory()->create(['name' => 'Mario Rossi']);
    $recipient = taskUpdateReader($task);

    $notification = new TaskUpdateRequested($task, $requester, 'Serve lo stato entro venerdi.');
    $payload = $notification->toArray($recipient);

    expect($payload['title'])->not->toBeEmpty();
    expect($payload['message'])->not->toBeEmpty()
        ->and($payload['message'])->toContain('Mario Rossi')
        ->and($payload['message'])->toContain('Rinnovo contratto Acme')
        ->and($payload['message'])->toContain('Serve lo stato entro venerdi.');
});

it('defaults is_cc to false, and carries true when the caller marks it a copy (spec 0153, D-14)', function () {
    $task = Task::factory()->create();
    $requester = User::factory()->create();
    $recipient = taskUpdateReader($task);

    $direct = new TaskUpdateRequested($task, $requester, 'Serve un aggiornamento.');
    $copy = new TaskUpdateRequested($task, $requester, 'Serve un aggiornamento.', isCc: true);

    expect($direct->toArray($recipient)['is_cc'])->toBeFalse()
        ->and($copy->toArray($recipient)['is_cc'])->toBeTrue();
});

it('speaks the recipient\'s language: title and body are translated in italian', function () {
    $task = Task::factory()->create(['title' => 'Rinnovo contratto Acme']);
    $requester = User::factory()->create(['name' => 'Mario Rossi']);
    $recipient = taskUpdateReader($task);
    $notification = new TaskUpdateRequested($task, $requester, 'Serve lo stato entro venerdi.');

    app()->setLocale('it');
    $italian = $notification->toArray($recipient);

    app()->setLocale('en');
    $english = $notification->toArray($recipient);

    expect($italian['title'])->toBe('Aggiornamento richiesto')
        ->and($italian['message'])->toBe('Mario Rossi ha chiesto un aggiornamento sull\'attività "Rinnovo contratto Acme" Serve lo stato entro venerdi.')
        ->and($english['title'])->toBe('Update requested')
        ->and($english['message'])->toBe('Mario Rossi asked for an update on Rinnovo contratto Acme Serve lo stato entro venerdi.');
});
