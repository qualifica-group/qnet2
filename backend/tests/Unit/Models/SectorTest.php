<?php

use App\Models\Concerns\LogsModelActivity;
use App\Models\Sector;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-001 — schema
// ---------------------------------------------------------------------------

it('creates the sectors table with the expected columns', function () {
    expect(Schema::hasTable('sectors'))->toBeTrue();
    expect(Schema::hasColumns('sectors', ['id', 'name', 'parent_id', 'created_at', 'updated_at']))->toBeTrue();
});

it('a sector with children cannot be deleted at the database level (restrictOnDelete)', function () {
    $parent = Sector::factory()->create();
    Sector::factory()->childOf($parent)->create();

    expect(fn () => DB::table('sectors')->where('id', $parent->id)->delete())
        ->toThrow(QueryException::class);
});

it('down() reverses the sectors migration, up() recreates it', function () {
    $migration = require database_path('migrations/2026_07_07_110900_create_sectors_table.php');

    $migration->down();

    expect(Schema::hasTable('sectors'))->toBeFalse();

    $migration->up();

    expect(Schema::hasTable('sectors'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-002 — model relations, fillable, activity log, morph map
// ---------------------------------------------------------------------------

it('parent()/children() are self-referencing relations', function () {
    $sector = new Sector;

    expect($sector->parent())->toBeInstanceOf(BelongsTo::class);
    expect($sector->children())->toBeInstanceOf(HasMany::class);
});

it('has code, name, parent_id and is_active as the only fillable attributes', function () {
    expect((new Sector)->getFillable())->toBe(['code', 'name', 'parent_id', 'is_active']);
});

it('logs model activity', function () {
    expect(class_uses(Sector::class))->toHaveKey(LogsModelActivity::class);
});

it('registers the "sector" morph alias', function () {
    expect(array_search(Sector::class, Relation::morphMap(), true))->toBe('sector');
});
