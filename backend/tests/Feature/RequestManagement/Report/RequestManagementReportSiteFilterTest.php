<?php

use App\Enums\RequestManagementReportRowMode;
use App\Jobs\GenerateRequestManagementReportJob;
use App\Models\ExportRun;
use App\Models\OperationalSite;
use App\Services\RequestManagement\Report\Dashboard\RequestManagementDashboardBuilder;
use App\Services\RequestManagement\Report\ReportOperatorFilter;
use App\Services\RequestManagement\Report\ReportSiteFilter;
use App\Services\RequestManagement\Report\RequestManagementReportGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\RequestManagement\Report\Support\SiteFilterFixture as Fixture;

// Spec 0112 — the Sede filter RESTRICTS THE CALCULATION (D-1) through the
// GA2's own memberships (D-2): AC-001..AC-008 and AC-013.

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    app()->setLocale('it');
});

// ---------------------------------------------------------------------------
// AC-001 / AC-003 — the TOTALE row narrows to the selected Sede, and is
// untouched when no selection was made
// ---------------------------------------------------------------------------

it('collapses the TOTALE row onto the selected Sede own requests (AC-001)', function () {
    $fixture = Fixture::twoSites();

    $rows = Fixture::csv(
        Fixture::actor(),
        RequestManagementReportRowMode::All,
        ReportSiteFilter::fromKeys([(string) $fixture['a']->id]),
    );

    // Ada's two requests only: not the A+B sum of three.
    expect(Fixture::cell($rows, 'GOL', 'TOTALE', 'telefonate'))->toBe(2)
        ->and(Fixture::cell($rows, 'GOL', 'TOTALE', 'richiami'))->toBe(2)
        ->and(Fixture::ga2Labels($rows, 'GOL'))->toBe(['TOTALE', 'Ada Rossi']);
});

it('leaves every value untouched when no Sede filter is passed (AC-003)', function () {
    Fixture::twoSites();
    $actor = Fixture::actor();

    $unfiltered = Fixture::csv($actor, RequestManagementReportRowMode::All, null, file: 'no-filter.csv');
    $everySite = Fixture::csv($actor, RequestManagementReportRowMode::All, ReportSiteFilter::all(), file: 'all-sites.csv');

    expect(Fixture::cell($unfiltered, 'GOL', 'TOTALE', 'telefonate'))->toBe(3)
        ->and(Fixture::ga2Labels($unfiltered, 'GOL'))->toBe(['TOTALE', 'Ada Rossi', 'Bruno Verdi'])
        // A null filter and an explicit all() are the same file, row for row.
        ->and($everySite)->toBe($unfiltered);
});

// ---------------------------------------------------------------------------
// AC-002 — the dashboard reads the same numbers as the CSV's TOTALE row
// ---------------------------------------------------------------------------

it('gives the dashboard summary the CSV TOTALE numbers under the same Sede filter (AC-002)', function () {
    $fixture = Fixture::twoSites();
    $actor = Fixture::actor();

    $sites = ReportSiteFilter::fromKeys([(string) $fixture['a']->id]);
    $rows = Fixture::csv($actor, RequestManagementReportRowMode::All, $sites, file: 'parity.csv');

    $result = app(RequestManagementDashboardBuilder::class)->build(
        $actor,
        now()->subDay()->toDateString(),
        now()->addDay()->toDateString(),
        ['gol'],
        RequestManagementReportRowMode::All,
        null,
        $sites,
    );

    $tile = static fn (string $key): int => collect($result->summary)->firstWhere('key', $key)->value;

    expect($tile('telefonate'))->toBe(Fixture::cell($rows, 'GOL', 'TOTALE', 'telefonate'))
        ->and($tile('richiami'))->toBe(Fixture::cell($rows, 'GOL', 'TOTALE', 'richiami'))
        ->and($tile('telefonate'))->toBe(2);
});

// ---------------------------------------------------------------------------
// AC-004 / AC-005 — the whole pivot, counted once
// ---------------------------------------------------------------------------

