<?php

use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// GET /api/request-management/report/dashboard (spec 0107 data_contract,
// AC-001/002/003/013/014).

uses(RefreshDatabase::class);

if (! function_exists('dashboardActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function dashboardActorWith(array $abilities): User
    {
        foreach (['report', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('dashboardCategoryTree')) {
    function dashboardCategoryTree(): void
    {
        $formazione = ProductCategory::factory()->create(['name' => 'Formazione']);
        ProductCategory::factory()->childOf($formazione)->create(['name' => 'GOL']);
        ProductCategory::factory()->childOf($formazione)->create(['name' => 'Autoimpiego']);
        ProductCategory::factory()->childOf($formazione)->create(['name' => 'Yisu']);
        ProductCategory::factory()->childOf($formazione)->create(['name' => 'Autofinanziato']);
        ProductCategory::factory()->create(['name' => 'Consulenza']);
        ProductCategory::factory()->create(['name' => 'APL']);
    }
}

if (! function_exists('dashboardQuery')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function dashboardQuery(array $overrides = []): array
    {
        return array_merge([
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
            'category_keys' => array_keys((array) config('request-management-report.branches')),
            'row_mode' => 'all',
        ], $overrides);
    }
}

// ---------------------------------------------------------------------------
// AC-001
// ---------------------------------------------------------------------------

it('200s with applied/summary/categories for an authorized actor (AC-001)', function () {
    dashboardCategoryTree();
    $actor = dashboardActorWith(['report', 'viewAll']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/request-management/report/dashboard?'.http_build_query(dashboardQuery()))
        ->assertOk();

    $response->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'applied',
                'summary' => [['key', 'label', 'value']],
                'categories' => [['key', 'label', 'summary' => [['key', 'label', 'value']], 'charts']],
            ],
        ]);
});

it('403s without request-management.report (AC-001)', function () {
    dashboardCategoryTree();
    $actor = dashboardActorWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/request-management/report/dashboard?'.http_build_query(dashboardQuery()))
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-002
// ---------------------------------------------------------------------------

it('422s when date_from is missing (AC-002)', function () {
    dashboardCategoryTree();
    $actor = dashboardActorWith(['report']);
    Sanctum::actingAs($actor);

    $query = dashboardQuery();
    unset($query['date_from']);

    $this->getJson('/api/request-management/report/dashboard?'.http_build_query($query))
        ->assertStatus(422)
        ->assertJsonValidationErrors('date_from');
});

it('422s when date_to is before date_from (AC-002)', function () {
    dashboardCategoryTree();
    $actor = dashboardActorWith(['report']);
    Sanctum::actingAs($actor);

    $query = dashboardQuery(['date_from' => '2026-09-30', 'date_to' => '2026-09-01']);

    $this->getJson('/api/request-management/report/dashboard?'.http_build_query($query))
        ->assertStatus(422)
        ->assertJsonValidationErrors('date_to');
});

it('422s when category_keys is empty (AC-002)', function () {
    dashboardCategoryTree();
    $actor = dashboardActorWith(['report']);
    Sanctum::actingAs($actor);

    $query = dashboardQuery(['category_keys' => []]);

    $this->getJson('/api/request-management/report/dashboard?'.http_build_query($query))
        ->assertStatus(422)
        ->assertJsonValidationErrors('category_keys');
});

it('422s when category_keys has an unknown key, never reaching a query (AC-002)', function () {
    dashboardCategoryTree();
    $actor = dashboardActorWith(['report']);
    Sanctum::actingAs($actor);

    $query = dashboardQuery(['category_keys' => ['gol', 'not-a-real-branch']]);

    $this->getJson('/api/request-management/report/dashboard?'.http_build_query($query))
        ->assertStatus(422)
        ->assertJsonValidationErrors('category_keys.1');
});

it('422s when row_mode is not one of the three values (AC-002)', function () {
    dashboardCategoryTree();
    $actor = dashboardActorWith(['report']);
    Sanctum::actingAs($actor);

    $query = dashboardQuery(['row_mode' => 'bogus']);

    $this->getJson('/api/request-management/report/dashboard?'.http_build_query($query))
        ->assertStatus(422)
        ->assertJsonValidationErrors('row_mode');
});

// ---------------------------------------------------------------------------
// AC-003 — applied echoes the received filters exactly
// ---------------------------------------------------------------------------

it('applied reflects exactly the filters received (AC-003)', function () {
    dashboardCategoryTree();
    $actor = dashboardActorWith(['report', 'viewAll']);
    Sanctum::actingAs($actor);

    $query = dashboardQuery(['category_keys' => ['gol', 'consulenza'], 'row_mode' => 'total_only']);

    $response = $this->getJson('/api/request-management/report/dashboard?'.http_build_query($query))->assertOk();

    // `operator_keys` joined the echo in spec 0108 and `site_keys` in spec
    // 0112 (data_contract): a null says "every operator"/"every Sede", the
    // meaning an ABSENT selection carries (0108 D-2, 0112 D-4).
    $response->assertJsonPath('data.applied', [
        'date_from' => '2026-09-01',
        'date_to' => '2026-09-30',
        'category_keys' => ['gol', 'consulenza'],
        'row_mode' => 'total_only',
        'operator_keys' => null,
        'site_keys' => null,
    ]);
});

// ---------------------------------------------------------------------------
// AC-013 — routing: dashboard must not be swallowed by report/{exportRun}
// ---------------------------------------------------------------------------

it('resolves to its own controller, declared before report/{exportRun} (AC-013)', function () {
    $routes = collect(Route::getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/request-management'))
        ->values();

    $dashboardIndex = $routes->search(fn ($route) => $route->uri() === 'api/request-management/report/dashboard');
    $showIndex = $routes->search(fn ($route) => $route->uri() === 'api/request-management/report/{exportRun}');

    expect($dashboardIndex)->not->toBeFalse()
        ->and($showIndex)->not->toBeFalse()
        ->and($dashboardIndex)->toBeLessThan($showIndex);

    expect($routes[$dashboardIndex]->getActionName())->toContain('RequestManagementDashboardController');
});

it('a real HTTP round-trip actually resolves the dashboard, never show(exportRun="dashboard") (AC-013)', function () {
    dashboardCategoryTree();
    $actor = dashboardActorWith(['report', 'viewAll']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/request-management/report/dashboard?'.http_build_query(dashboardQuery()))
        ->assertOk();

    $response->assertJsonStructure(['data' => ['summary', 'categories']])
        ->assertJsonMissingPath('data.export_run');
});

// ---------------------------------------------------------------------------
// AC-014 — no throttle
// ---------------------------------------------------------------------------

it('never applies throttle middleware to the dashboard route (AC-014)', function () {
    $route = collect(Route::getRoutes())->first(fn ($route) => $route->uri() === 'api/request-management/report/dashboard');

    expect($route)->not->toBeNull();

    $throttled = collect($route->gatherMiddleware())->contains(fn (string $middleware) => str_starts_with($middleware, 'throttle'));
    expect($throttled)->toBeFalse();
});
