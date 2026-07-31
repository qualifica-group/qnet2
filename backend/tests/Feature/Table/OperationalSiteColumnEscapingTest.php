<?php

use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Security-focused coverage for App\Tables\Shared\OperationalSiteColumn (spec
 * 0056; backend.md §8: dynamic filters are a SQLi sink unless every value
 * stays a bound parameter). Both `opportunities` and `request-management`
 * delegate their `operational_site` distinctValues() search to this ONE class,
 * so proving the escaping here once covers both domains' write path. The
 * advanced-filter LIKE is exercised through `opportunities` only: since the
 * user directive of 2026-07-31, request-management's own advanced filter is an
 * id-based picker with no free text to escape.
 *
 * OpportunityTableTest/RequestManagementTableTest's own AC-009/010/012 tests
 * only exercise CLEAN needles ("Milano", "Via Roma 1") — that proves the
 * filter WORKS, but a clean value cannot tell an escaped LIKE apart from an
 * unescaped one. This file closes that gap with real LIKE metacharacters
 * (`%`, `_`) and an apostrophe, which no other test in the suite does.
 *
 * The SET filter (whereIn on line1, exact match) has no LIKE at all and is
 * deliberately NOT exercised here: only the two bound, escaped LIKE calls in
 * OperationalSiteColumn — `applyAdvancedFilter()` (POST {domain}/rows) and
 * `distinctValues()`'s own `$search` (POST {domain}/values) — are a
 * wildcard-injection risk.
 */
uses(RefreshDatabase::class);

if (! function_exists('siteWithLine1')) {
    function siteWithLine1(string $line1): OperationalSite
    {
        $site = OperationalSite::factory()->create();
        $site->addresses()->create(['line1' => $line1, 'is_primary' => true]);

        return $site;
    }
}

if (! function_exists('operationalSiteEscapingOpportunityActor')) {
    function operationalSiteEscapingOpportunityActor(): User
    {
        Permission::findOrCreate('opportunities.viewAny');
        $actor = User::factory()->create();
        $actor->givePermissionTo('opportunities.viewAny');

        return $actor;
    }
}

