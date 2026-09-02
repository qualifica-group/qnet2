<?php

use App\Models\Campaign;
use App\Models\CampaignProductLine;
use App\Models\Concerns\LogsModelActivity;
use App\Models\PipelineStatus;
use App\Models\Project;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Touches the database (migrations, factories), so bind the full TestCase +
// RefreshDatabase explicitly (the default Pest binding only applies to the
// Feature suite — see tests/Pest.php).
uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// schema
// ---------------------------------------------------------------------------

it('creates the campaigns table with the expected columns', function () {
    expect(Schema::hasTable('campaigns'))->toBeTrue();
    expect(Schema::hasColumns('campaigns', [
        'id', 'code', 'project_id', 'name', 'description',
        'partner_id', 'pipeline_status_id', 'state_id', 'start_date', 'end_date',
        'total_budget', 'target_lead', 'created_at', 'updated_at',
    ]))->toBeTrue();
    expect(Schema::hasColumn('campaigns', 'registry_id'))->toBeFalse();
    expect(Schema::hasColumn('campaigns', 'source_id'))->toBeFalse();
    // Spec 0094, D-1/D-2: the former single-pair columns are DROPPED — the
    // collection now lives in campaign_product_lines.
    expect(Schema::hasColumn('campaigns', 'business_function_id'))->toBeFalse();
    expect(Schema::hasColumn('campaigns', 'product_category_id'))->toBeFalse();
});

it('creates the campaign_product_lines table with the expected columns (AC-001)', function () {
    expect(Schema::hasTable('campaign_product_lines'))->toBeTrue();
    expect(Schema::hasColumns('campaign_product_lines', [
        'id', 'campaign_id', 'business_function_id', 'product_category_id',
        'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('code is unique at the database level', function () {
    Campaign::factory()->create(['code' => 'CMP-0001']);

    expect(fn () => Campaign::factory()->create(['code' => 'CMP-0001']))
        ->toThrow(QueryException::class);
});

it('a project with campaigns cannot be deleted at the schema level (restrictOnDelete)', function () {
    $project = Project::factory()->create();
    Campaign::factory()->forProject($project)->create();

    expect(fn () => DB::table('projects')->where('id', $project->id)->delete())
        ->toThrow(QueryException::class);
});

it('down() reverses the migration, up() recreates it', function () {
    $migration = require database_path('migrations/2026_07_13_110200_create_campaigns_table.php');

    $migration->down();

    expect(Schema::hasTable('campaigns'))->toBeFalse();

    $migration->up();

    expect(Schema::hasTable('campaigns'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// model relations and casts
// ---------------------------------------------------------------------------

it('project() is a BelongsTo relation to Project', function () {
    $relation = (new Campaign)->project();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Project::class);
});

it('pipelineStatus() is a BelongsTo relation to PipelineStatus (the campaign\'s OWN, never derived)', function () {
    $relation = (new Campaign)->pipelineStatus();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(PipelineStatus::class);
});

it('casts total_budget to decimal:2 and target_lead to int', function () {
    $campaign = Campaign::factory()->create(['total_budget' => '99.9', 'target_lead' => '3']);

    expect($campaign->total_budget)->toBe('99.90')
        ->and($campaign->target_lead)->toBeInt()->toBe(3);
});

it('`code` is deliberately absent from #[Fillable]: mass-assignment cannot set it', function () {
    $status = PipelineStatus::factory()->create();
    $campaign = new Campaign([
        'name' => 'Guarded',
        'pipeline_status_id' => $status->id,
        'code' => 'SHOULD-NOT-STICK',
    ]);

    expect($campaign->code)->toBeNull();
});

it('forProject() factory state links the campaign, nulls pipeline_status_id/state_id and owns no product line (spec 0094)', function () {
    $project = Project::factory()->create();
    $campaign = Campaign::factory()->forProject($project)->create();

    expect($campaign->project_id)->toBe($project->id)
        ->and($campaign->pipeline_status_id)->toBeNull()
        ->and($campaign->state_id)->toBeNull()
        ->and($campaign->productLines)->toBeEmpty();
});

it('productLines() is a HasMany CampaignProductLine (spec 0094)', function () {
    $relation = (new Campaign)->productLines();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(CampaignProductLine::class);
});

it('a fresh STANDALONE campaign carries exactly one coherent product line by default', function () {
    $campaign = Campaign::factory()->create();

    expect($campaign->productLines)->toHaveCount(1);
});

it('logs model activity on the campaigns log channel', function () {
    expect(class_uses(Campaign::class))->toHaveKey(LogsModelActivity::class);
});
