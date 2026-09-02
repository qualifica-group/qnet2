<?php

use App\Enums\ImportDedupMode;
use App\Enums\ImportRowResolution;
use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\PersonalData;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0045 — auto-convert-to-Opportunity during lead import (POST
 * .../confirm with `convert_to_opportunity: true`, gated by
 * ImportOpportunityConvertibility) and its `GET .../summary` preview. The
 * per-row Operator/Operational Site override coverage (PATCH .../rows/{row})
 * lives in ImportRowOverrideTest (split out, engineering.md §6).
 */

/**
 * @param  array<int, string>  $abilities  `leads.*` abilities
 * @param  array<int, string>  $opportunityAbilities  `opportunities.*` abilities — required in
 *                                                    addition to `leads.import` to confirm with `convert_to_opportunity: true`
 */
function conversionActor(array $abilities, array $opportunityAbilities = []): User
{
    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        Permission::findOrCreate("leads.{$ability}");
        Permission::findOrCreate("opportunities.{$ability}");
    }

    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        $user->givePermissionTo("leads.{$ability}");
    }

    foreach ($opportunityAbilities as $ability) {
        $user->givePermissionTo("opportunities.{$ability}");
    }

    if (in_array('import', $abilities, true)) {
        grantImportRunsPermissions($user, ['update']);
    }

    return $user;
}

/**
 * A campaign whose business function/product category derive a non-empty
 * Opportunity product line, plus an operational site and a global operator
 * — the 3 ingredients ImportOpportunityConvertibility requires for a run to
 * be ready. Each blocker test starts from this and removes ONE ingredient.
 *
 * @return array{campaign: Campaign, operationalSite: OperationalSite, operator: User}
 */
function conversionReadyFixture(): array
{
    // Spec 0094 D-1/D-2: la classificazione della campagna e' una collezione,
    // non due colonne. La factory semina gia' una riga coerente per una
    // campagna standalone, che e' l'ingrediente richiesto qui.
    $campaign = Campaign::factory()->create();

    return [
        'campaign' => $campaign,
        'operationalSite' => OperationalSite::factory()->create(),
        'operator' => User::factory()->create(),
    ];
}

// ---------------------------------------------------------------------------
// Confirm gate — POST /api/imports/leads/{importRun}/confirm, convert_blockers
// ---------------------------------------------------------------------------

it('422 with rows_without_site listing every creatable row with no effective operational site', function () {
    // Requirement changed: the operational site is no longer a run-level
    // "set/missing" boolean — it is a PER-ROW override mirroring the
    // operator (spec 0045 extended), so an absent global AND per-row site
    // surfaces as `rows_without_site`, not `operational_site_missing`.
    $actor = conversionActor(['import'], ['create']);
    $fixture = conversionReadyFixture();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'leads',
        'status' => ImportStatus::Reviewing,
        'dedup_strategy' => ImportDedupMode::CreateNew->value,
        'global_config' => ['campaign_id' => $fixture['campaign']->id, 'operator_id' => $fixture['operator']->id],
    ]);
    ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 1, 'status' => ImportRowStatus::Valid]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/imports/leads/{$run->id}/confirm", ['convert_to_opportunity' => true])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('convert_blockers.campaign_missing_product_line', false)
        ->assertJsonPath('convert_blockers.rows_without_operator', [])
        ->assertJsonPath('convert_blockers.rows_without_site', [1]);

    expect($run->fresh()->status)->toBe(ImportStatus::Reviewing)
        ->and(Lead::query()->count())->toBe(0);
});

