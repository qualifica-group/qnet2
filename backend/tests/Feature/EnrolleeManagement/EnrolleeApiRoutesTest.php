<?php

use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

// Spec 0130, D-8/AC-018 — routes/api/request-management.php registers every
// literal segment PER RequestModule case, except `store`/`form-context`:
// those exist ONLY for a module whose allowsCreate() is true (RequestModule::
// Requests). A route that was never registered answers 404, or 405 when the
// SAME uri pattern is registered for another HTTP verb (spec 0130 accepts
// both, "404/405") — either way this holds for every actor, including
// super-admin (Gate::before never gets a chance to fire on an unmatched
// route).

uses(RefreshDatabase::class);

if (! function_exists('routesSuperAdmin')) {
    function routesSuperAdmin(): User
    {
        Role::findOrCreate('super-admin');
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        return $user;
    }
}

if (! function_exists('routesRegistered')) {
    /**
     * Whether $method+$uri resolves to a REGISTERED route — the router
     * facade itself, not an HTTP status code (a 404 from the endpoint's own
     * `findOrFail()`, e.g. an unknown report run id, is indistinguishable
     * from an unregistered route at the HTTP layer).
     */
    function routesRegistered(string $method, string $uri): bool
    {
        foreach (Route::getRoutes() as $route) {
            if ($route->matches(Request::create($uri, $method), false) && in_array($method, $route->methods(), true)) {
                return true;
            }
        }

        return false;
    }
}

// ---------------------------------------------------------------------------
// AC-018 — no creation surface under enrollee-management, not even for super-admin
// ---------------------------------------------------------------------------

it('POST /api/enrollee-management is not a registered route, even for super-admin', function () {
    Sanctum::actingAs(routesSuperAdmin());

    expect($this->postJson('/api/enrollee-management', [])->status())->toBeIn([404, 405]);
});

it('POST /api/enrollee-management/form-context is not a registered route, even for super-admin', function () {
    Sanctum::actingAs(routesSuperAdmin());

    expect($this->postJson('/api/enrollee-management/form-context', [])->status())->toBeIn([404, 405]);
});

it('neither POST is a route the router actually matches (router-level, unambiguous)', function () {
    expect(routesRegistered('POST', '/api/enrollee-management'))->toBeFalse()
        ->and(routesRegistered('POST', '/api/enrollee-management/form-context'))->toBeFalse();
});

it('the request-management creation routes stay registered and unaffected', function () {
    expect(routesRegistered('POST', '/api/request-management'))->toBeTrue()
        ->and(routesRegistered('POST', '/api/request-management/form-context'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Every other literal segment IS registered for enrollee-management
// ---------------------------------------------------------------------------

it('every non-creation enrollee-management route is registered at the router level', function () {
    expect(routesRegistered('GET', '/api/enrollee-management/1'))->toBeTrue()
        ->and(routesRegistered('PUT', '/api/enrollee-management/1'))->toBeTrue()
        ->and(routesRegistered('DELETE', '/api/enrollee-management/1'))->toBeTrue()
        ->and(routesRegistered('GET', '/api/enrollee-management/product-categories'))->toBeTrue()
        ->and(routesRegistered('POST', '/api/enrollee-management/assign-operators'))->toBeTrue()
        ->and(routesRegistered('POST', '/api/enrollee-management/assign-manager-ga1'))->toBeTrue()
        ->and(routesRegistered('POST', '/api/enrollee-management/transfer'))->toBeTrue()
        ->and(routesRegistered('POST', '/api/enrollee-management/report'))->toBeTrue()
        ->and(routesRegistered('GET', '/api/enrollee-management/report/categories'))->toBeTrue()
        ->and(routesRegistered('GET', '/api/enrollee-management/report/dashboard'))->toBeTrue()
        ->and(routesRegistered('GET', '/api/enrollee-management/report/operators'))->toBeTrue()
        ->and(routesRegistered('GET', '/api/enrollee-management/report/sites'))->toBeTrue()
        ->and(routesRegistered('GET', '/api/enrollee-management/report/1'))->toBeTrue()
        ->and(routesRegistered('GET', '/api/enrollee-management/report/1/download'))->toBeTrue();
});

it('DELETE /api/enrollee-management/{quote} actually reaches the controller (super-admin bypass, end-to-end)', function () {
    // D-2's status filter is a plain abort(403) inside
    // RequestManagementScope::isInStatusScope() — NOT a Gate/Policy check —
    // so even super-admin's Gate::before bypass does not exempt it: the
    // quote must genuinely sit in validated/closed_won (spec 0130 D-2).
    $status = QuoteWorkflowStatus::query()->whereNull('quote_workflow_id')->where('system_key', 'closed_won')->value('id')
        ?? QuoteWorkflowStatus::factory()->global()->system('closed_won')->create()->id;
    $quote = Quote::factory()->create(['quote_workflow_status_id' => $status]);
    Sanctum::actingAs(routesSuperAdmin());

    $this->deleteJson("/api/enrollee-management/{$quote->id}")->assertNoContent();

    expect(Quote::query()->whereKey($quote->id)->exists())->toBeFalse();
});
