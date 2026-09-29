<?php

use App\Enums\ImportDedupMode;
use App\Enums\ImportRowStatus;
use App\Imports\LeadsImportDefinition;
use App\Imports\Staging\StagedRowBuilder;
use App\Imports\Staging\StageOutcome;
use App\Models\Campaign;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\Lead;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Spec 0176 (AC-006): each imported lead gets the run's Fonte, else its
// campaign's; a row left with neither is rejected at staging.

const IDENTITY_MAPPING = ['Email' => 'email', 'Nome' => 'first_name', 'Cognome' => 'last_name'];

/**
 * @param  array<string, mixed>  $globalConfig
 * @param  array<string, string>  $mapping
 * @param  array<string, string>  $rawValues
 */
function stageSourceRow(array $globalConfig, array $mapping, array $rawValues): StageOutcome
{
    return (new StagedRowBuilder(
        app(LeadsImportDefinition::class),
        User::factory()->create(),
        $mapping,
        ImportDedupMode::CreateNew,
        $globalConfig,
    ))->build(1, $rawValues);
}

it('a row whose run and campaign both lack a source is an error', function () {
    $campaign = Campaign::factory()->create();

    $outcome = stageSourceRow(['campaign_id' => $campaign->id], IDENTITY_MAPPING, ['Email' => 'a@example.com', 'Nome' => 'Mario', 'Cognome' => 'Rossi']);

    expect($outcome->status)->toBe(ImportRowStatus::Error)
        ->and(implode(' ', $outcome->messages))->toContain('source is missing');
});

it('a row is valid when only the campaign names a source', function () {
    $campaign = Campaign::factory()->for(Source::factory())->create();

    $outcome = stageSourceRow(['campaign_id' => $campaign->id], IDENTITY_MAPPING, ['Email' => 'a@example.com', 'Nome' => 'Mario', 'Cognome' => 'Rossi']);

    expect($outcome->status)->toBe(ImportRowStatus::Valid);
});

it('a row is valid when only the run names a source', function () {
    $campaign = Campaign::factory()->create();

    $outcome = stageSourceRow(
        ['campaign_id' => $campaign->id, 'source_id' => Source::factory()->create()->id],
        IDENTITY_MAPPING,
        ['Email' => 'a@example.com', 'Nome' => 'Mario', 'Cognome' => 'Rossi'],
    );

    expect($outcome->status)->toBe(ImportRowStatus::Valid);
});

it('with the campaign read from the file, the ROW campaign supplies the source', function () {
    $withSource = Campaign::factory()->for(Source::factory())->create();
    $without = Campaign::factory()->create();
    $mapping = [...IDENTITY_MAPPING, 'Codice campagna' => 'campaign_code'];

    $ok = stageSourceRow([], $mapping, ['Email' => 'a@example.com', 'Nome' => 'Mario', 'Cognome' => 'Rossi', 'Codice campagna' => $withSource->code]);
    $ko = stageSourceRow([], $mapping, ['Email' => 'b@example.com', 'Nome' => 'Anna', 'Cognome' => 'Verdi', 'Codice campagna' => $without->code]);

    expect($ok->status)->toBe(ImportRowStatus::Valid)
        ->and($ko->status)->toBe(ImportRowStatus::Error);
});

it('the commit writes the campaign source, or the run source when set', function () {
    $actor = User::factory()->create();
    $campaignSource = Source::factory()->create();
    $runSource = Source::factory()->create();
    $campaign = Campaign::factory()->for($campaignSource)->create();
    $definition = app(LeadsImportDefinition::class);
    $run = ImportRun::factory()->create(['resource' => 'leads', 'global_config' => []]);

    foreach ([['inherit@example.com', []], ['run@example.com', ['source_id' => $runSource->id]]] as [$email, $extra]) {
        $row = ImportRunRow::factory()->for($run)->create(['mapped_values' => ['email' => $email, 'first_name' => 'Mario', 'last_name' => 'Rossi']]);
        $definition->persistRow($actor, $row, ['campaign_id' => $campaign->id, ...$extra], ImportDedupMode::CreateNew->value);
    }

    expect(Lead::query()->pluck('source_id')->sort()->values()->all())
        ->toBe(collect([$campaignSource->id, $runSource->id])->sort()->values()->all());
});
