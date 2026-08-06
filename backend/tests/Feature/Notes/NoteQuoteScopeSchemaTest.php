<?php

use App\Models\Note;
use App\Models\Opportunity;
use App\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Spec 0085, D-1: `notes.quote_id` — nullable scoping column, FK -> quotes,
// cascadeOnDelete. Schema/reversibility (AC-001) and the cascade itself
// (AC-002).

uses(RefreshDatabase::class);

it('creates notes.quote_id nullable, FK-backed; the rollback removes it cleanly (AC-001)', function () {
    expect(Schema::hasColumn('notes', 'quote_id'))->toBeTrue();

    $migration = require database_path('migrations/2026_08_06_110000_add_quote_id_to_notes_table.php');

    $migration->down();
    expect(Schema::hasColumn('notes', 'quote_id'))->toBeFalse();

    $migration->up();
    expect(Schema::hasColumn('notes', 'quote_id'))->toBeTrue();
});

it('deleting an Offerta cascades to its own notes; the Opportunity\'s general notes survive (AC-002)', function () {
    $opportunity = Opportunity::factory()->create();
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);

    $generalNote = Note::factory()->create(['notable_type' => 'opportunity', 'notable_id' => $opportunity->id]);
    $quoteNote = Note::factory()->create(['notable_type' => 'opportunity', 'notable_id' => $opportunity->id]);
    $quoteNote->forceFill(['quote_id' => $quote->id])->save();

    DB::table('quotes')->where('id', $quote->id)->delete();

    expect(Note::query()->find($quoteNote->id))->toBeNull();
    expect(Note::query()->find($generalNote->id))->not->toBeNull();
});
