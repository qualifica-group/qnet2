<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0332 AC-001. No RefreshDatabase on purpose: the migration drops a
 * foreign-key column, which SQLite does by rebuilding the table, impossible
 * while other tables reference it with the foreign-key pragma on. A minimal
 * copy of the tables involved (no FK pointing at `opportunities`) is built in
 * the in-memory database and the real migration files are run against it.
 */
function quoteSourceMigration(string $file): Migration
{
    return require database_path("migrations/{$file}.php");
}

function legacyOpportunitySourceSchema(): void
{
    Schema::create('sources', function (Blueprint $table) {
        $table->id();
    });
    Schema::create('opportunities', function (Blueprint $table) {
        $table->id();
        $table->foreignId('source_id')->nullable()->constrained('sources')->restrictOnDelete();
    });
    Schema::create('quotes', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('opportunity_id');
    });
}

function seedOpportunityWithOffers(?int $sourceId, int $offers): array
{
    $opportunityId = DB::table('opportunities')->insertGetId(['source_id' => $sourceId]);

    return [$opportunityId, array_map(
        fn (): int => DB::table('quotes')->insertGetId(['opportunity_id' => $opportunityId]),
        $offers === 0 ? [] : range(1, $offers),
    )];
}

it('AC-001: moves the opportunity Fonte onto its first offer only and drops the column', function () {
    legacyOpportunitySourceSchema();
    quoteSourceMigration('2026_11_11_100000_add_source_id_to_quotes_table')->up();
    $source = DB::table('sources')->insertGetId([]);
    $other = DB::table('sources')->insertGetId([]);
    [, [$first, $second, $third]] = seedOpportunityWithOffers($source, 3);
    seedOpportunityWithOffers($other, 0);
    [, [$plain]] = seedOpportunityWithOffers(null, 1);

    quoteSourceMigration('2026_11_11_100100_move_opportunity_source_to_first_quote')->up();

    expect(Schema::hasColumn('opportunities', 'source_id'))->toBeFalse()
        ->and(DB::table('quotes')->orderBy('id')->pluck('source_id', 'id')->all())->toBe([
            $first => $source,
            $second => null,
            $third => null,
            $plain => null,
        ]);
});

it('AC-001: the down restores opportunities.source_id from the first offer', function () {
    legacyOpportunitySourceSchema();
    $addSource = quoteSourceMigration('2026_11_11_100000_add_source_id_to_quotes_table');
    $move = quoteSourceMigration('2026_11_11_100100_move_opportunity_source_to_first_quote');
    $addSource->up();
    $source = DB::table('sources')->insertGetId([]);
    [$opportunityId] = seedOpportunityWithOffers($source, 2);
    [$plainId] = seedOpportunityWithOffers(null, 1);
    $move->up();

    $move->down();

    expect(Schema::hasColumn('opportunities', 'source_id'))->toBeTrue()
        ->and(DB::table('opportunities')->orderBy('id')->pluck('source_id', 'id')->all())->toBe([
            $opportunityId => $source,
            $plainId => null,
        ]);

    $addSource->down();

    expect(Schema::hasColumn('quotes', 'source_id'))->toBeFalse();
});
