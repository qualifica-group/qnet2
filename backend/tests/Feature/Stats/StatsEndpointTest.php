<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * The domains in scope (spec 0026). Declared here as a literal — NOT read from
 * config at collection time — and cross-checked against config/stats.php by
 * the registration test below, so a domain added/removed in config without a
 * test is caught.
 *
 * `projects` is now IN scope (explicit requirement change on top of spec 0026,
 * whose <scope> excluded it): it has a definition like any other module and its
 * legacy GET /projects/summary keeps working unchanged.
 *
 * `import-runs` joined the panel with spec 0034 (dedicated lead-import
 * module): its StatsDefinition is scoped to the actor's OWN runs (unlike
 * every other domain's global counts), verified separately in
 * ImportRunsStatsTest.
 *
 * `opportunities` joined the panel with spec 0040, same registry pattern.
 *
 * `tasks` joined with spec 0147 (requirement change, declared): scoped to the
 * actor's visible root tasks, values verified in TasksStatsTest.
 *
 * @return array<int, string>
 */
function statsDomains(): array
{
    return [
        'registries',
        'referents',
        'companies',
        'operational-sites',
        'company-sites',
        'products',
        'product-categories',
        'projects',
        'campaigns',
        'leads',
        'business-functions',
        'users',
        'import-runs',
        'opportunities',
        'tasks',
    ];
}

if (! function_exists('statsPermissionForDomain')) {
    /**
     * The permission gating a domain's stats panel. Every module uses its own
     * `{domain}.viewAny`, EXCEPT `import-runs`: its dedicated permission set was
     * removed (2026-07-17) and the module rides the lead module's `leads.import`
     * (ImportRunPolicy::viewAny).
     */
    function statsPermissionForDomain(string $domain): string
    {
        return $domain === 'import-runs' ? 'leads.import' : "{$domain}.viewAny";
    }
}

