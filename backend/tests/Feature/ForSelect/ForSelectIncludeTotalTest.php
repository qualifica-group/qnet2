<?php

use App\Models\Campaign;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Project;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Spec 0178, D-7 (AC-026, AC-027): `include_total=0` on the operational
 * for-select endpoints skips the COUNT, reads limit + 1 rows and answers
 * `pagination.has_more`; without it the response is the historical one.
 */
uses(RefreshDatabase::class);

/**
 * Each entry seeds at least three rows of the resource and returns the
 * endpoint URL (with a query string already started).
 *
 * @return array<string, Closure(): string>
 */
dataset('for-select resources', [
    'quotes' => [function (): string {
        Quote::factory()->count(3)->create();

        return '/api/quotes/for-select?';
    }],
    'opportunities' => [function (): string {
        Opportunity::factory()->count(3)->create();

        return '/api/opportunities/for-select?';
    }],
    'registries' => [function (): string {
        Registry::factory()->count(3)->create();

        return '/api/registries/for-select?';
    }],
    'leads' => [function (): string {
        Lead::factory()->count(3)->create();

        return '/api/leads/for-select?';
    }],
    'tasks' => [function (): string {
        Task::factory()->count(3)->create();

        return '/api/tasks/for-select?';
    }],
    'work-orders' => [function (): string {
        WorkOrder::factory()->count(3)->create();

        return '/api/work-orders/for-select?';
    }],
    'projects' => [function (): string {
        Project::factory()->count(3)->create();

        return '/api/projects/for-select?';
    }],
    'campaigns' => [function (): string {
        Campaign::factory()->count(3)->create();

        return '/api/campaigns/for-select?';
    }],
    'companies' => [function (): string {
        Company::factory()->count(3)->create();

        return '/api/companies/for-select?';
    }],
    'company-sites' => [function (): string {
        CompanySite::factory()->count(3)->create();

        return '/api/company-sites/for-select?';
    }],
    'referents' => [function (): string {
        Referent::factory()->count(3)->create();

        return '/api/referents/for-select?';
    }],
    'products' => [function (): string {
        Product::factory()->count(3)->create();

        return '/api/products/for-select?';
    }],
    'quote-offer-lines' => [function (): string {
        $quote = Quote::factory()->create();
        QuoteLine::factory()->count(3)->create(['quote_id' => $quote->id]);

        return "/api/quote-offer-lines/for-select?quote_id={$quote->id}&";
    }],
    'users' => [function (): string {
        User::factory()->count(3)->create();

        return '/api/users/for-select?';
    }],
    'mentionable users' => [function (): string {
        foreach (['request-management.view', 'request-management.viewAll'] as $permission) {
            Permission::findOrCreate($permission);
        }
        // Only holders of viewAll (or super-admins) are mentionable on any record (D-10).
        User::factory()->count(3)->create()->each->givePermissionTo(['request-management.view', 'request-management.viewAll']);
        $opportunity = Opportunity::factory()->create();

        return "/api/notes/mentionable-users?entity_type=request-management&entity_id={$opportunity->id}&";
    }],
]);

/**
 * Run the request and report the SQL statements it executed.
 *
 * @return array{0: TestResponse, 1: array<int, string>}
 */
function forSelectWithQueryLog(object $test, string $url): array
{
    $statements = [];
    DB::listen(function ($query) use (&$statements): void {
        $statements[] = $query->sql;
    });

    return [$test->getJson($url), $statements];
}

/**
 * The COUNT statements of the resource's own pagination. The `exists:` rule
 * of a validation (`where "id" = ?`) is a primary-key probe, not a count of
 * the option list, so it is not what AC-026 forbids.
 *
 * @param  array<int, string>  $statements
 * @return array<int, string>
 */
function forSelectCountStatements(array $statements): array
{
    return array_values(array_filter($statements, fn (string $sql): bool => str_contains(strtolower($sql), 'count(*)')
        && ! str_contains($sql, 'where "id" = ?')));
}

function forSelectActor(): User
{
    Role::findOrCreate('super-admin', 'web');
    $actor = User::factory()->create();
    $actor->assignRole('super-admin');
    Sanctum::actingAs($actor);

    return $actor;
}

it('AC-026: include_total=0 skips the count, pages by has_more and returns null totals', function (Closure $seed) {
    $url = $seed();
    forSelectActor();

    $total = $this->getJson($url.'limit=100')->assertOk()->json('pagination.total');
    expect($total)->toBeGreaterThanOrEqual(3);

    [$first, $statements] = forSelectWithQueryLog($this, $url.'include_total=0&limit=2&offset=0');

    $first->assertOk();
    expect($first->json('items'))->toHaveCount(2)
        ->and($first->json('pagination.has_more'))->toBeTrue()
        ->and($first->json('pagination.total'))->toBeNull()
        ->and($first->json('pagination.total_pages'))->toBeNull()
        ->and($first->json('pagination.offset'))->toBe(0)
        ->and($first->json('pagination.limit'))->toBe(2)
        ->and(forSelectCountStatements($statements))->toBe([]);

    $last = $this->getJson($url.'include_total=0&limit=2&offset='.($total - 1))->assertOk();

    expect($last->json('items'))->toHaveCount(1)
        ->and($last->json('pagination.has_more'))->toBeFalse()
        ->and($last->json('pagination.total'))->toBeNull();
})->with('for-select resources');

it('AC-027: without include_total the response is unchanged (integer total, no has_more)', function (Closure $seed) {
    $url = $seed();
    forSelectActor();

    foreach ([$url.'limit=2', $url.'limit=2&include_total=1'] as $request) {
        $response = $this->getJson($request)->assertOk();

        expect($response->json('pagination.total'))->toBeInt()
            ->and($response->json('pagination.total_pages'))->toBeInt()
            ->and($response->json('items'))->toHaveCount(2)
            ->and($response->json('pagination'))->not->toHaveKey('has_more');
    }
})->with('for-select resources');

it('AC-027: a non-boolean include_total is rejected with 422', function (Closure $seed) {
    $url = $seed();
    forSelectActor();

    $this->getJson($url.'include_total=x')->assertStatus(422)->assertJsonValidationErrors('include_total');
})->with('for-select resources');

it('hydrated ids never influence has_more', function () {
    $held = Registry::factory()->create(['name' => 'ZZZ held']);
    Registry::factory()->count(2)->create(['name' => 'Alpha']);
    forSelectActor();

    $response = $this->getJson("/api/registries/for-select?include_total=0&limit=10&search=Alpha&ids[]={$held->id}")->assertOk();

    expect($response->json('items'))->toHaveCount(3)
        ->and($response->json('pagination.has_more'))->toBeFalse();
});