it('422 with campaign_missing_product_line when the campaign derives no product line', function () {
    $actor = conversionActor(['import'], ['create']);
    // Una campagna che non deriva alcuna riga: la factory ne semina una di
    // default, quindi va rimossa esplicitamente (spec 0094 D-2).
    $campaign = Campaign::factory()->create();
    $campaign->productLines()->delete();
    $operationalSite = OperationalSite::factory()->create();
    $operator = User::factory()->create();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'leads',
        'status' => ImportStatus::Reviewing,
        'dedup_strategy' => ImportDedupMode::CreateNew->value,
        'global_config' => [
            'campaign_id' => $campaign->id,
            'operational_site_id' => $operationalSite->id,
            'operator_id' => $operator->id,
        ],
    ]);
    ImportRunRow::factory()->create(['import_run_id' => $run->id, 'status' => ImportRowStatus::Valid]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/imports/leads/{$run->id}/confirm", ['convert_to_opportunity' => true])
        ->assertStatus(422)
        ->assertJsonPath('convert_blockers.campaign_missing_product_line', true)
        ->assertJsonPath('convert_blockers.rows_without_operator', [])
        ->assertJsonPath('convert_blockers.rows_without_site', []);
});

it('422 with rows_without_operator listing every creatable row with no effective operator', function () {
    $actor = conversionActor(['import'], ['create']);
    $fixture = conversionReadyFixture();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'leads',
        'status' => ImportStatus::Reviewing,
        'dedup_strategy' => ImportDedupMode::CreateNew->value,
        // No global operator_id — every row must supply its own.
        'global_config' => ['campaign_id' => $fixture['campaign']->id, 'operational_site_id' => $fixture['operationalSite']->id],
    ]);
    ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 1, 'status' => ImportRowStatus::Valid]);
    ImportRunRow::factory()->create([
        'import_run_id' => $run->id,
        'row_number' => 2,
        'status' => ImportRowStatus::Valid,
        'operator_id' => User::factory()->create()->id,
    ]);
    ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 3, 'status' => ImportRowStatus::Valid]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/imports/leads/{$run->id}/confirm", ['convert_to_opportunity' => true])
        ->assertStatus(422)
        ->assertJsonPath('convert_blockers.campaign_missing_product_line', false)
        ->assertJsonPath('convert_blockers.rows_without_operator', [1, 3])
        ->assertJsonPath('convert_blockers.rows_without_site', []);
});

it('422 with rows_without_site listing every creatable row with no effective operational site (per-row override present on some)', function () {
    $actor = conversionActor(['import'], ['create']);
    $fixture = conversionReadyFixture();
    $rowSite = OperationalSite::factory()->create();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'leads',
        'status' => ImportStatus::Reviewing,
        'dedup_strategy' => ImportDedupMode::CreateNew->value,
        // No global operational_site_id — every row must supply its own.
        'global_config' => ['campaign_id' => $fixture['campaign']->id, 'operator_id' => $fixture['operator']->id],
    ]);
    ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 1, 'status' => ImportRowStatus::Valid]);
    ImportRunRow::factory()->create([
        'import_run_id' => $run->id,
        'row_number' => 2,
        'status' => ImportRowStatus::Valid,
        'operational_site_id' => $rowSite->id,
    ]);
    ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 3, 'status' => ImportRowStatus::Valid]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/imports/leads/{$run->id}/confirm", ['convert_to_opportunity' => true])
        ->assertStatus(422)
        ->assertJsonPath('convert_blockers.campaign_missing_product_line', false)
        ->assertJsonPath('convert_blockers.rows_without_operator', [])
        ->assertJsonPath('convert_blockers.rows_without_site', [1, 3]);
});

