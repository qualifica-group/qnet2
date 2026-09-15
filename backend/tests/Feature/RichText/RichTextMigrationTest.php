<?php

use App\Models\TaskStatus;
use App\Models\TaskTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| D-10 / AC-014 — legacy plain text -> rich text HTML backfill
|--------------------------------------------------------------------------
|
| RefreshDatabase already runs `2026_09_15_120000_convert_rich_text_columns_
| to_html.php`'s up() once, on empty tables — a no-op. Every row here is
| inserted straight through the query builder AFTER that (bypassing
| Note/Task/TaskTemplate/TaskTemplateItem services entirely, so the legacy
| plain text is exactly what the assertions expect), then the migration
| file is required and its up()/down() are invoked directly, mirroring
| NoteMigrationRollbackTest's own precedent.
*/

uses(RefreshDatabase::class);

if (! function_exists('richTextColumnsMigration')) {
    function richTextColumnsMigration(): object
    {
        return require database_path('migrations/2026_09_15_120000_convert_rich_text_columns_to_html.php');
    }
}

it('converts notes.body: blank-line paragraphs, single newlines and a mention token (AC-014)', function () {
    $user = User::factory()->create();
    $noteId = DB::table('notes')->insertGetId([
        'user_id' => $user->id,
        'body' => "Line one\nLine two\n\nSecond paragraph with @[Anna Rossi](user:3) mention.",
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    richTextColumnsMigration()->up();

    $html = DB::table('notes')->where('id', $noteId)->value('body');

    expect($html)->toBe(
        '<p>Line one<br>Line two</p>'
        .'<p>Second paragraph with <span data-type="mention" data-id="3" data-label="Anna Rossi">@Anna Rossi</span> mention.</p>'
    );
});

it('converts a soft-deleted note too, since the query builder ignores the soft-delete scope', function () {
    $user = User::factory()->create();
    $noteId = DB::table('notes')->insertGetId([
        'user_id' => $user->id,
        'body' => 'Trashed note body',
        'deleted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    richTextColumnsMigration()->up();

    expect(DB::table('notes')->where('id', $noteId)->value('body'))->toBe('<p>Trashed note body</p>');
});

it('falls back to an escaped <p> for a whitespace-only note body, since notes.body is NOT NULL', function () {
    $user = User::factory()->create();
    $noteId = DB::table('notes')->insertGetId([
        'user_id' => $user->id,
        'body' => '   ',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    richTextColumnsMigration()->up();

    expect(DB::table('notes')->where('id', $noteId)->value('body'))->toBe('<p>   </p>');
});

it('converts tasks.description: escapes < and &, and leaves a mention token as plain text (AC-002/AC-014)', function () {
    $status = TaskStatus::factory()->create();
    $creator = User::factory()->create();
    $taskId = DB::table('tasks')->insertGetId([
        'title' => 'Legacy task',
        'description' => "Check A & B < C\n\n@[Anna Rossi](user:3) please review",
        'task_status_id' => $status->id,
        'creator_id' => $creator->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    richTextColumnsMigration()->up();

    $html = DB::table('tasks')->where('id', $taskId)->value('description');

    expect($html)->toBe(
        '<p>Check A &amp; B &lt; C</p><p>@[Anna Rossi](user:3) please review</p>'
    );
});

it('leaves a null tasks.description untouched', function () {
    $status = TaskStatus::factory()->create();
    $creator = User::factory()->create();
    $taskId = DB::table('tasks')->insertGetId([
        'title' => 'No description',
        'description' => null,
        'task_status_id' => $status->id,
        'creator_id' => $creator->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    richTextColumnsMigration()->up();

    expect(DB::table('tasks')->where('id', $taskId)->value('description'))->toBeNull();
});

it('converts task_templates.description and task_template_items.description (AC-014)', function () {
    $template = TaskTemplate::factory()->create(['description' => null]);
    DB::table('task_templates')->where('id', $template->id)->update(['description' => "Header line one\n\nHeader line two"]);

    $itemId = DB::table('task_template_items')->insertGetId([
        'task_template_id' => $template->id,
        'title' => 'Item',
        'description' => "Step one\nStep two",
        'due_offset_days' => 0,
        'sort_order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    richTextColumnsMigration()->up();

    expect(DB::table('task_templates')->where('id', $template->id)->value('description'))
        ->toBe('<p>Header line one</p><p>Header line two</p>');
    expect(DB::table('task_template_items')->where('id', $itemId)->value('description'))
        ->toBe('<p>Step one<br>Step two</p>');
});

it('is idempotent: an already-clean HTML value is left byte-identical on re-run', function () {
    $template = TaskTemplate::factory()->create(['description' => '<p>Already converted</p>']);

    richTextColumnsMigration()->up();

    expect(DB::table('task_templates')->where('id', $template->id)->value('description'))
        ->toBe('<p>Already converted</p>');
});

it('sanitizes a legacy already-HTML row instead of skipping it, and stays stable on re-run', function () {
    $template = TaskTemplate::factory()->create(['description' => '<p><script>x</script>ciao</p>']);

    $migration = richTextColumnsMigration();
    $migration->up();

    $sanitized = DB::table('task_templates')->where('id', $template->id)->value('description');
    expect($sanitized)->toBe('<p>ciao</p>');

    $migration->up();

    expect(DB::table('task_templates')->where('id', $template->id)->value('description'))->toBe($sanitized);
});

it('down() restores the original plain text for realistic content (round trip, AC-014)', function () {
    $user = User::factory()->create();
    $status = TaskStatus::factory()->create();
    $creator = User::factory()->create();
    $template = TaskTemplate::factory()->create(['description' => null]);

    $noteBody = "Line one\nLine two\n\nSecond paragraph with @[Anna Rossi](user:3) mention.";
    $taskDescription = "Check A & B < C\n\n@[Anna Rossi](user:3) please review";
    $itemDescription = "Step one\nStep two";

    $noteId = DB::table('notes')->insertGetId([
        'user_id' => $user->id,
        'body' => $noteBody,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $taskId = DB::table('tasks')->insertGetId([
        'title' => 'Legacy task',
        'description' => $taskDescription,
        'task_status_id' => $status->id,
        'creator_id' => $creator->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $itemId = DB::table('task_template_items')->insertGetId([
        'task_template_id' => $template->id,
        'title' => 'Item',
        'description' => $itemDescription,
        'due_offset_days' => 0,
        'sort_order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = richTextColumnsMigration();
    $migration->up();
    $migration->down();

    expect(DB::table('notes')->where('id', $noteId)->value('body'))->toBe($noteBody);
    expect(DB::table('tasks')->where('id', $taskId)->value('description'))->toBe($taskDescription);
    expect(DB::table('task_template_items')->where('id', $itemId)->value('description'))->toBe($itemDescription);
});

it('down() leaves a null description untouched', function () {
    $template = TaskTemplate::factory()->create(['description' => null]);

    $migration = richTextColumnsMigration();
    $migration->up();
    $migration->down();

    expect(DB::table('task_templates')->where('id', $template->id)->value('description'))->toBeNull();
});
