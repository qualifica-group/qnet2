<?php

use App\Enums\QuoteStatusGroup;
use App\Enums\StatusSystemKey;
use App\Models\Concerns\LogsModelActivity;
use App\Models\Quote;
use App\Models\QuoteStatus;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Touches the database (migrations, factories), so bind the full TestCase +
// RefreshDatabase explicitly, mirroring OpportunityStatus's own sibling.
uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// schema (AC-010)
// ---------------------------------------------------------------------------

it('creates the quote_statuses table with the expected columns', function () {
    expect(Schema::hasTable('quote_statuses'))->toBeTrue();
    expect(Schema::hasColumns('quote_statuses', [
        'id', 'name', 'color', 'sort_order', 'system_key', 'group', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('seeds exactly the 3 system rows with the expected system_key/sort_order/group (AC-010)', function () {
    $rows = DB::table('quote_statuses')->orderBy('sort_order')->get(['system_key', 'sort_order', 'group']);

    expect($rows)->toHaveCount(3)
        ->and($rows[0]->system_key)->toBe('new')
        ->and($rows[0]->sort_order)->toBe(0)
        ->and($rows[0]->group)->toBe('open')
        ->and($rows[1]->system_key)->toBe('won')
        ->and($rows[1]->sort_order)->toBe(10)
        ->and($rows[1]->group)->toBe('closed_won')
        ->and($rows[2]->system_key)->toBe('lost')
        ->and($rows[2]->sort_order)->toBe(20)
        ->and($rows[2]->group)->toBe('closed_lost');
});

it('name is unique at the database level', function () {
    QuoteStatus::factory()->create(['name' => 'Duplicate']);

    expect(fn () => QuoteStatus::factory()->create(['name' => 'Duplicate']))
        ->toThrow(QueryException::class);
});

// ---------------------------------------------------------------------------
// model: casts, relations, isSystem(), activity log
// ---------------------------------------------------------------------------

it('casts sort_order to int and group to QuoteStatusGroup', function () {
    $status = QuoteStatus::factory()->create(['sort_order' => '5', 'group' => QuoteStatusGroup::Pending]);

    expect($status->sort_order)->toBeInt()->toBe(5)
        ->and($status->group)->toBe(QuoteStatusGroup::Pending);
});

it('casts the split closed outcomes to QuoteStatusGroup', function () {
    $won = QuoteStatus::factory()->create(['group' => QuoteStatusGroup::ClosedWon]);
    $lost = QuoteStatus::factory()->create(['group' => QuoteStatusGroup::ClosedLost]);

    expect($won->fresh()->group)->toBe(QuoteStatusGroup::ClosedWon)
        ->and($lost->fresh()->group)->toBe(QuoteStatusGroup::ClosedLost);
});

it('quotes() is a HasMany relation to Quote', function () {
    $relation = (new QuoteStatus)->quotes();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Quote::class);
});

it('isSystem() distinguishes system rows from custom rows', function () {
    $system = QuoteStatus::factory()->system('new');
    $custom = QuoteStatus::factory();

    expect($system->make()->isSystem())->toBeTrue()
        ->and($custom->make()->isSystem())->toBeFalse();
});

it('SYSTEM_HEAD_KEYS is [New] and SYSTEM_TAIL_KEYS is [Won, Lost] in order', function () {
    expect(QuoteStatus::SYSTEM_HEAD_KEYS)->toBe([StatusSystemKey::New])
        ->and(QuoteStatus::SYSTEM_TAIL_KEYS)->toBe([StatusSystemKey::Won, StatusSystemKey::Lost]);
});

it('a status referencing a Quote restricts deletion at the schema level (AC-014)', function () {
    $status = QuoteStatus::factory()->create();
    Quote::factory()->create(['quote_status_id' => $status->id]);

    expect(fn () => DB::table('quote_statuses')->where('id', $status->id)->delete())
        ->toThrow(QueryException::class);
});

it('logs model activity on the quote_statuses log channel', function () {
    expect(class_uses(QuoteStatus::class))->toHaveKey(LogsModelActivity::class);
});
