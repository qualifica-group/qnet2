<?php

use App\Models\Contract;
use App\Models\Quote;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

// TestCase is already bound to the whole Feature directory (tests/Pest.php);
// only RefreshDatabase needs adding here, mirroring RewardStatusCrudTest's
// own sibling.
uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-018, amended by the user directive of 2026-08-31: the D-2 seed plus the
// positive-outcome row "Validato" — 8 rows, the head sequence now two rows
// long, so every later row shifted +10.
// ---------------------------------------------------------------------------

it('seeds exactly the 8 rows, ordered by sort_order', function () {
    $rows = DB::table('contract_statuses')->orderBy('sort_order')->get(['name', 'group', 'system_key', 'sort_order', 'is_default', 'is_active']);

    expect($rows)->toHaveCount(8);

    $expected = [
        ['name' => 'Da validare', 'group' => 'open', 'system_key' => 'new', 'sort_order' => 0],
        ['name' => 'Validato', 'group' => 'closed_won', 'system_key' => 'validated', 'sort_order' => 10],
        ['name' => 'Da programmare', 'group' => 'pending', 'system_key' => null, 'sort_order' => 20],
        ['name' => 'Programmato', 'group' => 'pending', 'system_key' => null, 'sort_order' => 30],
        ['name' => 'In scadenza', 'group' => 'pending', 'system_key' => null, 'sort_order' => 40],
        ['name' => 'Sospeso', 'group' => 'pending', 'system_key' => 'suspended', 'sort_order' => 50],
        ['name' => 'Annullato', 'group' => 'closed_lost', 'system_key' => 'cancelled', 'sort_order' => 60],
        ['name' => 'Disdetto', 'group' => 'closed_lost', 'system_key' => 'terminated', 'sort_order' => 70],
    ];

    foreach ($expected as $index => $row) {
        expect($rows[$index]->name)->toBe($row['name'])
            ->and($rows[$index]->group)->toBe($row['group'])
            ->and($rows[$index]->system_key)->toBe($row['system_key'])
            ->and($rows[$index]->sort_order)->toBe($row['sort_order']);
    }
});

it('"Da validare" is the only row with is_default = true', function () {
    $defaults = DB::table('contract_statuses')->where('is_default', true)->pluck('name');

    expect($defaults->all())->toBe(['Da validare']);
});

it('all 8 seeded rows are active', function () {
    $inactiveCount = DB::table('contract_statuses')->where('is_active', false)->count();

    expect($inactiveCount)->toBe(0);
});

it('the five system rows carry the expected system_key and the three custom rows have none', function () {
    $systemRows = DB::table('contract_statuses')->whereNotNull('system_key')->orderBy('sort_order')->pluck('system_key', 'name');
    $customRows = DB::table('contract_statuses')->whereNull('system_key')->pluck('name');

    expect($systemRows->all())->toBe([
        'Da validare' => 'new',
        'Validato' => 'validated',
        'Sospeso' => 'suspended',
        'Annullato' => 'cancelled',
        'Disdetto' => 'terminated',
    ]);
    expect($customRows->sort()->values()->all())->toBe(['Da programmare', 'In scadenza', 'Programmato']);
});

// ---------------------------------------------------------------------------
// contracts table (AC-008, D-6): `quote_id` cascadeOnDelete + unique.
// Placed here (schema-level, no domain service yet) rather than a dedicated
// ContractTest, which is out of MT-00's write surface.
// ---------------------------------------------------------------------------

it('deleting the Quote cascades to delete its Contract row (AC-008, D-6)', function () {
    $contract = Contract::factory()->create();
    $quoteId = $contract->quote_id;

    Quote::find($quoteId)->delete();

    expect(Contract::find($contract->id))->toBeNull()
        ->and(Quote::find($quoteId))->toBeNull();
});

it('deleting one Quote does not touch another Contract row (AC-008)', function () {
    $untouched = Contract::factory()->create();
    $toDelete = Contract::factory()->create();

    Quote::find($toDelete->quote_id)->delete();

    expect(Contract::find($untouched->id))->not->toBeNull();
});

it('quote_id is unique: a second contract for the same quote is rejected', function () {
    $contract = Contract::factory()->create();

    expect(fn () => Contract::factory()->create(['quote_id' => $contract->quote_id]))
        ->toThrow(QueryException::class);
});
