<?php

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\EmploymentProfile;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\User;
use App\Services\Assignment\AssignmentCandidates;
use App\Services\Assignment\AssignmentSiteResolver;
use App\Services\LeadOperatorDistributor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Spec 0113, AC-001..AC-009 — the Sede that scopes the assignment of a
 * record, derived from the record itself, and the candidate pool it composes
 * with the competence rule (spec 0111, untouched here).
 */
if (! function_exists('assignmentScopeCampaign')) {
    /** A standalone campaign carrying (or not) its own Sede — `campaigns.operational_site_id`. */
    function assignmentScopeCampaign(?OperationalSite $site): Campaign
    {
        return Campaign::factory()->create(['operational_site_id' => $site?->id]);
    }
}

if (! function_exists('assignmentScopeRow')) {
    /**
     * A staged row of $run. `$attributes` carries whatever the case needs:
     * its own `mapped_values` (per-row campaign) or the Sede override
     * AC-001 proves irrelevant.
     *
     * @param  array<string, mixed>  $attributes
     */
    function assignmentScopeRow(ImportRun $run, array $attributes = []): ImportRunRow
    {
        return ImportRunRow::factory()->for($run, 'importRun')->create($attributes);
    }
}

if (! function_exists('assignmentScopeOperator')) {
    /**
     * A user employed at $site (PHYSICAL membership) and, when a function
     * and categories are given, competent for them (spec 0111).
     */
    function assignmentScopeOperator(OperationalSite $site, ?BusinessFunction $function = null, ProductCategory ...$categories): User
    {
        $user = User::factory()->create();

        $profile = EmploymentProfile::factory()->for($user)->physicalSite($site);

        if ($function !== null) {
            $profile = $profile->competentIn($function, ...$categories);
        }

        $profile->create();

        return $user;
    }
}

if (! function_exists('assignmentScopeCategory')) {
    /** A category owning its own business function (spec 0023: the chain's only one). */
    function assignmentScopeCategory(BusinessFunction $function): ProductCategory
    {
        return ProductCategory::factory()->create([
            'business_function_id' => $function->id,
            'parent_id' => null,
        ]);
    }
}

// ---------------------------------------------------------------------------
// AC-001..AC-003 — the Sede of a STAGED IMPORT ROW comes from its campaign.
// ---------------------------------------------------------------------------

it('0113 AC-001: an import row takes the Sede of its campaign, ignoring its own operational_site_id override', function () {
    $campaignSite = OperationalSite::factory()->create();
    $overrideSite = OperationalSite::factory()->create();
    $campaign = assignmentScopeCampaign($campaignSite);
    $run = ImportRun::factory()->create();

    $row = assignmentScopeRow($run, ['operational_site_id' => $overrideSite->id]);

    expect(app(AssignmentSiteResolver::class)->forImportRows(collect([$row]), ['campaign_id' => $campaign->id]))
        ->toBe([$row->id => $campaignSite->id]);
});

it('0113 AC-002: per-row campaigns resolve each row onto the Sede of its OWN campaign', function () {
    $firstSite = OperationalSite::factory()->create();
    $secondSite = OperationalSite::factory()->create();
    $firstCampaign = assignmentScopeCampaign($firstSite);
    $secondCampaign = assignmentScopeCampaign($secondSite);
    $run = ImportRun::factory()->create();

    $firstRow = assignmentScopeRow($run, ['mapped_values' => ['campaign_id' => $firstCampaign->id]]);
    $secondRow = assignmentScopeRow($run, ['mapped_values' => ['campaign_id' => $secondCampaign->id]]);

    // The run also carries a global campaign: the per-row value must win.
    $globalConfig = ['campaign_id' => assignmentScopeCampaign(OperationalSite::factory()->create())->id];

    expect(app(AssignmentSiteResolver::class)->forImportRows(collect([$firstRow, $secondRow]), $globalConfig))
        ->toBe([$firstRow->id => $firstSite->id, $secondRow->id => $secondSite->id]);
});

it('0113 AC-003: a campaign without a Sede, or no campaign at all, maps the row onto null', function () {
    $run = ImportRun::factory()->create();
    $siteless = assignmentScopeCampaign(null);

    $sitelessRow = assignmentScopeRow($run, ['mapped_values' => ['campaign_id' => $siteless->id]]);
    $campaignlessRow = assignmentScopeRow($run, ['mapped_values' => ['email' => 'nobody@example.test']]);

    expect(app(AssignmentSiteResolver::class)->forImportRows(collect([$sitelessRow, $campaignlessRow]), []))
        ->toBe([$sitelessRow->id => null, $campaignlessRow->id => null]);
});

// ---------------------------------------------------------------------------
// AC-004 / AC-005 — real leads and offers.
// ---------------------------------------------------------------------------

