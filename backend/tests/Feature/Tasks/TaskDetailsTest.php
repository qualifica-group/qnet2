<?php

use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\User;
use App\Support\Notifications\DetailsTable;
use App\Support\Notifications\TaskDetails;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| App\Support\Notifications\TaskDetails (spec 0119, D-8, AC-029)
|--------------------------------------------------------------------------
|
| The detail card of the notification email. It returns i18n KEYS, not
| rendered labels: the translation happens downstream in DetailsTable,
| inside the recipient's own locale.
*/

it('builds the detail card with the six fields of D-8', function () {
    $requester = User::factory()->create(['name' => 'Mario Rossi']);
    $assignee = User::factory()->create(['name' => 'Anna Bianchi']);
    $priority = TaskPriority::factory()->create(['name' => 'Alta']);

    $task = Task::factory()->create([
        'title' => 'Rinnovo contratto Acme',
        'requester_id' => $requester->id,
        'task_priority_id' => $priority->id,
        'end_date' => '2026-03-22',
    ]);
    $task->assignees()->attach($assignee->id);

    $details = TaskDetails::for($task->fresh());

    expect($details)->toHaveKeys([
        'notifications.fields.title',
        'notifications.fields.status',
        'notifications.fields.priority',
        'notifications.fields.end_date',
        'notifications.fields.requester',
        'notifications.fields.assignees',
    ])
        ->and($details['notifications.fields.title'])->toBe('Rinnovo contratto Acme')
        ->and($details['notifications.fields.priority'])->toBe('Alta')
        ->and($details['notifications.fields.end_date'])->toBe('22/03/2026')
        ->and($details['notifications.fields.requester'])->toBe('Mario Rossi')
        ->and($details['notifications.fields.assignees'])->toBe('Anna Bianchi');
});

it('omits an empty field instead of rendering a blank row (AC-029)', function () {
    $task = Task::factory()->create([
        'requester_id' => null,
        'task_priority_id' => null,
        'end_date' => null,
    ]);

    $details = TaskDetails::for($task->fresh());

    expect($details)->not->toHaveKey('notifications.fields.requester')
        ->and($details)->not->toHaveKey('notifications.fields.priority')
        ->and($details)->not->toHaveKey('notifications.fields.end_date')
        ->and($details)->not->toHaveKey('notifications.fields.assignees')
        ->and($details)->toHaveKey('notifications.fields.title');
});

it('joins several assignees into one cell', function () {
    $task = Task::factory()->create();
    $task->assignees()->attach([
        User::factory()->create(['name' => 'Anna Bianchi'])->id,
        User::factory()->create(['name' => 'Carlo Verdi'])->id,
    ]);

    $details = TaskDetails::for($task->fresh());

    expect($details['notifications.fields.assignees'])
        ->toContain('Anna Bianchi')
        ->toContain('Carlo Verdi');
});

// REQUIREMENT CHANGED (spec 0381, D-1): DetailsTable returns rows, not markdown.
it('renders through DetailsTable as rows with translated labels', function () {
    // Fixture COMPLETA di proposito: con i campi nulli `compact()` li scarta e
    // la tabella resa porterebbe solo due label su sei, lasciando le altre
    // quattro senza alcuna asserzione di traduzione.
    $task = Task::factory()->create([
        'title' => 'Rinnovo contratto Acme',
        'requester_id' => User::factory()->create(['name' => 'Mario Rossi'])->id,
        'task_priority_id' => TaskPriority::factory()->create(['name' => 'Alta'])->id,
        'end_date' => '2026-03-22',
    ]);
    $task->assignees()->attach(User::factory()->create(['name' => 'Anna Bianchi'])->id);

    app()->setLocale('it');
    $rows = collect(DetailsTable::rows(TaskDetails::for($task->fresh())));

    expect($rows->pluck('label')->all())->toBe(['Titolo', 'Stato', 'Priorità', 'Data fine', 'Richiedente', 'Assegnatari'])
        ->and($rows->pluck('value'))->toContain('Rinnovo contratto Acme')
        // Nessuna chiave puntata deve sopravvivere fino alla riga resa.
        ->and($rows->pluck('label')->implode(' '))->not->toContain('notifications.fields.');
});
