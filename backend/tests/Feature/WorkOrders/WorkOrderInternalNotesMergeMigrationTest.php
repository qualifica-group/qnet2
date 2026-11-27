<?php

use App\Models\WorkOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Note commessa" become the "Descrizione" (user decision 2026-11-27): the
 * migration moves `internal_notes` into `description` and drops the column.
 * RefreshDatabase has already run it, so down() brings the column back to
 * plant the pre-merge data.
 */
uses(RefreshDatabase::class);

if (! function_exists('workOrderInternalNotesMergeMigration')) {
    function workOrderInternalNotesMergeMigration(): Migration
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/2026_12_28_100000_merge_internal_notes_into_description_on_work_orders_table.php');

        return $migration;
    }
}

it('moves the notes into the description, keeping an existing description after them, and drops the column', function () {
    $migration = workOrderInternalNotesMergeMigration();
    $notesOnly = WorkOrder::factory()->create(['description' => null]);
    $both = WorkOrder::factory()->create(['description' => 'Vecchia descrizione']);
    $descriptionOnly = WorkOrder::factory()->create(['description' => 'Solo descrizione']);
    $blankNotes = WorkOrder::factory()->create(['description' => null]);
    $updatedAt = DB::table('work_orders')->where('id', $both->id)->value('updated_at');
    $migration->down();
    DB::table('work_orders')->where('id', $notesOnly->id)->update(['internal_notes' => 'Note legacy']);
    DB::table('work_orders')->where('id', $both->id)->update(['internal_notes' => 'Note commessa']);
    DB::table('work_orders')->where('id', $blankNotes->id)->update(['internal_notes' => '']);

    $migration->up();

    $descriptions = DB::table('work_orders')->pluck('description', 'id');
    expect(Schema::hasColumn('work_orders', 'internal_notes'))->toBeFalse()
        ->and($descriptions[$notesOnly->id])->toBe('Note legacy')
        ->and($descriptions[$both->id])->toBe("Note commessa\n\nVecchia descrizione")
        ->and($descriptions[$descriptionOnly->id])->toBe('Solo descrizione')
        ->and($descriptions[$blankNotes->id])->toBeNull()
        ->and(DB::table('work_orders')->where('id', $both->id)->value('updated_at'))->toBe($updatedAt);
});
