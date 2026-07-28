<?php

use App\Models\Concerns\LogsModelActivity;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Touches the database (migrations, factories), so bind the full TestCase +
// RefreshDatabase explicitly, mirroring LeadTest/CampaignTest.

uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// schema (AC-001)
// ---------------------------------------------------------------------------

it('creates the opportunities table with the expected columns', function () {
    expect(Schema::hasTable('opportunities'))->toBeTrue();
    expect(Schema::hasColumns('opportunities', [
        'id', 'name', 'registry_id',
        'referent_id', 'commercial_id', 'reporter_id', 'supervisor_id',
        'source_id', 'lead_id', 'start_date', 'estimated_value',
        'expected_close_date', 'success_probability', 'created_at', 'updated_at',
    ]))->toBeTrue();

    // Amendment rev.3: the former single scalar columns are DROPPED.
    expect(Schema::hasColumn('opportunities', 'business_function_id'))->toBeFalse();
    expect(Schema::hasColumn('opportunities', 'product_category_id'))->toBeFalse();

    // User directive 2026-07-17: company_id/company_site_id/operational_site_id
    // are DROPPED entirely. Spec 0056 (2026-07-23) then REINSTATES
    // `operational_site_id` alone, as an optional FK; the other two stay dropped.
    expect(Schema::hasColumn('opportunities', 'company_id'))->toBeFalse();
    expect(Schema::hasColumn('opportunities', 'company_site_id'))->toBeFalse();
    expect(Schema::hasColumn('opportunities', 'operational_site_id'))->toBeTrue();

    expect(Schema::hasTable('opportunity_user'))->toBeTrue();
    expect(Schema::hasColumns('opportunity_user', ['id', 'opportunity_id', 'user_id', 'position']))->toBeTrue();

    expect(Schema::hasTable('opportunity_product_lines'))->toBeTrue();
    expect(Schema::hasColumns('opportunity_product_lines', [
        'id', 'opportunity_id', 'business_function_id', 'product_category_id', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('the 2 mandatory fields are NOT NULL, every other relation is nullable (AC-001/AC-081)', function () {
    expect(fn () => Opportunity::factory()->make(['name' => null])->saveQuietly())
        ->toThrow(QueryException::class);

    expect(fn () => Opportunity::factory()->make(['registry_id' => null])->saveQuietly())
        ->toThrow(QueryException::class);

    $opportunity = Opportunity::factory()->create([
        'referent_id' => null, 'commercial_id' => null,
        'reporter_id' => null, 'supervisor_id' => null, 'source_id' => null,
        'lead_id' => null,
    ]);

    expect($opportunity->exists)->toBeTrue();
    expect($opportunity->productLines)->toBeEmpty();
});

it('lead_id is UNIQUE: a second opportunity for the same lead violates the constraint', function () {
    $lead = Lead::factory()->create();
    Opportunity::factory()->create(['lead_id' => $lead->id]);

    expect(fn () => Opportunity::factory()->make(['lead_id' => $lead->id])->saveQuietly())
        ->toThrow(QueryException::class);
});

it('opportunity_user has unique(opportunity_id,user_id) and unique(opportunity_id,position)', function () {
    $opportunity = Opportunity::factory()->create();
    $userOne = User::factory()->create();
    $userTwo = User::factory()->create();

    DB::table('opportunity_user')->insert(['opportunity_id' => $opportunity->id, 'user_id' => $userOne->id, 'position' => 1]);

    expect(fn () => DB::table('opportunity_user')->insert(['opportunity_id' => $opportunity->id, 'user_id' => $userOne->id, 'position' => 2]))
        ->toThrow(QueryException::class);

    expect(fn () => DB::table('opportunity_user')->insert(['opportunity_id' => $opportunity->id, 'user_id' => $userTwo->id, 'position' => 1]))
        ->toThrow(QueryException::class);
});

// ---------------------------------------------------------------------------
// BR-3: every referenced entity is restrictOnDelete at the schema level
// ---------------------------------------------------------------------------

it('a registry with opportunities cannot be deleted at the schema level (restrictOnDelete)', function () {
    $registry = Registry::factory()->create();
    Opportunity::factory()->create(['registry_id' => $registry->id]);

    expect(fn () => DB::table('registries')->where('id', $registry->id)->delete())
        ->toThrow(QueryException::class);
});

it('a lead with a linked opportunity cannot be deleted at the schema level (restrictOnDelete)', function () {
    $lead = Lead::factory()->create();
    Opportunity::factory()->create(['lead_id' => $lead->id]);

    expect(fn () => DB::table('leads')->where('id', $lead->id)->delete())
        ->toThrow(QueryException::class);
});

it('opportunity_product_lines: business_function_id/product_category_id are restrictOnDelete, opportunity_id cascades (AC-097)', function () {
    $line = OpportunityProductLine::factory()->create();

    expect(fn () => DB::table('business_functions')->where('id', $line->business_function_id)->delete())
        ->toThrow(QueryException::class);

    expect(fn () => DB::table('product_categories')->where('id', $line->product_category_id)->delete())
        ->toThrow(QueryException::class);

    $opportunityId = $line->opportunity_id;
    Opportunity::destroy($opportunityId);

    expect(DB::table('opportunity_product_lines')->where('id', $line->id)->exists())->toBeFalse();
});

it('opportunity_product_lines: unique(opportunity_id, business_function_id, product_category_id)', function () {
    $line = OpportunityProductLine::factory()->create();

    expect(fn () => OpportunityProductLine::factory()->create([
        'opportunity_id' => $line->opportunity_id,
        'business_function_id' => $line->business_function_id,
        'product_category_id' => $line->product_category_id,
    ]))->toThrow(QueryException::class);
});

// ---------------------------------------------------------------------------
// migration reversibility (AC-003)
// ---------------------------------------------------------------------------

it('down() reverses the 3 opportunities migrations in LIFO order, up() recreates them all', function () {
    $pivotMigration = require database_path('migrations/2026_07_16_140100_create_opportunity_user_table.php');
    $productLinesMigration = require database_path('migrations/2026_07_17_150000_create_opportunity_product_lines_table.php');
    $tableMigration = require database_path('migrations/2026_07_16_140000_create_opportunities_table.php');

    // LIFO: the tables holding a FK to `opportunities` go first.
    $pivotMigration->down();
    $productLinesMigration->down();
    $tableMigration->down();

    expect(Schema::hasTable('opportunity_user'))->toBeFalse();
    expect(Schema::hasTable('opportunity_product_lines'))->toBeFalse();
    expect(Schema::hasTable('opportunities'))->toBeFalse();

    $tableMigration->up();
    $productLinesMigration->up();
    $pivotMigration->up();

    expect(Schema::hasTable('opportunities'))->toBeTrue();
    expect(Schema::hasTable('opportunity_product_lines'))->toBeTrue();
    expect(Schema::hasTable('opportunity_user'))->toBeTrue();

    // The business function / product category pair lives on the product lines,
    // never as columns here; company and company site are not relations at all.
    expect(Schema::hasColumn('opportunities', 'business_function_id'))->toBeFalse();
    expect(Schema::hasColumn('opportunities', 'product_category_id'))->toBeFalse();
    expect(Schema::hasColumn('opportunities', 'company_id'))->toBeFalse();
    expect(Schema::hasColumn('opportunities', 'company_site_id'))->toBeFalse();
    expect(Schema::hasColumn('opportunities', 'operational_site_id'))->toBeTrue();
});

it('opportunity_product_lines is reversible standalone: down() then up() (AC-097)', function () {
    $migration = require database_path('migrations/2026_07_17_150000_create_opportunity_product_lines_table.php');

    $migration->down();

    expect(Schema::hasTable('opportunity_product_lines'))->toBeFalse();

    $migration->up();

    expect(Schema::hasTable('opportunity_product_lines'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// model relations (AC-002)
// ---------------------------------------------------------------------------

it('every to-one relation is a BelongsTo to the expected model', function () {
    $opportunity = new Opportunity;

    expect($opportunity->registry())->toBeInstanceOf(BelongsTo::class)
        ->and($opportunity->registry()->getRelated())->toBeInstanceOf(Registry::class)
        ->and($opportunity->referent())->toBeInstanceOf(BelongsTo::class)
        ->and($opportunity->referent()->getRelated())->toBeInstanceOf(Referent::class)
        ->and($opportunity->source())->toBeInstanceOf(BelongsTo::class)
        ->and($opportunity->source()->getRelated())->toBeInstanceOf(Source::class)
        ->and($opportunity->lead())->toBeInstanceOf(BelongsTo::class)
        ->and($opportunity->lead()->getRelated())->toBeInstanceOf(Lead::class);
});

it('productLines() is a HasMany OpportunityProductLine (amendment rev.3, AC-098)', function () {
    $opportunity = new Opportunity;

    expect($opportunity->productLines())->toBeInstanceOf(HasMany::class)
        ->and($opportunity->productLines()->getRelated())->toBeInstanceOf(OpportunityProductLine::class);
});

it('commercial()/reporter() are BelongsTo Referent via their own FK', function () {
    $opportunity = new Opportunity;

    expect($opportunity->commercial()->getForeignKeyName())->toBe('commercial_id')
        ->and($opportunity->commercial()->getRelated())->toBeInstanceOf(Referent::class)
        ->and($opportunity->reporter()->getForeignKeyName())->toBe('reporter_id')
        ->and($opportunity->reporter()->getRelated())->toBeInstanceOf(Referent::class);
});

it('supervisor() is a BelongsTo User via supervisor_id', function () {
    $relation = (new Opportunity)->supervisor();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(User::class);
    expect($relation->getForeignKeyName())->toBe('supervisor_id');
});

it('managers() is a BelongsToMany User via opportunity_user, ordered by pivot position', function () {
    $opportunity = Opportunity::factory()->create();
    $first = User::factory()->create();
    $second = User::factory()->create();

    $opportunity->managers()->sync([
        $second->id => ['position' => 1],
        $first->id => ['position' => 2],
    ]);

    $relation = $opportunity->managers();
    expect($relation)->toBeInstanceOf(BelongsToMany::class);

    $ordered = $opportunity->managers()->get();
    expect($ordered->pluck('id')->all())->toBe([$second->id, $first->id]);
});

it('logs model activity on the opportunities log channel', function () {
    expect(class_uses(Opportunity::class))->toHaveKey(LogsModelActivity::class);
});

it('casts estimated_value/success_probability/dates correctly', function () {
    $opportunity = Opportunity::factory()->create([
        'estimated_value' => 1234.5,
        'success_probability' => 42,
        'start_date' => '2026-01-15',
        'expected_close_date' => '2026-03-01',
    ]);

    $opportunity->refresh();

    expect($opportunity->estimated_value)->toBe('1234.50')
        ->and($opportunity->success_probability)->toBe(42)
        ->and($opportunity->start_date->format('Y-m-d'))->toBe('2026-01-15')
        ->and($opportunity->expected_close_date->format('Y-m-d'))->toBe('2026-03-01');
});