it('counts a request once whichever of its GA2 two Sedi is selected (AC-004)', function () {
    $categories = Fixture::categories();
    $siteA = Fixture::site('Via Alfa 1', 'Frattamaggiore');
    $siteB = Fixture::site('Via Beta 2', 'Aversa');
    Fixture::quote($categories['gol'], Fixture::operator('Dora Doppia', $siteA, [$siteB])->id);

    $counts = [];

    foreach (['a' => [$siteA], 'b' => [$siteB], 'both' => [$siteA, $siteB]] as $case => $sites) {
        $rows = Fixture::csv(
            Fixture::actor(abilities: ['report', 'viewAll'], name: "Attore {$case}"),
            RequestManagementReportRowMode::All,
            ReportSiteFilter::fromKeys(array_map(static fn (OperationalSite $site): string => (string) $site->id, $sites)),
            file: "duo-{$case}.csv",
        );

        $counts[$case] = Fixture::cell($rows, 'GOL', 'TOTALE', 'telefonate');
    }

    expect($counts)->toBe(['a' => 1, 'b' => 1, 'both' => 1]);
});

it('includes a request whose GA2 membership is REMOTE (AC-005)', function () {
    $categories = Fixture::categories();
    $siteA = Fixture::site('Via Alfa 1', 'Frattamaggiore');
    $remo = Fixture::operator('Remo Remoto', null, [$siteA]); // is_primary = false
    Fixture::quote($categories['gol'], $remo->id);

    $rows = Fixture::csv(
        Fixture::actor(),
        RequestManagementReportRowMode::All,
        ReportSiteFilter::fromKeys([(string) $siteA->id]),
        file: 'remote.csv',
    );

    expect(Fixture::cell($rows, 'GOL', 'TOTALE', 'telefonate'))->toBe(1)
        ->and(Fixture::ga2Labels($rows, 'GOL'))->toBe(['TOTALE', 'Remo Remoto']);
});

// ---------------------------------------------------------------------------
// AC-006 — no Sede attributable, no row: fail-closed by construction
// ---------------------------------------------------------------------------

it('drops both the unassigned request and the membership-less GA2 under a restricted filter (AC-006)', function () {
    $categories = Fixture::categories();
    $siteA = Fixture::site('Via Alfa 1', 'Frattamaggiore');
    $ada = Fixture::operator('Ada Rossi', $siteA);
    $nomad = Fixture::operator('Nadia Nomade'); // employment profile, no membership
    $actor = Fixture::actor();

    Fixture::quote($categories['gol'], $ada->id);
    Fixture::quote($categories['gol'], $nomad->id);
    Fixture::quote($categories['gol'], null); // "Non assegnato"

    $unfiltered = Fixture::csv($actor, RequestManagementReportRowMode::All, null, file: 'nosede-all.csv');
    $filtered = Fixture::csv(
        $actor,
        RequestManagementReportRowMode::All,
        ReportSiteFilter::fromKeys([(string) $siteA->id]),
        file: 'nosede-a.csv',
    );

    expect(Fixture::ga2Labels($unfiltered, 'GOL'))->toBe(['TOTALE', 'Ada Rossi', 'Nadia Nomade', 'Non assegnato'])
        ->and(Fixture::ga2Labels($filtered, 'GOL'))->toBe(['TOTALE', 'Ada Rossi'])
        ->and(Fixture::cell($filtered, 'GOL', 'TOTALE', 'richiami'))->toBe(1);
});

// ---------------------------------------------------------------------------
// AC-007 — the two axes compose in AND
// ---------------------------------------------------------------------------

it('produces zero for an operator and a Sede that do not intersect (AC-007)', function () {
    $fixture = Fixture::twoSites();

    $rows = Fixture::csv(
        Fixture::actor(),
        RequestManagementReportRowMode::All,
        ReportSiteFilter::fromKeys([(string) $fixture['b']->id]),
        ReportOperatorFilter::fromKeys([(string) $fixture['ada']->id]),
        file: 'and-composition.csv',
    );

    // Not Ada's 2, not Sede B's 1: their intersection is empty.
    expect(Fixture::cell($rows, 'GOL', 'TOTALE', 'telefonate'))->toBe(0)
        ->and(Fixture::cell($rows, 'GOL', 'TOTALE', 'richiami'))->toBe(0)
        ->and(Fixture::ga2Labels($rows, 'GOL'))->toBe(['TOTALE']);
});

