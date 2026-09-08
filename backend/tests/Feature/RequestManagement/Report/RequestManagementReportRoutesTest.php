<?php

use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// AC-024: the three report routes must resolve to
// RequestManagementReportController, never be swallowed by the
// `request-management/{quote}` wildcard, and must be registered BEFORE it.
// AC-036 (rev-2): `report/categories` must resolve to its OWN action, never
// to `show` with `exportRun = "categories"`.

uses(RefreshDatabase::class);

it('resolves the four report routes to their own controller, declared before the {quote} wildcard', function () {
    $routes = collect(Route::getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/request-management'))
        ->values();

    $reportIndex = $routes->search(fn ($route) => $route->uri() === 'api/request-management/report');
    $categoriesIndex = $routes->search(fn ($route) => $route->uri() === 'api/request-management/report/categories');
    $showIndex = $routes->search(fn ($route) => $route->uri() === 'api/request-management/report/{exportRun}');
    $downloadIndex = $routes->search(fn ($route) => $route->uri() === 'api/request-management/report/{exportRun}/download');
    $quoteShowIndex = $routes->search(fn ($route) => $route->uri() === 'api/request-management/{quote}' && in_array('GET', $route->methods(), true));

    expect($reportIndex)->not->toBeFalse()
        ->and($categoriesIndex)->not->toBeFalse()
        ->and($showIndex)->not->toBeFalse()
        ->and($downloadIndex)->not->toBeFalse()
        ->and($quoteShowIndex)->not->toBeFalse()
        ->and($categoriesIndex)->toBeLessThan($showIndex) // AC-036: declared BEFORE {exportRun}
        ->and($reportIndex)->toBeLessThan($quoteShowIndex)
        ->and($categoriesIndex)->toBeLessThan($quoteShowIndex)
        ->and($showIndex)->toBeLessThan($quoteShowIndex)
        ->and($downloadIndex)->toBeLessThan($quoteShowIndex);

    expect($routes[$reportIndex]->getActionName())->toContain('RequestManagementReportController')
        ->and($routes[$categoriesIndex]->getActionName())->toContain('RequestManagementReportController@categories')
        ->and($routes[$showIndex]->getActionName())->toContain('RequestManagementReportController@show')
        ->and($routes[$downloadIndex]->getActionName())->toContain('RequestManagementReportController@download');
});

it('constrains {exportRun} to numbers on show/download, so a non-numeric segment 404s instead of binding (AC-036)', function () {
    $routes = collect(Route::getRoutes())
        ->filter(fn ($route) => in_array($route->uri(), ['api/request-management/report/{exportRun}', 'api/request-management/report/{exportRun}/download'], true));

    expect($routes)->toHaveCount(2);

    foreach ($routes as $route) {
        expect($route->wheres['exportRun'] ?? null)->not->toBeNull();
    }
});

it('GET report/categories actually resolves to the categories list, never to show(exportRun="categories") (AC-036)', function () {
    // The endpoint resolves all six report branches (ReportBranchResolver),
    // so the config-bound category tree must exist for it to answer at all.
    $formazione = ProductCategory::factory()->create(['name' => 'Formazione']);
    ProductCategory::factory()->childOf($formazione)->create(['name' => 'GOL']);
    ProductCategory::factory()->childOf($formazione)->create(['name' => 'Autoimpiego']);
    ProductCategory::factory()->childOf($formazione)->create(['name' => 'Yisu']);
    ProductCategory::factory()->childOf($formazione)->create(['name' => 'Autofinanziato']);
    ProductCategory::factory()->create(['name' => 'Consulenza']);
    ProductCategory::factory()->create(['name' => 'APL']);

    Permission::findOrCreate('request-management.report');
    $actor = User::factory()->create();
    $actor->givePermissionTo('request-management.report');
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/request-management/report/categories')->assertOk();

    $response->assertJsonStructure(['data' => ['categories']])
        ->assertJsonMissingPath('data.export_run');
});

it('never applies throttle middleware to the three report routes (AC-025)', function () {
    $routes = collect(Route::getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/request-management/report'));

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        $throttled = collect($route->gatherMiddleware())->contains(fn (string $middleware) => str_starts_with($middleware, 'throttle'));
        expect($throttled)->toBeFalse();
    }
});
