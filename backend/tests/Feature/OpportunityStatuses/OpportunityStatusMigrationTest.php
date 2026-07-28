<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| AC-001 / BR-1/BR-2 — schema migration + mandatory status FK
|--------------------------------------------------------------------------
|
| Not using RefreshDatabase: this test drives a real Artisan migrate call to
| assert what a from-scratch install produces, which RefreshDatabase's per-test
| transaction wrapping would get in the way of. It brings the schema back to a
| fully migrated, empty state via `migrate:fresh` before it exits, so it leaves
| no trace for the rest of the suite.
*/

it('migrate:fresh runs clean on an empty database (AC-001)', function () {
    Artisan::call('migrate:fresh');

    expect(Schema::hasTable('opportunity_statuses'))->toBeTrue();
    expect(Schema::hasColumn('opportunities', 'opportunity_status_id'))->toBeTrue();
    // spec 0043, D-1/D-2: the create migration seeds the 3 mandatory rows
    // ("Nuova"/"Chiusa con successo"/"Persa") unconditionally, even on an
    // empty database.
    expect(DB::table('opportunity_statuses')->count())->toBe(3);

    $rows = DB::table('opportunity_statuses')->orderBy('sort_order')->get(['name', 'system_key', 'group', 'sort_order']);
    expect($rows->pluck('system_key')->all())->toBe(['new', 'won', 'lost']);
    expect($rows->firstWhere('system_key', 'new')->sort_order)->toBe(0);
    expect($rows->firstWhere('system_key', 'new')->group)->toBe('open');
    expect($rows->firstWhere('system_key', 'lost')->group)->toBe('closed');

    // BR-2: the column is NOT NULL — an insert that omits it must fail, not
    // silently store a null FK.
    $registryId = DB::table('registries')->insertGetId(['name' => 'Mandatory FK Registry']);
    $insertWithoutStatus = fn () => DB::table('opportunities')->insert([
        'name' => 'No status',
        'registry_id' => $registryId,
    ]);
    expect($insertWithoutStatus)->toThrow(QueryException::class);

    Artisan::call('migrate:fresh');
});
