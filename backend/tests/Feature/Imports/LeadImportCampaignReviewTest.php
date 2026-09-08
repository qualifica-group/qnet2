<?php

use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Models\Campaign;
use App\Models\City;
use App\Models\Country;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Province;
use App\Models\State;
use App\Models\User;
use App\Services\Import\ImportOpportunityConvertibility;
use App\Services\ImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0108 — the review side: pinning a campaign on a staged row, the
 * per-row product coherence that follows from it, the bulk assignment's
 * all-or-nothing rule and the conversion readiness across campaigns.
 *
 * @param  array<int, string>  $abilities
 */
function campaignReviewActor(array $abilities = ['import']): User
{
    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        Permission::findOrCreate("leads.{$ability}");
    }

    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        $user->givePermissionTo("leads.{$ability}");
    }

    grantImportRunsPermissions($user, ['viewAny', 'view', 'update']);

    return $user;
}

/**
 * A `reviewing` run, per-row campaign mode unless $globalCampaign is given.
 */
function campaignReviewRun(User $actor, ?Campaign $globalCampaign = null): ImportRun
{
    return ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'leads',
        'status' => ImportStatus::Reviewing,
        'column_mapping' => $globalCampaign === null
            ? ['Email' => 'email', 'Nome' => 'first_name', 'Cognome' => 'last_name', 'Codice campagna' => 'campaign_code']
            : ['Email' => 'email', 'Nome' => 'first_name', 'Cognome' => 'last_name'],
        'global_config' => $globalCampaign === null ? [] : ['campaign_id' => $globalCampaign->id],
        'dedup_strategy' => 'create_new',
    ]);
}

/**
 * @param  array<string, mixed>  $mapped
 * @param  array<string, string>  $raw
 */
function campaignReviewRow(ImportRun $run, array $mapped, array $raw = [], ImportRowStatus $status = ImportRowStatus::Error): ImportRunRow
{
    return ImportRunRow::factory()->for($run)->create([
        'row_number' => 1,
        'status' => $status,
        'raw_values' => $raw,
        'mapped_values' => $mapped,
    ]);
}

/**
 * Local geo chain / covered-category helpers: this file must run on its own
 * (`pest <this file>`), so it never borrows a helper another test file
 * happens to define.
 *
 * @return array{country: Country, state: State, province: Province, city: City}
 */
function campaignReviewGeoChain(): array
{
    $country = Country::factory()->create(['name' => 'Italy']);
    $state = State::factory()->create(['name' => 'Lombardy', 'country_id' => $country->id]);
    $province = Province::factory()->create(['name' => 'Milan', 'state_id' => $state->id, 'country_id' => $country->id]);
    $city = City::factory()->create(['name' => 'Milan', 'province_id' => $province->id, 'state_id' => $state->id, 'country_id' => $country->id]);

    return compact('country', 'state', 'province', 'city');
}

/** The ONE product category a standalone Campaign::factory() row already covers. */
function campaignReviewCoveredCategory(Campaign $campaign): ProductCategory
{
    return $campaign->productLines()->first()->productCategory;
}

// ---------------------------------------------------------------------------
// AC-013..AC-016 — pinning a campaign on one row
// ---------------------------------------------------------------------------

it('AC-013: pinning a campaign on an unmatched row makes it valid again and records the canonical code', function () {
    $actor = campaignReviewActor();
    $campaign = Campaign::factory()->create();
    $run = campaignReviewRun($actor);
    $row = campaignReviewRow(
        $run,
        ['email' => 'a@example.com', 'first_name' => 'Mario', 'last_name' => 'Rossi', 'campaign_code' => 'CMP-9999'],
        ['Email' => 'a@example.com', 'Nome' => 'Mario', 'Cognome' => 'Rossi', 'Codice campagna' => 'CMP-9999'],
    );
    app(ImportService::class)->recomputeCounts($run->fresh());
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", ['campaign_id' => $campaign->id])
        ->assertOk()
        ->assertJsonPath('data.row.status', 'valid')
        ->assertJsonPath('data.row.is_edited', true)
        ->assertJsonPath('data.row.campaign_id', $campaign->id)
        ->assertJsonPath('data.row.campaign.code', $campaign->code);

    $row->refresh();
    expect($row->mapped_values['campaign_id'])->toBe($campaign->id)
        ->and($row->mapped_values['campaign_code'])->toBe($campaign->code)
        ->and($run->fresh()->invalid_rows)->toBe(0);
});

