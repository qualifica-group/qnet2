<?php

use App\Models\Campaign;
use App\Models\Concerns\LogsModelActivity;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Touches the database (migrations, factories), so bind the full TestCase +
// RefreshDatabase explicitly (the default Pest binding only applies to the
// Feature suite — see tests/Pest.php), mirroring CampaignTest.

uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// schema (AC-001)
// ---------------------------------------------------------------------------

it('creates the leads table with the expected columns', function () {
    expect(Schema::hasTable('leads'))->toBeTrue();
    expect(Schema::hasColumns('leads', [
        'id', 'registry_id', 'campaign_id', 'operational_site_id', 'source_id',
        'operator_id', 'notes', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('registry_id and campaign_id are NOT NULL, the other 4 columns are nullable (AC-001)', function () {
    expect(fn () => Lead::factory()->make(['registry_id' => null])->saveQuietly())
        ->toThrow(QueryException::class);

    expect(fn () => Lead::factory()->make(['campaign_id' => null])->saveQuietly())
        ->toThrow(QueryException::class);

    $lead = Lead::factory()->create([
        'operational_site_id' => null,
        'source_id' => null,
        'operator_id' => null,
        'notes' => null,
    ]);

    expect($lead->exists)->toBeTrue();
});

// ---------------------------------------------------------------------------
// BR-2: every referenced entity is restrictOnDelete at the schema level
// (spec 0041 D-1: the contact guard moved from Referent to Registry)
// ---------------------------------------------------------------------------

it('a registry with leads cannot be deleted at the schema level (restrictOnDelete)', function () {
    $registry = Registry::factory()->create();
    Lead::factory()->create(['registry_id' => $registry->id]);

    expect(fn () => DB::table('registries')->where('id', $registry->id)->delete())
        ->toThrow(QueryException::class);
});

it('a campaign with leads cannot be deleted at the schema level (restrictOnDelete)', function () {
    $campaign = Campaign::factory()->create();
    Lead::factory()->create(['campaign_id' => $campaign->id]);

    expect(fn () => DB::table('campaigns')->where('id', $campaign->id)->delete())
        ->toThrow(QueryException::class);
});

// ---------------------------------------------------------------------------
// migration reversibility (AC-001, AC-003)
// ---------------------------------------------------------------------------

it('down() reverses the migration, up() recreates it', function () {
    $migration = require database_path('migrations/2026_07_13_120000_create_leads_table.php');

    $migration->down();

    expect(Schema::hasTable('leads'))->toBeFalse();

    $migration->up();

    expect(Schema::hasTable('leads'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// model relations (AC-002)
// ---------------------------------------------------------------------------

it('registry() is a BelongsTo relation to Registry', function () {
    $relation = (new Lead)->registry();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Registry::class);
});

it('campaign() is a BelongsTo relation to Campaign', function () {
    $relation = (new Lead)->campaign();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Campaign::class);
});

it('operationalSite() is a BelongsTo relation to OperationalSite', function () {
    $relation = (new Lead)->operationalSite();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(OperationalSite::class);
});

it('source() is a BelongsTo relation to Source', function () {
    $relation = (new Lead)->source();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Source::class);
});

it('operator() is a BelongsTo relation to User via operator_id', function () {
    $relation = (new Lead)->operator();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(User::class);
    expect($relation->getForeignKeyName())->toBe('operator_id');
});

it('a saved lead resolves all 5 relations (AC-002)', function () {
    $registry = Registry::factory()->create();
    $campaign = Campaign::factory()->create();
    $site = OperationalSite::factory()->create();
    $source = Source::factory()->create();
    $operator = User::factory()->create();

    $lead = Lead::factory()->create([
        'registry_id' => $registry->id,
        'campaign_id' => $campaign->id,
        'operational_site_id' => $site->id,
        'source_id' => $source->id,
        'operator_id' => $operator->id,
    ]);

    expect($lead->registry->is($registry))->toBeTrue()
        ->and($lead->campaign->is($campaign))->toBeTrue()
        ->and($lead->operationalSite->is($site))->toBeTrue()
        ->and($lead->source->is($source))->toBeTrue()
        ->and($lead->operator->is($operator))->toBeTrue();
});

it('logs model activity on the leads log channel', function () {
    expect(class_uses(Lead::class))->toHaveKey(LogsModelActivity::class);
});
