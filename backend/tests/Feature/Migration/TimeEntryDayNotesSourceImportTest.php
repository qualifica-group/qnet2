<?php

use App\Models\MigrationRun;
use App\Models\TimeEntryDayNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// Spec 0341 D-8: the legacy segnatempo day notes.

beforeEach(function () {
    seedMigrationsConfig();
    $this->owner = User::factory()->create(['old_id' => 449]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function legacyTimeEntryDayNoteRecord(array $overrides = []): array
{
    return array_merge([
        'id' => 12,
        'user_id' => 449,
        'date' => '2026-05-20',
        'note' => 'Giornata dedicata al bugfix.',
        'created_at' => '2026-05-21 11:48:27',
        'updated_at' => '2026-05-21 11:49:17',
        'warnings' => [],
    ], $overrides);
}

/**
 * @param  array<int, array<string, mixed>>  $records
 */
function runLegacyTimeEntryDayNotesImport(array $records, ?User $actor = null): MigrationRun
{
    Http::fake([
        fakeMigrationsBaseUrl().'/time-entry-day-notes*' => Http::response([
            'items' => $records,
            'pagination' => ['total' => count($records)],
        ]),
    ]);

    $run = MigrationRun::factory()->create(['user_id' => ($actor ?? migrationsSuperAdminActor())->id, 'source' => 'time-entry-day-notes']);
    runMigrationJobFor($run);

    return $run->fresh();
}

it('AC-009 imports a day note with its owner, date, text and legacy dates, quietly', function () {
    $actor = migrationsSuperAdminActor();
    $activityBefore = DB::table('activity_log')->count();

    $run = runLegacyTimeEntryDayNotesImport([legacyTimeEntryDayNoteRecord()], $actor);
    $note = TimeEntryDayNote::query()->where('old_id', 12)->sole();

    expect($run->created_rows)->toBe(1)
        ->and($run->report)->toBeNull()
        ->and($note->user_id)->toBe($this->owner->id)
        ->and($note->date->toDateString())->toBe('2026-05-20')
        ->and($note->note)->toBe('Giornata dedicata al bugfix.')
        ->and($note->created_at->toDateTimeString())->toBe('2026-05-21 11:48:27')
        ->and(DB::table('activity_log')->count())->toBe($activityBefore);
});

it('AC-009 adopts the note already written in QNet for that day, keeping its text', function () {
    $existing = TimeEntryDayNote::factory()->create(['user_id' => $this->owner->id, 'date' => '2026-05-20', 'note' => 'Scritta in QNet']);

    $run = runLegacyTimeEntryDayNotesImport([legacyTimeEntryDayNoteRecord()]);

    expect(TimeEntryDayNote::query()->sole()->only(['id', 'old_id', 'note']))->toBe(['id' => $existing->id, 'old_id' => 12, 'note' => 'Scritta in QNet'])
        ->and(legacyTaskWarnings($run))->toBe(['A day note for 2026-05-20 already exists in QNet; it was linked to the legacy note and kept as is.']);
});

it('AC-009 skips a note already imported and fails the one of a user not migrated', function () {
    TimeEntryDayNote::factory()->create(['user_id' => $this->owner->id, 'date' => '2026-05-19'])->forceFill(['old_id' => 12])->save();

    $run = runLegacyTimeEntryDayNotesImport([legacyTimeEntryDayNoteRecord(), legacyTimeEntryDayNoteRecord(['id' => 13, 'user_id' => 99])]);

    expect($run->skipped_rows)->toBe(1)
        ->and($run->failed_rows)->toBe(1)
        ->and(legacyTaskErrors($run)[0])->toContain('User (legacy id 99) not migrated')
        ->and(TimeEntryDayNote::query()->count())->toBe(1);
});