// ---------------------------------------------------------------------------
// AC-008 — the filter can only ever NARROW the actor's own perimeter
// ---------------------------------------------------------------------------

it('never reveals a request the actor cannot see, even in a Sede they belong to (AC-008)', function () {
    $categories = Fixture::categories();
    $siteA = Fixture::site('Via Alfa 1', 'Frattamaggiore');

    $actor = Fixture::actor(abilities: ['report']); // no viewAll, no viewSite: only their own
    Fixture::employ($actor, $siteA);

    $stranger = Fixture::operator('Zzz Estranea', $siteA);
    Fixture::quote($categories['gol'], $actor->id);
    Fixture::quote($categories['gol'], $stranger->id);

    $rows = Fixture::csv(
        $actor,
        RequestManagementReportRowMode::All,
        ReportSiteFilter::fromKeys([(string) $siteA->id]),
        file: 'scoped.csv',
    );

    expect(Fixture::ga2Labels($rows, 'GOL'))->toBe(['TOTALE', 'Attore Report'])
        ->and(Fixture::cell($rows, 'GOL', 'TOTALE', 'richiami'))->toBe(1);
});

// ---------------------------------------------------------------------------
// AC-013 — a run frozen before this spec generates exactly what it always did
// ---------------------------------------------------------------------------

it('generates on every Sede for a run whose state has no site_keys (AC-013)', function () {
    Fixture::twoSites();

    $run = ExportRun::factory()->create([
        'user_id' => Fixture::actor()->id,
        'resource' => 'request-management-report',
        'state' => [
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
            'locale' => 'it',
            'category_keys' => array_keys((array) config('request-management-report.branches')),
            'row_mode' => 'all',
            // no site_keys: the shape every run frozen before spec 0112 has
        ],
    ]);

    (new GenerateRequestManagementReportJob($run->id))->handle(app(RequestManagementReportGenerator::class));

    $rows = Fixture::parse(Storage::disk('local')->get($run->fresh()->file_path));

    expect(Fixture::ga2Labels($rows, 'GOL'))->toBe(['TOTALE', 'Ada Rossi', 'Bruno Verdi'])
        ->and(Fixture::cell($rows, 'GOL', 'TOTALE', 'telefonate'))->toBe(3);
});

it('re-reads the frozen site_keys when the job runs (AC-013)', function () {
    $fixture = Fixture::twoSites();

    $run = ExportRun::factory()->create([
        'user_id' => Fixture::actor()->id,
        'resource' => 'request-management-report',
        'state' => [
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
            'locale' => 'it',
            'category_keys' => array_keys((array) config('request-management-report.branches')),
            'row_mode' => 'all',
            'site_keys' => [(string) $fixture['b']->id],
        ],
    ]);

    (new GenerateRequestManagementReportJob($run->id))->handle(app(RequestManagementReportGenerator::class));

    $rows = Fixture::parse(Storage::disk('local')->get($run->fresh()->file_path));

    expect(Fixture::ga2Labels($rows, 'GOL'))->toBe(['TOTALE', 'Bruno Verdi'])
        ->and(Fixture::cell($rows, 'GOL', 'TOTALE', 'telefonate'))->toBe(1);
});

// ---------------------------------------------------------------------------
// D-1 — one chokepoint: no indicator learned anything about Sedi
// ---------------------------------------------------------------------------

it('keeps the Sede condition out of every indicator', function () {
    foreach (glob(app_path('Services/RequestManagement/Report/Indicators/*.php')) as $file) {
        expect(file_get_contents($file))->not->toContain('employment_profile_operational_site');
    }

    // The one place that knows the chain, and the one place that applies it.
    expect(file_get_contents(app_path('Services/RequestManagement/Report/ReportSiteFilter.php')))
        ->toContain("whereColumn('employment_profiles.user_id', 'quotes.operator_id')")
        ->and(file_get_contents(app_path('Services/RequestManagement/Report/ReportBranchQuery.php')))
        ->toContain('$sites->applyTo(');
});
