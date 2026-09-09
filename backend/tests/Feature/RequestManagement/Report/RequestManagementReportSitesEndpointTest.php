<?php

use App\Models\ExportRun;
use App\Models\OperationalSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\RequestManagement\Report\Support\SiteFilterFixture as Fixture;

// Spec 0112 — GET /report/sites (the picker's own source and the shared
// allow-list) plus `site_keys` validation and freezing on both consumers:
// AC-009..AC-012 and AC-014.

uses(RefreshDatabase::class);

// The report's POST dispatches its generation job, and QUEUE_CONNECTION is
// `sync` under phpunit.xml: without this the job would really run and write
// real files while these tests only care about validation and frozen state.
beforeEach(function () {
    Queue::fake();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function sitesDashboardQuery(array $overrides = []): array
{
    return array_merge([
        'date_from' => '2026-09-01',
        'date_to' => '2026-09-30',
        'category_keys' => array_keys((array) config('request-management-report.branches')),
        'row_mode' => 'all',
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function sitesReportPayload(array $overrides = []): array
{
    return array_merge(sitesDashboardQuery(), ['format' => 'csv'], $overrides);
}

// ---------------------------------------------------------------------------
// AC-009 — the picker's own endpoint: in-scope Sedi, sorted by label
// ---------------------------------------------------------------------------

it('lists the in-scope Sedi sorted by label, keys as strings (AC-009)', function () {
    $categories = Fixture::categories();
    $zeta = Fixture::site('Via Zeta 9', 'Aversa');
    $alfa = Fixture::site('Via Alfa 1', 'Frattamaggiore');
    $addressless = OperationalSite::factory()->create();
    $unused = Fixture::site('Via Inutilizzata 0', 'Napoli');

    Fixture::quote($categories['gol'], Fixture::operator('Zoe', $zeta)->id);
    Fixture::quote($categories['gol'], Fixture::operator('Ada', $alfa)->id);
    Fixture::quote($categories['gol'], Fixture::operator('Nina', $addressless)->id);
    Fixture::operator('Senza Richieste', $unused); // no request: no Sede offered

    Sanctum::actingAs(Fixture::actor());

    // An addressless Sede keeps an EMPTY label and leads the list: it is real
    // and selectable, and hiding it would make it unfilterable.
    expect($this->getJson('/api/request-management/report/sites')->assertOk()->json('data.sites'))->toBe([
        ['key' => (string) $addressless->id, 'label' => ''],
        ['key' => (string) $alfa->id, 'label' => 'Via Alfa 1 - Frattamaggiore'],
        ['key' => (string) $zeta->id, 'label' => 'Via Zeta 9 - Aversa'],
    ]);
});

it('offers no Sede to an actor who sees no request (AC-009)', function () {
    $categories = Fixture::categories();
    $siteA = Fixture::site('Via Alfa 1', 'Frattamaggiore');
    Fixture::quote($categories['gol'], Fixture::operator('Ada', $siteA)->id);

    // Scoped actor (no viewAll, no viewSite): the only request belongs to
    // someone else, so the Sede behind it is not theirs to filter by.
    Sanctum::actingAs(Fixture::actor(abilities: ['report'], name: 'Attore Ristretto'));

    expect($this->getJson('/api/request-management/report/sites')->assertOk()->json('data.sites'))->toBe([]);
});

// ---------------------------------------------------------------------------
// AC-010 — authorization and the routing trap
// ---------------------------------------------------------------------------

it('403s without request-management.report (AC-010)', function () {
    Fixture::categories();
    Sanctum::actingAs(Fixture::actor(abilities: []));

    $this->getJson('/api/request-management/report/sites')->assertForbidden();
});

it('resolves report/sites to its own action, not to show(exportRun) (AC-010)', function () {
    $categories = Fixture::categories();
    Fixture::quote($categories['gol'], Fixture::operator('Ada', Fixture::site('Via Alfa 1'))->id);

    Sanctum::actingAs(Fixture::actor());

    // A real round-trip: swallowed by the wildcard it would 404 (no such run).
    $this->getJson('/api/request-management/report/sites')
        ->assertOk()
        ->assertJsonStructure(['data' => ['sites']]);

    $routes = collect(Route::getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/request-management'))
        ->values();

    expect($routes->search(fn ($route) => $route->uri() === 'api/request-management/report/sites'))
        ->toBeLessThan($routes->search(fn ($route) => $route->uri() === 'api/request-management/report/{exportRun}'));
});

// ---------------------------------------------------------------------------
// AC-011 — validation of site_keys on both consumers
// ---------------------------------------------------------------------------

it('422s on a site key outside the allow-list, on both endpoints (AC-011)', function () {
    $categories = Fixture::categories();
    $siteA = Fixture::site('Via Alfa 1', 'Frattamaggiore');
    $orphan = Fixture::site('Via Orfana 7', 'Napoli'); // real Sede, no in-scope request
    Fixture::quote($categories['gol'], Fixture::operator('Ada', $siteA)->id);

    Sanctum::actingAs(Fixture::actor());

    $keys = [(string) $siteA->id, (string) $orphan->id];

    $this->getJson('/api/request-management/report/dashboard?'.http_build_query(sitesDashboardQuery(['site_keys' => $keys])))
        ->assertStatus(422)
        ->assertJsonValidationErrors('site_keys.1');

    $this->postJson('/api/request-management/report', sitesReportPayload(['site_keys' => $keys]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('site_keys.1');

    expect(ExportRun::query()->count())->toBe(0);
});

it('422s on an empty site_keys array (AC-011)', function () {
    Fixture::categories();
    Sanctum::actingAs(Fixture::actor());

    // JSON body only: an empty array CANNOT be expressed in a query string
    // (`http_build_query` drops the key entirely), so on the dashboard's GET
    // an empty selection is indistinguishable from an absent one and means
    // "every Sede" (D-4). That gate is the client's own.
    $this->postJson('/api/request-management/report', sitesReportPayload(['site_keys' => []]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('site_keys');
});

it('200s on both endpoints when site_keys is absent (AC-011)', function () {
    $categories = Fixture::categories();
    Fixture::quote($categories['gol'], Fixture::operator('Ada', Fixture::site('Via Alfa 1'))->id);

    Sanctum::actingAs(Fixture::actor());

    $this->getJson('/api/request-management/report/dashboard?'.http_build_query(sitesDashboardQuery()))
        ->assertOk()
        ->assertJsonPath('data.applied.site_keys', null);

    $this->postJson('/api/request-management/report', sitesReportPayload())->assertCreated();
});

it('echoes site_keys as sent (AC-011)', function () {
    $categories = Fixture::categories();
    $siteA = Fixture::site('Via Alfa 1', 'Frattamaggiore');
    Fixture::quote($categories['gol'], Fixture::operator('Ada', $siteA)->id);

    Sanctum::actingAs(Fixture::actor());

    $this->getJson('/api/request-management/report/dashboard?'.http_build_query(
        sitesDashboardQuery(['site_keys' => [(string) $siteA->id]])
    ))->assertOk()->assertJsonPath('data.applied.site_keys', [(string) $siteA->id]);
});

// ---------------------------------------------------------------------------
// AC-012 — the allow-list costs a query, so it is not resolved for free
// ---------------------------------------------------------------------------

it('never resolves the Sede allow-list when site_keys was not sent (AC-012)', function () {
    $categories = Fixture::categories();
    $siteA = Fixture::site('Via Alfa 1', 'Frattamaggiore');
    Fixture::quote($categories['gol'], Fixture::operator('Ada', $siteA)->id);

    // viewAll and no viewSite on purpose: RequestManagementScope reads the
    // same pivot for its tier-3 rule, and would make this count meaningless.
    Sanctum::actingAs(Fixture::actor());

    $pivotQueries = 0;
    DB::listen(function ($query) use (&$pivotQueries): void {
        if (str_contains($query->sql, 'employment_profile_operational_site')) {
            $pivotQueries++;
        }
    });

    $this->getJson('/api/request-management/report/dashboard?'.http_build_query(sitesDashboardQuery()))->assertOk();

    expect($pivotQueries)->toBe(0);

    $this->getJson('/api/request-management/report/dashboard?'.http_build_query(
        sitesDashboardQuery(['site_keys' => [(string) $siteA->id]])
    ))->assertOk();

    expect($pivotQueries)->toBeGreaterThan(0);
});

// ---------------------------------------------------------------------------
// AC-014 — site_keys frozen in the run state ONLY when the actor filtered
// ---------------------------------------------------------------------------

it('freezes site_keys in the run state when sent (AC-014)', function () {
    $categories = Fixture::categories();
    $siteA = Fixture::site('Via Alfa 1', 'Frattamaggiore');
    Fixture::quote($categories['gol'], Fixture::operator('Ada', $siteA)->id);

    Sanctum::actingAs(Fixture::actor());

    $this->postJson('/api/request-management/report', sitesReportPayload(['site_keys' => [(string) $siteA->id]]))
        ->assertCreated();

    expect(ExportRun::query()->latest('id')->first()->state['site_keys'])->toBe([(string) $siteA->id]);
});

it('leaves the site_keys key ABSENT from the run state when not sent (AC-014)', function () {
    $categories = Fixture::categories();
    Fixture::quote($categories['gol'], Fixture::operator('Ada', Fixture::site('Via Alfa 1'))->id);

    Sanctum::actingAs(Fixture::actor());

    $this->postJson('/api/request-management/report', sitesReportPayload())->assertCreated();

    expect(ExportRun::query()->latest('id')->first()->state)->not->toHaveKey('site_keys');
});
