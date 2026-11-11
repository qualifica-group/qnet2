<?php

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// Spec 0388 D-4 / AC-005: the legacy "generato_da_modello" flag on the task import.

beforeEach(function () {
    seedMigrationsConfig();
    legacyTaskCreator();
});

it('AC-005: the import writes the flag the legacy sends', function () {
    runLegacyTasksImport([
        legacyTaskRecord(['is_template_generated' => true]),
        legacyTaskRecord(['id' => 501]),
    ]);

    expect(Task::query()->where('old_id', 500)->firstOrFail()->is_template_generated)->toBeTrue()
        ->and(Task::query()->where('old_id', 501)->firstOrFail()->is_template_generated)->toBeFalse();
});

it('AC-005: a re-run fills the flag on a task already imported and rewrites nothing else', function () {
    runLegacyTasksImport([legacyTaskRecord()]);

    Http::swap(new Factory);
    $run = runLegacyTasksImport([legacyTaskRecord(['is_template_generated' => true, 'title' => 'Changed'])]);

    $task = Task::query()->where('old_id', 500)->firstOrFail();
    expect($task->is_template_generated)->toBeTrue()
        ->and($task->title)->toBe('Legacy task')
        ->and($run->created_rows)->toBe(1);
});

it('AC-005: a re-run with nothing to fill is skipped', function () {
    runLegacyTasksImport([legacyTaskRecord(['is_template_generated' => true])]);

    Http::swap(new Factory);
    $run = runLegacyTasksImport([legacyTaskRecord(['is_template_generated' => true])]);

    expect($run->skipped_rows)->toBe(1)
        ->and($run->created_rows)->toBe(0);
});