if (! function_exists('operationalSiteEscapingRequestManagementActor')) {
    function operationalSiteEscapingRequestManagementActor(): User
    {
        foreach (['viewAny', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }
        $actor = User::factory()->create();
        $actor->givePermissionTo(['request-management.viewAny', 'request-management.viewAll']);

        return $actor;
    }
}

if (! function_exists('advancedFilterIds')) {
    /**
     * POST {route} with an `operational_site` advanced (text) filter,
     * returning the matched row ids.
     *
     * @return array<int, int>
     */
    function advancedFilterIds(string $route, string $needle): array
    {
        return collect(test()->postJson($route, [
            'startRow' => 0,
            'endRow' => 25,
            'advancedFilters' => ['operational_site' => $needle],
        ])->assertOk()->json('items'))->pluck('id')->all();
    }
}

if (! function_exists('operationalSiteDistinctValues')) {
    /**
     * POST {route}/values for the `operational_site` column, searched —
     * OperationalSiteColumn::distinctValues()'s own bound, escaped LIKE.
     *
     * @return array<int, string>
     */
    function operationalSiteDistinctValues(string $route, string $search): array
    {
        return test()->postJson($route, ['columnId' => 'operational_site', 'search' => $search])
            ->assertOk()
            ->json('data.values');
    }
}

// ---------------------------------------------------------------------------
// `%` — a literal percent in the needle must NOT become a second wildcard
// ---------------------------------------------------------------------------

it('opportunities: a literal % in the advanced filter needle stays literal, not a wildcard (AC-010)', function () {
    $actor = operationalSiteEscapingOpportunityActor();
    $target = siteWithLine1('Via 100% Sconto');
    $decoy = siteWithLine1('Via 1008 Sconto');
    $matching = Opportunity::factory()->create(['operational_site_id' => $target->id]);
    Opportunity::factory()->create(['operational_site_id' => $decoy->id]);
    Sanctum::actingAs($actor);

    // Unescaped, LIKE '%100%%' also matches "Via 1008 Sconto" (the literal %
    // becomes a second wildcard swallowing the "8"); escaped, only the exact
    // "100%" substring matches.
    expect(advancedFilterIds('/api/tables/opportunities/rows', '100%'))->toBe([$matching->id]);
});

// ---------------------------------------------------------------------------
// `_` — a literal underscore must NOT become a single-char wildcard
// ---------------------------------------------------------------------------

it('opportunities: a literal _ in the advanced filter needle stays literal, not a single-char wildcard (AC-010)', function () {
    $actor = operationalSiteEscapingOpportunityActor();
    $target = siteWithLine1('Via A_1 Building');
    $decoy = siteWithLine1('Via AX1 Building');
    $matching = Opportunity::factory()->create(['operational_site_id' => $target->id]);
    Opportunity::factory()->create(['operational_site_id' => $decoy->id]);
    Sanctum::actingAs($actor);

    // Unescaped, LIKE '%A_1%' also matches "Via AX1 Building" (`_` matches
    // any single character); escaped, only the literal "A_1" matches.
    expect(advancedFilterIds('/api/tables/opportunities/rows', 'A_1'))->toBe([$matching->id]);
});

// ---------------------------------------------------------------------------
// apostrophe — proves the value is a BOUND parameter, never concatenated
// ---------------------------------------------------------------------------

it('opportunities: an apostrophe in the advanced filter needle never breaks the query (bound parameter) (AC-010)', function () {
    $actor = operationalSiteEscapingOpportunityActor();
    $site = siteWithLine1("Via dell'Orso 5");
    $matching = Opportunity::factory()->create(['operational_site_id' => $site->id]);
    Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    expect(advancedFilterIds('/api/tables/opportunities/rows', "dell'Orso"))->toBe([$matching->id]);
});

// ---------------------------------------------------------------------------
// The other bound, escaped LIKE call in the shared class: distinctValues()'s
// own `search` parameter (POST {domain}/values) — same 3 payloads
// ---------------------------------------------------------------------------

it('opportunities: distinctValues search for a literal % stays literal, not a wildcard (AC-010)', function () {
    $actor = operationalSiteEscapingOpportunityActor();
    $target = siteWithLine1('Via 100% Sconto');
    $decoy = siteWithLine1('Via 1008 Sconto');
    Opportunity::factory()->create(['operational_site_id' => $target->id]);
    Opportunity::factory()->create(['operational_site_id' => $decoy->id]);
    Sanctum::actingAs($actor);

    $values = operationalSiteDistinctValues('/api/tables/opportunities/values', '100%');

    expect($values)->toContain('Via 100% Sconto')->not->toContain('Via 1008 Sconto');
});

it('request-management: distinctValues search for a literal % stays literal, not a wildcard (AC-012)', function () {
    $actor = operationalSiteEscapingRequestManagementActor();
    $target = siteWithLine1('Via 100% Sconto');
    $decoy = siteWithLine1('Via 1008 Sconto');
    Opportunity::factory()->create(['operational_site_id' => $target->id]);
    Opportunity::factory()->create(['operational_site_id' => $decoy->id]);
    Sanctum::actingAs($actor);

    $values = operationalSiteDistinctValues('/api/tables/request-management/values', '100%');

    expect($values)->toContain('Via 100% Sconto')->not->toContain('Via 1008 Sconto');
});

it('opportunities: distinctValues search for a literal _ stays literal, not a single-char wildcard (AC-010)', function () {
    $actor = operationalSiteEscapingOpportunityActor();
    $target = siteWithLine1('Via A_1 Building');
    $decoy = siteWithLine1('Via AX1 Building');
    Opportunity::factory()->create(['operational_site_id' => $target->id]);
    Opportunity::factory()->create(['operational_site_id' => $decoy->id]);
    Sanctum::actingAs($actor);

    $values = operationalSiteDistinctValues('/api/tables/opportunities/values', 'A_1');

    expect($values)->toContain('Via A_1 Building')->not->toContain('Via AX1 Building');
});

it('request-management: distinctValues search for a literal _ stays literal, not a single-char wildcard (AC-012)', function () {
    $actor = operationalSiteEscapingRequestManagementActor();
    $target = siteWithLine1('Via A_1 Building');
    $decoy = siteWithLine1('Via AX1 Building');
    Opportunity::factory()->create(['operational_site_id' => $target->id]);
    Opportunity::factory()->create(['operational_site_id' => $decoy->id]);
    Sanctum::actingAs($actor);

    $values = operationalSiteDistinctValues('/api/tables/request-management/values', 'A_1');

    expect($values)->toContain('Via A_1 Building')->not->toContain('Via AX1 Building');
});

it('opportunities: distinctValues search with an apostrophe never breaks the query (bound parameter)', function () {
    $actor = operationalSiteEscapingOpportunityActor();
    $site = siteWithLine1("Via dell'Orso 5");
    Opportunity::factory()->create(['operational_site_id' => $site->id]);
    Sanctum::actingAs($actor);

    expect(operationalSiteDistinctValues('/api/tables/opportunities/values', "dell'Orso"))->toBe(["Via dell'Orso 5"]);
});

it('request-management: distinctValues search with an apostrophe never breaks the query (bound parameter)', function () {
    $actor = operationalSiteEscapingRequestManagementActor();
    $site = siteWithLine1("Via dell'Orso 5");
    Opportunity::factory()->create(['operational_site_id' => $site->id]);
    Sanctum::actingAs($actor);

    expect(operationalSiteDistinctValues('/api/tables/request-management/values', "dell'Orso"))->toBe(["Via dell'Orso 5"]);
});