it('0113 AC-004: leads take the Sede of their own campaign, never leads.operational_site_id, in a constant number of queries', function () {
    $firstSite = OperationalSite::factory()->create();
    $secondSite = OperationalSite::factory()->create();
    $decoySite = OperationalSite::factory()->create();
    $firstCampaign = assignmentScopeCampaign($firstSite);
    $secondCampaign = assignmentScopeCampaign($secondSite);

    // Every lead carries a DIFFERENT Sede of its own: D-3 says it is ignored.
    $leads = collect([$firstCampaign, $secondCampaign, $firstCampaign, $secondCampaign])
        ->map(fn (Campaign $campaign): Lead => Lead::factory()->create([
            'campaign_id' => $campaign->id,
            'operational_site_id' => $decoySite->id,
        ]));

    $resolver = app(AssignmentSiteResolver::class);

    DB::enableQueryLog();
    $twoLeads = $resolver->forLeads($leads->take(2)->pluck('id')->all());
    $twoQueries = count(DB::getQueryLog());
    DB::flushQueryLog();
    $allLeads = $resolver->forLeads($leads->pluck('id')->all());
    $allQueries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($allLeads)->toBe([
        $leads[0]->id => $firstSite->id,
        $leads[1]->id => $secondSite->id,
        $leads[2]->id => $firstSite->id,
        $leads[3]->id => $secondSite->id,
    ])
        ->and($twoLeads)->toHaveCount(2)
        ->and($allQueries)->toBe($twoQueries);
});

it('0113 AC-005: offers take their own quotes.operational_site_id, null included', function () {
    $site = OperationalSite::factory()->create();
    $sited = Quote::factory()->create(['operational_site_id' => $site->id]);
    $siteless = Quote::factory()->create(['operational_site_id' => null]);

    expect(app(AssignmentSiteResolver::class)->forQuotes([$sited->id, $siteless->id]))
        ->toBe([$sited->id => $site->id, $siteless->id => null]);
});

// ---------------------------------------------------------------------------
// AC-006..AC-008 — the candidate pool: Sede INTERSECTED with competence.
// ---------------------------------------------------------------------------

it('0113 AC-006: the candidates are the members of the record Sede competent for its categories, ascending', function () {
    $site = OperationalSite::factory()->create();
    $otherSite = OperationalSite::factory()->create();
    $function = BusinessFunction::factory()->create();
    $category = assignmentScopeCategory($function);

    $competentHere = assignmentScopeOperator($site, $function, $category);
    $competentElsewhere = assignmentScopeOperator($otherSite, $function, $category);
    $incompetentHere = assignmentScopeOperator($site, $function, assignmentScopeCategory(BusinessFunction::factory()->create()));
    $alsoCompetentHere = assignmentScopeOperator($site, $function, $category);

    $candidates = app(AssignmentCandidates::class)->byRecord(
        [7 => $site->id],
        [7 => [$category->id]],
    );

    expect($candidates[7])->toBe([$competentHere->id, $alsoCompetentHere->id])
        ->not->toContain($competentElsewhere->id)
        ->not->toContain($incompetentHere->id);
});

it('0113 AC-007: a record without a Sede has an EMPTY pool, with no fallback on every operator', function () {
    $function = BusinessFunction::factory()->create();
    $category = assignmentScopeCategory($function);
    assignmentScopeOperator(OperationalSite::factory()->create(), $function, $category);

    expect(app(AssignmentCandidates::class)->byRecord([7 => null], [7 => [$category->id]]))
        ->toBe([7 => []]);
});

it('0113 AC-008: a record demanding no category keeps the whole Sede, competence configured or not', function () {
    $site = OperationalSite::factory()->create();
    $function = BusinessFunction::factory()->create();

    $configured = assignmentScopeOperator($site, $function, assignmentScopeCategory($function));
    $rowless = assignmentScopeOperator($site);
    assignmentScopeOperator(OperationalSite::factory()->create(), $function);

    expect(app(AssignmentCandidates::class)->byRecord([7 => $site->id], [7 => []]))
        ->toBe([7 => collect([$configured->id, $rowless->id])->sort()->values()->all()]);
});

// ---------------------------------------------------------------------------
// AC-009 — one query for the whole batch of Sedi.
// ---------------------------------------------------------------------------

it('0113 AC-009: operatorIdsBySite answers every requested Sede in ONE query, empty ones included', function () {
    $firstSite = OperationalSite::factory()->create();
    $secondSite = OperationalSite::factory()->create();
    $emptySite = OperationalSite::factory()->create();

    $firstOperator = assignmentScopeOperator($firstSite);
    $secondOperator = assignmentScopeOperator($secondSite);
    // A REMOTE membership is operative exactly like the physical one (0103 D-1).
    $remoteOperator = User::factory()->create();
    EmploymentProfile::factory()->for($remoteOperator)->remoteSites($firstSite)->create();

    DB::enableQueryLog();
    $operatorIdsBySite = app(LeadOperatorDistributor::class)
        ->operatorIdsBySite([$firstSite->id, $secondSite->id, $emptySite->id]);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($operatorIdsBySite)->toBe([
        $firstSite->id => collect([$firstOperator->id, $remoteOperator->id])->sort()->values()->all(),
        $secondSite->id => [$secondOperator->id],
        $emptySite->id => [],
    ])->and($queries)->toHaveCount(1);
});