it('AC-014: unpinning with campaign_id null hands the row back to the recognizer and it fails again', function () {
    $actor = campaignReviewActor();
    $campaign = Campaign::factory()->create();
    $run = campaignReviewRun($actor);
    $row = campaignReviewRow(
        $run,
        ['email' => 'a@example.com', 'first_name' => 'Mario', 'last_name' => 'Rossi', 'campaign_code' => $campaign->code, 'campaign_id' => $campaign->id],
        ['Email' => 'a@example.com', 'Nome' => 'Mario', 'Cognome' => 'Rossi', 'Codice campagna' => 'CMP-9999'],
        ImportRowStatus::Valid,
    );
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", ['campaign_id' => null])
        ->assertOk()
        ->assertJsonPath('data.row.status', 'error')
        ->assertJsonPath('data.row.campaign_id', null);

    $row->refresh();
    expect($row->mapped_values)->not->toHaveKey('campaign_id')
        ->and($row->mapped_values['campaign_code'])->toBe('CMP-9999');
});

it('AC-015: campaign_id is rejected on a global-campaign run, and an unknown id is rejected everywhere', function () {
    $actor = campaignReviewActor();
    $globalCampaign = Campaign::factory()->create();
    $globalRun = campaignReviewRun($actor, $globalCampaign);
    $globalRow = campaignReviewRow($globalRun, ['email' => 'a@example.com', 'first_name' => 'Mario', 'last_name' => 'Rossi'], [], ImportRowStatus::Valid);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$globalRun->id}/rows/{$globalRow->id}", ['campaign_id' => $globalCampaign->id])
        ->assertStatus(422)->assertJsonValidationErrors('campaign_id');

    $perRowRun = campaignReviewRun($actor);
    $perRowRow = campaignReviewRow($perRowRun, ['email' => 'b@example.com', 'first_name' => 'Anna', 'last_name' => 'Verdi', 'campaign_code' => 'CMP-9999']);

    $this->patchJson("/api/imports/leads/{$perRowRun->id}/rows/{$perRowRow->id}", ['campaign_id' => 999999])
        ->assertStatus(422)->assertJsonValidationErrors('campaign_id');
});

