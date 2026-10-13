<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('AC-001: the existing cost types take nature direct and color slate, and rollback drops the columns', function () {
    $migration = require database_path('migrations/2026_10_17_170000_add_nature_and_color_to_cost_types_table.php');

    $migration->down();
    expect(Schema::hasColumn('cost_types', 'nature'))->toBeFalse()
        ->and(Schema::hasColumn('cost_types', 'color'))->toBeFalse();

    $migration->up();
    $rows = DB::table('cost_types')->get();

    expect($rows)->not->toBeEmpty()
        ->and($rows->pluck('nature')->unique()->values()->all())->toBe(['direct'])
        ->and($rows->pluck('color')->unique()->values()->all())->toBe(['slate']);
});
