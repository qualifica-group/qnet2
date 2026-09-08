<?php

use App\Enums\ImportDedupMode;
use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Imports\LeadsImportDefinition;
use App\Imports\Staging\StagedRowBuilder;
use App\Imports\Staging\StageOutcome;
use App\Imports\Support\ColumnMapper;
use App\Models\Campaign;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0108 — the campaign read from a file column: configure-time rules,
 * staging resolution, invalidation and commit. The review-side pin lives in
 * LeadImportCampaignReviewTest.
 *
 * @param  array<int, string>  $abilities
 */
function campaignFileImportActor(array $abilities = ['import']): User
{
    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        Permission::findOrCreate("leads.{$ability}");
    }

    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        $user->givePermissionTo("leads.{$ability}");
    }

    grantImportRunsPermissions($user, ['viewAny', 'view', 'create', 'update']);

    return $user;
}

/** A `configuring` run whose detected columns include the campaign code column. */
function campaignFileRun(User $actor): ImportRun
{
    return ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'leads',
        'status' => ImportStatus::Configuring,
        'detected_columns' => [
            ['name' => 'Email', 'index' => 0, 'duplicate' => false],
            ['name' => 'Nome', 'index' => 1, 'duplicate' => false],
            ['name' => 'Cognome', 'index' => 2, 'duplicate' => false],
            ['name' => 'Codice campagna', 'index' => 3, 'duplicate' => false],
        ],
    ]);
}

/**
 * @return array<string, string>
 */
function campaignFileMapping(bool $withCampaignColumn = true): array
{
    return [
        'Email' => 'email',
        'Nome' => 'first_name',
        'Cognome' => 'last_name',
        'Codice campagna' => $withCampaignColumn ? 'campaign_code' : '__ignore__',
    ];
}

/**
 * @param  array<string, string>  $rawValues
 * @param  array<string, mixed>  $globalConfig
 */
function stageCampaignFileRow(array $rawValues, array $globalConfig = [], bool $withCampaignColumn = true): StageOutcome
{
    return (new StagedRowBuilder(
        app(LeadsImportDefinition::class),
        User::factory()->create(),
        campaignFileMapping($withCampaignColumn),
        ImportDedupMode::CreateNew,
        $globalConfig,
    ))->build(1, $rawValues);
}

// ---------------------------------------------------------------------------
// AC-001 / AC-021 — catalogue, template and auto-mapping
// ---------------------------------------------------------------------------

it('AC-001: the wizard catalogue exposes campaign_code and campaign_id\'s required_unless_mapped', function () {
    $actor = campaignFileImportActor();
    $run = campaignFileRun($actor);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/imports/leads/{$run->id}")->assertOk();

    $fieldIds = collect($response->json('data.import_run.fields'))->pluck('id');
    $globalFields = collect($response->json('data.import_run.global_fields'))->keyBy('id');

    expect($fieldIds)->toContain('campaign_code')
        ->and($globalFields['campaign_id']['required_unless_mapped'])->toBe('campaign_code')
        ->and($globalFields['source_id']['required_unless_mapped'])->toBeNull();
});

it('AC-021: the leads template carries the campaign_code column and auto-mapping recognizes its header', function () {
    $actor = campaignFileImportActor();
    Sanctum::actingAs($actor);

    $header = str_getcsv(explode("\n", $this->get('/api/imports/leads/template')->streamedContent())[0]);
    expect($header)->toContain('campaign_code');

    $suggestion = app(ColumnMapper::class)->suggest(
        [
            ['name' => 'Codice campagna', 'index' => 0, 'duplicate' => false],
            ['name' => 'Email', 'index' => 1, 'duplicate' => false],
        ],
        app(LeadsImportDefinition::class)->fields(),
    );

    expect($suggestion->mapping['Codice campagna'])->toBe('campaign_code');
});

// ---------------------------------------------------------------------------
// AC-002..AC-005 — configure-time rules
// ---------------------------------------------------------------------------

it('AC-002: configure without a mapped campaign_code and without a global campaign_id is 422', function () {
    $actor = campaignFileImportActor();
    $run = campaignFileRun($actor);
    Sanctum::actingAs($actor);

    $this->putJson("/api/imports/leads/{$run->id}/configure", [
        'column_mapping' => campaignFileMapping(withCampaignColumn: false),
        'global_config' => [],
        'dedup_strategy' => 'create_new',
    ])->assertStatus(422)->assertJsonValidationErrors('global_config.campaign_id');
});