it('AC-016: pinning a campaign leaves an already pinned geo untouched', function () {
    $actor = campaignReviewActor();
    $campaign = Campaign::factory()->create();
    $geo = campaignReviewGeoChain();
    $run = campaignReviewRun($actor);
    $row = campaignReviewRow($run, [
        'email' => 'a@example.com', 'first_name' => 'Mario', 'last_name' => 'Rossi',
        'campaign_code' => 'CMP-9999',
        'country' => $geo['country']->name, 'region' => $geo['state']->name, 'province' => $geo['province']->name, 'city' => $geo['city']->name,
        'country_id' => $geo['country']->id, 'state_id' => $geo['state']->id, 'province_id' => $geo['province']->id, 'city_id' => $geo['city']->id,
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", ['campaign_id' => $campaign->id])->assertOk();

    $row->refresh();
    expect($row->mapped_values['country_id'])->toBe($geo['country']->id)
        ->and($row->mapped_values['state_id'])->toBe($geo['state']->id)
        ->and($row->mapped_values['province_id'])->toBe($geo['province']->id)
        ->and($row->mapped_values['city_id'])->toBe($geo['city']->id);
});

// ---------------------------------------------------------------------------
// AC-017 / AC-018 — product coherence against the ROW's campaign
// ---------------------------------------------------------------------------

it('AC-017: a per-row product override is checked against the ROW campaign, not another row\'s', function () {
    $actor = campaignReviewActor();
    $campaign = Campaign::factory()->create();
    $otherCampaign = Campaign::factory()->create();
    $covered = Product::factory()->create(['category_id' => campaignReviewCoveredCategory($campaign)->id]);
    $foreign = Product::factory()->create(['category_id' => campaignReviewCoveredCategory($otherCampaign)->id]);
    $run = campaignReviewRun($actor);
    $row = campaignReviewRow(
        $run,
        ['email' => 'a@example.com', 'first_name' => 'Mario', 'last_name' => 'Rossi', 'campaign_code' => $campaign->code, 'campaign_id' => $campaign->id],
        [],
        ImportRowStatus::Valid,
    );
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", ['product_ids' => [$covered->id]])->assertOk();
    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", ['product_ids' => [$foreign->id]])
        ->assertStatus(422)->assertJsonValidationErrors('product_ids');
});

it('AC-018: a bulk product assignment across campaigns is all-or-nothing and names the offending rows', function () {
    $actor = campaignReviewActor();
    $campaign = Campaign::factory()->create();
    $otherCampaign = Campaign::factory()->create();
    $covered = Product::factory()->create(['category_id' => campaignReviewCoveredCategory($campaign)->id]);
    $run = campaignReviewRun($actor);

    $good = ImportRunRow::factory()->for($run)->create([
        'row_number' => 1,
        'status' => ImportRowStatus::Valid,
        'mapped_values' => ['email' => 'a@example.com', 'campaign_id' => $campaign->id],
    ]);
    $bad = ImportRunRow::factory()->for($run)->create([
        'row_number' => 2,
        'status' => ImportRowStatus::Valid,
        'mapped_values' => ['email' => 'b@example.com', 'campaign_id' => $otherCampaign->id],
    ]);
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'select_all' => false,
        'row_ids' => [$good->id, $bad->id],
        'product_ids' => [$covered->id],
    ])->assertStatus(422)->assertJsonValidationErrors('product_ids');

    expect($response->json('errors.product_ids.0'))->toContain('2')
        ->and($good->fresh()->product_ids)->toBeNull()
        ->and($bad->fresh()->product_ids)->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-019 / AC-020 — readiness across campaigns, and the batched row payload
// ---------------------------------------------------------------------------

it('AC-019: the conversion readiness holds only when EVERY row campaign derives a product line', function () {
    $actor = campaignReviewActor();
    $deriving = Campaign::factory()->create();
    $nonDeriving = Campaign::factory()->create();
    $nonDeriving->productLines()->delete();
    $run = campaignReviewRun($actor);

    ImportRunRow::factory()->for($run)->create([
        'row_number' => 1,
        'status' => ImportRowStatus::Valid,
        'mapped_values' => ['email' => 'a@example.com', 'campaign_id' => $deriving->id],
    ]);

    expect(app(ImportOpportunityConvertibility::class)->assess($run->fresh())->campaignDerivesProductLine)->toBeTrue();

    ImportRunRow::factory()->for($run)->create([
        'row_number' => 2,
        'status' => ImportRowStatus::Valid,
        'mapped_values' => ['email' => 'b@example.com', 'campaign_id' => $nonDeriving->id],
    ]);

    expect(app(ImportOpportunityConvertibility::class)->assess($run->fresh())->campaignDerivesProductLine)->toBeFalse();
});

it('AC-020: the review rows expose their campaign and resolve every label in ONE query', function () {
    $actor = campaignReviewActor();
    $first = Campaign::factory()->create();
    $second = Campaign::factory()->create();
    $run = campaignReviewRun($actor);

    foreach ([[1, $first], [2, $second], [3, $first]] as [$number, $campaign]) {
        ImportRunRow::factory()->for($run)->create([
            'row_number' => $number,
            'status' => ImportRowStatus::Valid,
            'mapped_values' => ['email' => "row{$number}@example.com", 'campaign_code' => $campaign->code, 'campaign_id' => $campaign->id],
        ]);
    }
    Sanctum::actingAs($actor);

    $campaignQueries = 0;
    DB::listen(function ($query) use (&$campaignQueries): void {
        if (str_contains($query->sql, 'from "campaigns"') || str_contains($query->sql, 'from `campaigns`')) {
            $campaignQueries++;
        }
    });

    $response = $this->postJson("/api/imports/leads/{$run->id}/rows", [
        'sortModel' => [['colId' => 'row_number', 'sort' => 'asc']],
    ])->assertOk();

    expect($response->json('items.0.campaign_id'))->toBe($first->id)
        ->and($response->json('items.0.campaign.code'))->toBe($first->code)
        ->and($response->json('items.1.campaign.name'))->toBe($second->name)
        ->and($campaignQueries)->toBe(1);
});