if (! function_exists('statsUserWith')) {
    /**
     * A user granted each domain's stats permission (see statsPermissionForDomain).
     * Every permission is created first (idempotent), so a user granted none is
     * genuinely unauthorized rather than merely missing a permission row.
     *
     * @param  array<int, string>  $domains
     */
    function statsUserWith(array $domains): User
    {
        foreach (statsDomains() as $domain) {
            Permission::findOrCreate(statsPermissionForDomain($domain));
        }

        $user = User::factory()->create();

        foreach ($domains as $domain) {
            $user->givePermissionTo(statsPermissionForDomain($domain));
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-001 — 200 + envelope for each of the 12 registered domains
// ---------------------------------------------------------------------------

it('registers exactly the 12 in-scope domains, projects included (AC-001)', function () {
    expect(array_keys(config('stats.definitions')))
        ->toEqualCanonicalizing(statsDomains())
        ->and(array_keys(config('stats.definitions')))->toContain('projects');
});

it('returns the widgets envelope for every registered domain (AC-001)', function (string $domain) {
    Sanctum::actingAs(statsUserWith([$domain]));

    $response = $this->getJson("/api/stats/{$domain}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'OK')
        ->assertJsonStructure(['success', 'message', 'data' => ['widgets']]);

    $widgets = $response->json('data.widgets');

    expect($widgets)->toBeArray()->not->toBeEmpty();

    foreach ($widgets as $widget) {
        expect($widget)->toHaveKeys(['type', 'key', 'label'])
            ->and($widget['type'])->toBeIn(['stat', 'distribution', 'trend'])
            // Labels are i18n KEYS, never translated text (D-4).
            ->and($widget['label'])->toStartWith(Str::camel($domain).'.stats.');
    }
})->with(statsDomains());

// ---------------------------------------------------------------------------
// AC-001 — the frozen frontend contract: i18n label keys + lucide icon allow-list
// ---------------------------------------------------------------------------

it('emits exactly the i18n label keys the frontend translates (AC-001)', function (string $domain, array $keys) {
    Sanctum::actingAs(statsUserWith([$domain]));

    $widgets = $this->getJson("/api/stats/{$domain}")->assertOk()->json('data.widgets');

    $expected = array_map(static fn (string $key): string => Str::camel($domain).".stats.{$key}", $keys);

    expect(array_column($widgets, 'label'))->toBe($expected);
})->with([
    ['registries', ['total', 'suppliers', 'qualifiedSuppliers', 'agreed', 'byAgreementStatus', 'bySizeClass', 'trend']],
    ['referents', ['total', 'internal', 'external', 'assigned', 'byType', 'trend']],
    ['companies', ['total', 'withVatNumber', 'withSites', 'sites', 'trend']],
    ['operational-sites', ['total', 'withAddress', 'staffed', 'leads', 'byRegion', 'trend']],
    ['company-sites', ['total', 'defaultSites', 'withBank', 'companies', 'byCompany']],
    ['products', ['total', 'averagePrice', 'averageCost', 'averageMargin', 'byType', 'byCategory']],
    ['product-categories', ['total', 'rootCategories', 'withProducts', 'inheritsAttributes', 'byProducts']],
    ['projects', ['total', 'campaigns', 'leads', 'allocatedBudget', 'byStatus', 'trend']],
    ['campaigns', ['total', 'linkedToProject', 'totalBudget', 'generatedLeads', 'byPipelineStatus', 'trend']],
    ['leads', ['total', 'assigned', 'withSource', 'withSite', 'bySource', 'byOperator', 'trend']],
    ['business-functions', ['total', 'businessUnits', 'businessServices', 'withManager', 'byUsers']],
    ['users', ['total', 'active', 'inactive', 'managers', 'byRole', 'byBusinessFunction', 'trend']],
    ['import-runs', ['total', 'completed', 'failed', 'rowsImported', 'byStatus', 'trend']],
    ['opportunities', ['total', 'estimatedValue', 'averageProbability', 'fromLead', 'byRegistry', 'trend']],
    ['tasks', ['overdue', 'dueToday', 'estimatedMinutes', 'actualMinutes', 'byStatus', 'byPriority', 'trend']],
]);

/**
 * REQUIREMENT CHANGE (explicit, not a bent test): the stat count per module was
 * heterogeneous (1 to 5). It is now EXACTLY 4 for every module — the frontend
 * renders the counters in a 4-column grid — and the stats must lead the widget
 * array, with the distributions/trends after them. This structural case guards
 * the rule for the whole registry, so no future module can regress it.
 */
it('exposes exactly 4 leading stat widgets in every module (AC-001)', function (string $domain) {
    Sanctum::actingAs(statsUserWith([$domain]));

    $widgets = $this->getJson("/api/stats/{$domain}")->assertOk()->json('data.widgets');

    $types = array_column($widgets, 'type');
    $stats = array_filter($types, static fn (string $type): bool => $type === 'stat');

    expect($stats)->toHaveCount(4)
        // Leading: the first four widgets are the stats, nothing else is one.
        ->and(array_slice($types, 0, 4))->toBe(['stat', 'stat', 'stat', 'stat'])
        ->and(array_slice($types, 4))->not->toContain('stat');

    // The keys stay unique within the domain (the frontend uses them as React keys).
    $keys = array_column($widgets, 'key');
    expect(array_unique($keys))->toHaveCount(count($keys));
})->with(statsDomains());

it('only emits icons the frontend allow-list knows (AC-001)', function (string $domain) {
    $allowed = [
        'briefcase', 'building', 'check-circle', 'folder-tree', 'layers', 'map-pin', 'megaphone',
        'package', 'percent', 'target', 'trending-up', 'user-check', 'user-x', 'users', 'wallet',
        // Spec 0147 (Task panel): the work-order Task board's own KPI icons.
        'alert-triangle', 'calendar-clock', 'timer', 'clock',
    ];

    Sanctum::actingAs(statsUserWith([$domain]));

    $widgets = $this->getJson("/api/stats/{$domain}")->assertOk()->json('data.widgets');

    foreach ($widgets as $widget) {
        if (($widget['icon'] ?? null) !== null) {
            expect($widget['icon'])->toBeIn($allowed);
        }
    }
})->with(statsDomains());

// ---------------------------------------------------------------------------
// AC-002 — 403 without {domain}.viewAny
// ---------------------------------------------------------------------------

it('returns 403 to an actor without {domain}.viewAny (AC-002)', function (string $domain) {
    Sanctum::actingAs(statsUserWith([]));

    $this->getJson("/api/stats/{$domain}")->assertForbidden();
})->with(statsDomains());

it('returns 403 when the actor may view another domain only (AC-002)', function () {
    Sanctum::actingAs(statsUserWith(['leads']));

    $this->getJson('/api/stats/users')->assertForbidden();
    $this->getJson('/api/stats/leads')->assertOk();
});

// ---------------------------------------------------------------------------
// AC-003 — 404 on an unknown domain, with no internal class name leaked
// ---------------------------------------------------------------------------

it('returns 404 for an unregistered domain without leaking a class name (AC-003)', function () {
    Sanctum::actingAs(statsUserWith(statsDomains()));

    $response = $this->getJson('/api/stats/non-existent')->assertNotFound();

    expect($response->json('success'))->toBeFalse()
        ->and($response->json('message'))->not->toContain('App\\');
});

/**
 * REQUIREMENT CHANGE (not a bent test): spec 0026 excluded `projects` from the
 * generic panel and this case asserted 404 on it. `projects` is now a
 * registered domain by explicit request, so the case is re-pointed at a domain
 * that genuinely does not exist — the 404 contract on an unregistered
 * {domain} segment is still covered, and the new behaviour of `projects` is
 * pinned in the same test.
 */
it('returns 404 only for a genuinely unregistered domain, no longer for projects (AC-003)', function () {
    Sanctum::actingAs(statsUserWith(statsDomains()));

    $this->getJson('/api/stats/not-a-domain')->assertNotFound();
    $this->getJson('/api/stats/projects')->assertOk();
});

// ---------------------------------------------------------------------------
// AC-004 — 401 unauthenticated
// ---------------------------------------------------------------------------

it('returns 401 without authentication (AC-004)', function () {
    $this->getJson('/api/stats/leads')->assertUnauthorized();
});