it('AC-003: configure with a mapped campaign_code and no global campaign_id stages the run', function () {
    Queue::fake();
    $actor = campaignFileImportActor();
    $run = campaignFileRun($actor);
    Sanctum::actingAs($actor);

    $this->putJson("/api/imports/leads/{$run->id}/configure", [
        'column_mapping' => campaignFileMapping(),
        'global_config' => [],
        'dedup_strategy' => 'create_new',
    ])->assertOk()->assertJsonPath('data.import_run.status', 'staging');
});

it('AC-004: configure with BOTH a mapped campaign_code and a global campaign_id is 422', function () {
    $actor = campaignFileImportActor();
    $run = campaignFileRun($actor);
    $campaign = Campaign::factory()->create();
    Sanctum::actingAs($actor);

    $this->putJson("/api/imports/leads/{$run->id}/configure", [
        'column_mapping' => campaignFileMapping(),
        'global_config' => ['campaign_id' => $campaign->id],
        'dedup_strategy' => 'create_new',
    ])->assertStatus(422)->assertJsonValidationErrors('global_config.campaign_id');
});

it('AC-005: configure with a mapped campaign_code and global product_ids is 422', function () {
    $actor = campaignFileImportActor();
    $run = campaignFileRun($actor);
    $product = Product::factory()->create();
    Sanctum::actingAs($actor);

    $this->putJson("/api/imports/leads/{$run->id}/configure", [
        'column_mapping' => campaignFileMapping(),
        'global_config' => ['product_ids' => [$product->id]],
        'dedup_strategy' => 'create_new',
    ])->assertStatus(422)->assertJsonValidationErrors('global_config.product_ids');
});

// ---------------------------------------------------------------------------
// AC-006..AC-009 — staging resolution and invalidation
// ---------------------------------------------------------------------------

it('AC-006: each staged row resolves its OWN campaign from the file code', function () {
    $first = Campaign::factory()->create();
    $second = Campaign::factory()->create();

    $one = stageCampaignFileRow(['Email' => 'a@example.com', 'Nome' => 'Mario', 'Cognome' => 'Rossi', 'Codice campagna' => $first->code]);
    $two = stageCampaignFileRow(['Email' => 'b@example.com', 'Nome' => 'Anna', 'Cognome' => 'Verdi', 'Codice campagna' => $second->code]);

    expect($one->status)->toBe(ImportRowStatus::Valid)
        ->and($one->mappedValues['campaign_id'])->toBe($first->id)
        ->and($one->resolved['campaign_id'])->toBe($first->id)
        ->and($two->status)->toBe(ImportRowStatus::Valid)
        ->and($two->mappedValues['campaign_id'])->toBe($second->id)
        ->and($two->mappedValues['campaign_id'])->not->toBe($one->mappedValues['campaign_id']);
});

it('AC-007: the code match is case-insensitive and space-tolerant, and stores the canonical code', function () {
    $campaign = Campaign::factory()->create();

    $outcome = stageCampaignFileRow([
        'Email' => 'a@example.com', 'Nome' => 'Mario', 'Cognome' => 'Rossi',
        'Codice campagna' => '  '.strtolower($campaign->code).' ',
    ]);

    expect($outcome->status)->toBe(ImportRowStatus::Valid)
        ->and($outcome->mappedValues['campaign_id'])->toBe($campaign->id)
        ->and($outcome->mappedValues['campaign_code'])->toBe($campaign->code);
});

it('AC-008: an unknown campaign code invalidates the row and names the code', function () {
    Campaign::factory()->create();

    $outcome = stageCampaignFileRow(['Email' => 'a@example.com', 'Nome' => 'Mario', 'Cognome' => 'Rossi', 'Codice campagna' => 'CMP-9999']);

    expect($outcome->status)->toBe(ImportRowStatus::Error)
        ->and($outcome->mappedValues['campaign_id'] ?? null)->toBeNull()
        ->and(implode(' ', $outcome->messages))->toContain('CMP-9999');
});