it('403 with convert_to_opportunity:true when the actor lacks opportunities.create, even with leads.import', function () {
    // Regression (verifier finding): the confirm gate must enforce
    // `opportunities.create` server-side — the frontend `<Can>` gate alone
    // is not authorization (security.md §1).
    $actor = conversionActor(['import']);
    $fixture = conversionReadyFixture();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'leads',
        'status' => ImportStatus::Reviewing,
        'dedup_strategy' => ImportDedupMode::CreateNew->value,
        'global_config' => [
            'campaign_id' => $fixture['campaign']->id,
            'operational_site_id' => $fixture['operationalSite']->id,
            'operator_id' => $fixture['operator']->id,
        ],
    ]);
    ImportRunRow::factory()->create([
        'import_run_id' => $run->id,
        'row_number' => 1,
        'status' => ImportRowStatus::Valid,
        'mapped_values' => ['first_name' => 'Should', 'last_name' => 'NotConvert', 'email' => 'should-not-convert@example.com'],
    ]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/imports/leads/{$run->id}/confirm", ['convert_to_opportunity' => true])
        ->assertForbidden();

    // The authorization check runs BEFORE confirmStaged(): the run never
    // transitions to processing, no job is dispatched, nothing is persisted.
    expect($run->fresh()->status)->toBe(ImportStatus::Reviewing)
        ->and($run->fresh()->convert_to_opportunity)->toBeFalse()
        ->and(Lead::query()->count())->toBe(0)
        ->and(Opportunity::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Happy path — convert_to_opportunity true/false
// ---------------------------------------------------------------------------

it('converts every CREATE-branch row when ready; the UPDATE-branch row never converts', function () {
    $actor = conversionActor(['import'], ['create']);
    $fixture = conversionReadyFixture();
    $rowOperator = User::factory()->create();

    // A Registry+Lead already in the SAME campaign — the row resolving to it
    // (Manual, resolution=update) takes the UPDATE branch.
    $existingRegistry = Registry::factory()->create();
    $existingCard = PersonalData::factory()->individual()->for($existingRegistry, 'personable')->create();
    Contact::factory()->email()->for($existingCard, 'contactable')->create(['value' => 'existing@example.com']);
    $existingLead = Lead::factory()->create(['registry_id' => $existingRegistry->id, 'campaign_id' => $fixture['campaign']->id]);

    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'leads',
        'status' => ImportStatus::Reviewing,
        'dedup_strategy' => ImportDedupMode::Manual->value,
        'global_config' => [
            'campaign_id' => $fixture['campaign']->id,
            'operational_site_id' => $fixture['operationalSite']->id,
            'operator_id' => $fixture['operator']->id,
        ],
    ]);

    ImportRunRow::factory()->create([
        'import_run_id' => $run->id,
        'row_number' => 1,
        'status' => ImportRowStatus::Valid,
        'mapped_values' => ['first_name' => 'Global', 'last_name' => 'Operator', 'email' => 'global-op@example.com'],
    ]);
    ImportRunRow::factory()->create([
        'import_run_id' => $run->id,
        'row_number' => 2,
        'status' => ImportRowStatus::Valid,
        'mapped_values' => ['first_name' => 'Own', 'last_name' => 'Operator', 'email' => 'own-op@example.com'],
        'operator_id' => $rowOperator->id,
    ]);
    ImportRunRow::factory()->create([
        'import_run_id' => $run->id,
        'row_number' => 3,
        'status' => ImportRowStatus::Duplicate,
        'mapped_values' => ['first_name' => 'Existing', 'last_name' => 'Person', 'email' => 'existing@example.com'],
        'duplicate_of_id' => $existingRegistry->id,
        'resolution' => ImportRowResolution::Update,
    ]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/imports/leads/{$run->id}/confirm", ['convert_to_opportunity' => true])
        ->assertOk()
        ->assertJsonPath('data.import_run.status', 'completed');

    expect($run->fresh()->convert_to_opportunity)->toBeTrue();

    $globalOperatorLead = Lead::query()->where('operator_id', $fixture['operator']->id)->where('id', '!=', $existingLead->id)->firstOrFail();
    $rowOperatorLead = Lead::query()->where('operator_id', $rowOperator->id)->firstOrFail();

    expect(Opportunity::where('lead_id', $globalOperatorLead->id)->count())->toBe(1)
        ->and(Opportunity::where('lead_id', $rowOperatorLead->id)->count())->toBe(1)
        ->and(Opportunity::where('lead_id', $existingLead->id)->exists())->toBeFalse();
});

it('convert_to_opportunity absent never requires opportunities.create — plain import stays accessible with only leads.import', function () {
    // The actor deliberately has NO `opportunities.create` here: a plain
    // (non-converting) confirm must never require it.
    $actor = conversionActor(['import']);
    $fixture = conversionReadyFixture();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'leads',
        'status' => ImportStatus::Reviewing,
        'dedup_strategy' => ImportDedupMode::CreateNew->value,
        'global_config' => [
            'campaign_id' => $fixture['campaign']->id,
            'operational_site_id' => $fixture['operationalSite']->id,
            'operator_id' => $fixture['operator']->id,
        ],
    ]);
    ImportRunRow::factory()->create([
        'import_run_id' => $run->id,
        'row_number' => 1,
        'status' => ImportRowStatus::Valid,
        'mapped_values' => ['first_name' => 'No', 'last_name' => 'Conversion', 'email' => 'no-conversion@example.com'],
    ]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/imports/leads/{$run->id}/confirm")
        ->assertOk()
        ->assertJsonPath('data.import_run.status', 'completed');

    expect($run->fresh()->convert_to_opportunity)->toBeFalse()
        ->and(Lead::query()->count())->toBe(1)
        ->and(Opportunity::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// GET /api/imports/leads/{importRun}/summary — conversion_readiness
// ---------------------------------------------------------------------------

it('summary reports conversion_readiness computed from the run/rows', function () {
    $actor = conversionActor(['import']);
    $fixture = conversionReadyFixture();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'leads',
        'status' => ImportStatus::Reviewing,
        'dedup_strategy' => ImportDedupMode::Manual->value,
        // No global operator_id: only rows with their OWN override count.
        'global_config' => ['campaign_id' => $fixture['campaign']->id, 'operational_site_id' => $fixture['operationalSite']->id],
    ]);

    // Creatable, no operator.
    ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 1, 'status' => ImportRowStatus::Valid]);
    // Creatable, has its own operator.
    ImportRunRow::factory()->create([
        'import_run_id' => $run->id,
        'row_number' => 2,
        'status' => ImportRowStatus::Valid,
        'operator_id' => User::factory()->create()->id,
    ]);
    // Creatable (duplicate resolved to `create`), no operator.
    ImportRunRow::factory()->create([
        'import_run_id' => $run->id,
        'row_number' => 3,
        'status' => ImportRowStatus::Duplicate,
        'duplicate_of_id' => 999,
        'resolution' => ImportRowResolution::Create,
    ]);
    // NOT creatable: duplicate resolved to `update`.
    ImportRunRow::factory()->create([
        'import_run_id' => $run->id,
        'row_number' => 4,
        'status' => ImportRowStatus::Duplicate,
        'duplicate_of_id' => 999,
        'resolution' => ImportRowResolution::Update,
    ]);
    // NOT creatable: error/skipped rows.
    ImportRunRow::factory()->error()->create(['import_run_id' => $run->id, 'row_number' => 5]);
    ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 6, 'status' => ImportRowStatus::Skipped]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/imports/leads/{$run->id}/summary")
        ->assertOk()
        ->assertJsonPath('data.summary.conversion_readiness.campaign_derives_product_line', true)
        ->assertJsonPath('data.summary.conversion_readiness.creatable_rows', 3)
        ->assertJsonPath('data.summary.conversion_readiness.rows_without_operator', 2)
        // The global operational_site_id covers every row that has no own
        // per-row override (spec 0045, extended to mirror the operator).
        ->assertJsonPath('data.summary.conversion_readiness.rows_without_site', 0);
});

it('summary reports rows_without_site mirroring rows_without_operator when neither global nor per-row site is set', function () {
    $actor = conversionActor(['import']);
    $fixture = conversionReadyFixture();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'leads',
        'status' => ImportStatus::Reviewing,
        'dedup_strategy' => ImportDedupMode::CreateNew->value,
        // No global operational_site_id: only rows with their OWN override count.
        'global_config' => ['campaign_id' => $fixture['campaign']->id, 'operator_id' => $fixture['operator']->id],
    ]);

    ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 1, 'status' => ImportRowStatus::Valid]);
    ImportRunRow::factory()->create([
        'import_run_id' => $run->id,
        'row_number' => 2,
        'status' => ImportRowStatus::Valid,
        'operational_site_id' => OperationalSite::factory()->create()->id,
    ]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/imports/leads/{$run->id}/summary")
        ->assertOk()
        ->assertJsonPath('data.summary.conversion_readiness.creatable_rows', 2)
        ->assertJsonPath('data.summary.conversion_readiness.rows_without_operator', 0)
        ->assertJsonPath('data.summary.conversion_readiness.rows_without_site', 1);
});
