<?php

use App\Models\TimeEntryDayNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| PUT /api/time-entries/day-notes (spec 0122, AC-021)
|--------------------------------------------------------------------------
|
| AC-021's own wording also asserts `day_note` on a DaySummary row of
| `GET /api/time-entries` — out of MT-B2's scope (the list endpoint is
| MT-B3+). This suite covers the write path itself: the persisted
| `time_entry_day_notes` row and the `{date, note}` response shape.
*/

if (! function_exists('dayNoteActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function dayNoteActorWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'exportMonthly', 'manageAll', 'viewAll'] as $ability) {
            Permission::findOrCreate("time-entries.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("time-entries.{$ability}");
        }

        return $user;
    }
}

it('AC-021: PUT with note "abc" is 200, persists and echoes {date, note}', function () {
    $actor = dayNoteActorWith(['update']);
    Sanctum::actingAs($actor);

    $this->putJson('/api/time-entries/day-notes', ['date' => '2026-09-14', 'note' => 'abc'])
        ->assertOk()
        ->assertJsonPath('data.date', '2026-09-14')
        ->assertJsonPath('data.note', 'abc');

    $this->assertDatabaseHas('time_entry_day_notes', ['user_id' => $actor->id, 'date' => '2026-09-14', 'note' => 'abc']);
});

it('AC-021: a repeat PUT with a blank note removes it', function () {
    $actor = dayNoteActorWith(['update']);
    TimeEntryDayNote::factory()->forUser($actor)->onDate('2026-09-14')->create(['note' => 'abc']);
    Sanctum::actingAs($actor);

    $this->putJson('/api/time-entries/day-notes', ['date' => '2026-09-14', 'note' => '  '])
        ->assertOk()
        ->assertJsonPath('data.note', null);

    $this->assertDatabaseMissing('time_entry_day_notes', ['user_id' => $actor->id, 'date' => '2026-09-14']);
});

it('AC-021: user_id of someone else, without manageAll, is 403', function () {
    $actor = dayNoteActorWith(['update']);
    $other = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->putJson('/api/time-entries/day-notes', ['user_id' => $other->id, 'date' => '2026-09-14', 'note' => 'abc'])
        ->assertForbidden();
});

it('AC-021: user_id of someone else, with manageAll, writes that user\'s note', function () {
    $actor = dayNoteActorWith(['update', 'manageAll']);
    $other = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->putJson('/api/time-entries/day-notes', ['user_id' => $other->id, 'date' => '2026-09-14', 'note' => 'abc'])
        ->assertOk();

    $this->assertDatabaseHas('time_entry_day_notes', ['user_id' => $other->id, 'date' => '2026-09-14', 'note' => 'abc']);
});

it('without time-entries.update the request is 403', function () {
    $actor = dayNoteActorWith([]);
    Sanctum::actingAs($actor);

    $this->putJson('/api/time-entries/day-notes', ['date' => '2026-09-14', 'note' => 'abc'])
        ->assertForbidden();
});