it('AC-009: an empty campaign code cell invalidates the row, never a placeholder or a warning', function () {
    $outcome = stageCampaignFileRow(['Email' => 'a@example.com', 'Nome' => 'Mario', 'Cognome' => 'Rossi', 'Codice campagna' => '   ']);

    expect($outcome->status)->toBe(ImportRowStatus::Error)
        ->and(implode(' ', $outcome->messages))->toContain('campaign code is empty');
});

// ---------------------------------------------------------------------------
// AC-010..AC-012 — commit, duplicate scope, and the global mode's regression
// ---------------------------------------------------------------------------

it('AC-010: the commit writes every Lead on ITS OWN row campaign', function () {
    $actor = User::factory()->create();
    $first = Campaign::factory()->create();
    $second = Campaign::factory()->create();
    $definition = app(LeadsImportDefinition::class);
    $run = ImportRun::factory()->create(['resource' => 'leads', 'global_config' => []]);

    foreach ([['first@example.com', $first], ['second@example.com', $second]] as [$email, $campaign]) {
        $row = ImportRunRow::factory()->for($run)->create([
            'mapped_values' => ['email' => $email, 'first_name' => 'Mario', 'last_name' => 'Rossi', 'campaign_code' => $campaign->code, 'campaign_id' => $campaign->id],
        ]);

        $definition->persistRow($actor, $row, [], ImportDedupMode::CreateNew->value);
    }

    expect(Lead::query()->where('campaign_id', $first->id)->count())->toBe(1)
        ->and(Lead::query()->where('campaign_id', $second->id)->count())->toBe(1);
});

it('AC-011: the duplicate match is scoped to the ROW campaign, not the run', function () {
    $actor = User::factory()->create();
    $first = Campaign::factory()->create();
    $second = Campaign::factory()->create();
    $definition = app(LeadsImportDefinition::class);
    $run = ImportRun::factory()->create(['resource' => 'leads', 'global_config' => []]);

    // An already imported lead on the FIRST campaign.
    $seed = ImportRunRow::factory()->for($run)->create([
        'mapped_values' => ['email' => 'dup@example.com', 'first_name' => 'Mario', 'last_name' => 'Rossi', 'campaign_code' => $first->code, 'campaign_id' => $first->id],
    ]);
    $definition->persistRow($actor, $seed, [], ImportDedupMode::CreateNew->value);

    $registry = Registry::query()->latest('id')->firstOrFail();
    $mapped = ['email' => 'dup@example.com', 'first_name' => 'Mario', 'last_name' => 'Rossi'];

    $sameCampaign = $definition->resolveDuplicateMatch([...$mapped, 'campaign_id' => $first->id], []);
    $otherCampaign = $definition->resolveDuplicateMatch([...$mapped, 'campaign_id' => $second->id], []);

    expect($sameCampaign['id'])->toBe($registry->id)
        ->and($sameCampaign['meta']['lead_id'])->not->toBeNull()
        ->and($otherCampaign['id'])->toBe($registry->id)
        ->and($otherCampaign['meta']['lead_id'])->toBeNull();
});

it('AC-012: with no campaign column mapped the run keeps its pre-0108 single-campaign behaviour', function () {
    $campaign = Campaign::factory()->create();

    $outcome = stageCampaignFileRow(
        ['Email' => 'a@example.com', 'Nome' => 'Mario', 'Cognome' => 'Rossi', 'Codice campagna' => 'ignored'],
        ['campaign_id' => $campaign->id],
        withCampaignColumn: false,
    );

    expect($outcome->status)->toBe(ImportRowStatus::Valid)
        ->and($outcome->mappedValues)->not->toHaveKey('campaign_id')
        ->and($outcome->mappedValues)->not->toHaveKey('campaign_code');

    $row = ImportRunRow::factory()->create([
        'import_run_id' => ImportRun::factory()->create(['resource' => 'leads'])->id,
        'mapped_values' => $outcome->mappedValues,
    ]);
    app(LeadsImportDefinition::class)->persistRow(User::factory()->create(), $row, ['campaign_id' => $campaign->id], ImportDedupMode::CreateNew->value);

    expect(Lead::query()->where('campaign_id', $campaign->id)->count())->toBe(1);
});
