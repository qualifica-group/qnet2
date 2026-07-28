<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| AC-001 — system statuses + `group` schema migration
|--------------------------------------------------------------------------
|
| Not using RefreshDatabase: this test drives a real Artisan migrate call to
| assert what a from-scratch install produces, which RefreshDatabase's per-test
| transaction wrapping would get in the way of. It brings the schema back to a
| fully migrated, empty state via `migrate:fresh` before it exits.
*/

it('produces exactly the two system pipeline statuses on an empty database (AC-001)', function () {
    Artisan::call('migrate:fresh');

    $rows = DB::table('pipeline_statuses')->orderBy('sort_order')->get();

    expect($rows)->toHaveCount(2);

    expect($rows[0]->name)->toBe('Nuovo');
    expect($rows[0]->system_key)->toBe('new');
    expect($rows[0]->sort_order)->toBe(0);
    expect($rows[0]->color)->toBe('slate');
    expect($rows[0]->group)->toBe('open');

    expect($rows[1]->name)->toBe('Chiuso');
    expect($rows[1]->system_key)->toBe('closed');
    expect($rows[1]->sort_order)->toBe(10);
    expect($rows[1]->color)->toBe('green');
    expect($rows[1]->group)->toBe('closed');

    Artisan::call('migrate:fresh');
});
