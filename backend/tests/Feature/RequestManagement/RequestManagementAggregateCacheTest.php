<?php

use App\Enums\ExportFormat;
use App\Enums\RequestManagementReportRowMode;
use App\Enums\WorkflowStatusGroup;
use App\Models\BusinessFunction;
use App\Models\EmploymentProfile;
use App\Models\Note;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\RequestManagement\RequestModule;
use App\Services\RequestManagement\Report\ReportBranchResolver;
use App\Services\RequestManagement\Report\RequestManagementReportGenerator;
use App\Services\RequestManagement\RequestManagementScope;
use Illuminate\Cache\ArrayStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0178, M4: category tabs and report dashboard/pickers behind
// AggregateCache, keyed per visibility scope (D-5), exact for the writer (D-4).

uses(RefreshDatabase::class);

const AGG_TABS_URL = '/api/request-management/product-categories';

function aggActor(array $abilities, string $module = 'request-management'): User
{
    foreach (['viewAny', 'view', 'viewAll', 'viewSite', 'update', 'report'] as $ability) {
        Permission::findOrCreate("{$module}.{$ability}");
    }

    $user = User::factory()->create();
    foreach ($abilities as $ability) {
        $user->givePermissionTo("{$module}.{$ability}");
    }

    return $user;
}

function aggCategory(string $name): ProductCategory
{
    return ProductCategory::factory()->childOf(ProductCategory::query()->firstOrCreate(['name' => 'Formazione'], ProductCategory::factory()->raw(['name' => 'Formazione'])))->reportable()->create([
        'name' => $name,
        'business_function_id' => BusinessFunction::factory()->create()->id,
    ]);
}

function aggQuote(ProductCategory $category, ?User $operator = null): Quote
{
    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => $category->business_function_id,
        'product_category_id' => $category->id,
    ]);
    if ($operator !== null) {
        $opportunity->managers()->sync([$operator->id => ['position' => 2]]);
    }

    return Quote::factory()->for($opportunity)->create(['operator_id' => $operator?->id]);
}

/** @return array<string, int> name => requests_count */
function aggTabs(string $url = AGG_TABS_URL): array
{
    return collect(test()->getJson($url)->assertOk()->json('data.categories'))->pluck('requests_count', 'name')->all();
}

function aggCountQueries(Closure $call): int
{
    $count = 0;
    DB::listen(function ($query) use (&$count): void {
        if (str_contains($query->sql, 'count(distinct')) {
            $count++;
        }
    });
    $call();

    return $count;
}

beforeEach(fn () => Cache::flush());

it('AC-018 an operator changing the category of their request sees the new tab counts at once', function () {
    $actor = aggActor(['viewAny', 'view', 'update']);
    $from = aggCategory('Alpha');
    $to = aggCategory('Beta');
    $quote = aggQuote($from, $actor);
    Sanctum::actingAs($actor);

    expect(aggTabs())->toBe(['Alpha' => 1]);

    $this->travel(2)->seconds();
    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'product_categories',
        'value' => [['business_function_id' => (int) $to->business_function_id, 'product_category_id' => $to->id]],
    ])->assertOk();

    expect(aggTabs())->toBe(['Beta' => 1]);
});

it('AC-019 two non-viewAll operators get their own counts, never each other\'s', function () {
    $ada = aggActor(['viewAny']);
    $zoe = aggActor(['viewAny']);
    $alpha = aggCategory('Alpha');
    $beta = aggCategory('Beta');
    aggQuote($alpha, $ada);
    aggQuote($alpha, $ada);
    aggQuote($beta, $zoe);

    Sanctum::actingAs($ada);
    expect(aggTabs())->toBe(['Alpha' => 2]);

    $this->app['auth']->forgetGuards();
    Sanctum::actingAs($zoe);
    expect(aggTabs())->toBe(['Beta' => 1]);
});

it('AC-019 two viewAll actors share one computation within the fresh window', function () {
    $first = aggActor(['viewAny', 'viewAll']);
    $second = aggActor(['viewAny', 'viewAll']);
    aggQuote(aggCategory('Alpha'));

    Sanctum::actingAs($first);
    aggTabs();

    $this->app['auth']->forgetGuards();
    Sanctum::actingAs($second);
    $queries = aggCountQueries(fn () => expect(aggTabs())->toBe(['Alpha' => 1]));

    expect($queries)->toBe(0);
});

it('AC-019 the fingerprint separates modules, viewAll, operators and their sites', function () {
    $viewAll = aggActor(['viewAny', 'viewAll']);
    $operator = aggActor(['viewAny']);

    expect(RequestManagementScope::scopeFingerprint($viewAll, RequestModule::Requests))->toBe('request-management:all')
        ->and(RequestManagementScope::scopeFingerprint($operator, RequestModule::Requests))->toBe("request-management:op:{$operator->id}:sites:")
        ->and(RequestManagementScope::scopeFingerprint(null, RequestModule::Requests))->toBe('request-management:none');

    $enrolleeAll = aggActor(['viewAny', 'viewAll'], 'enrollee-management');
    expect(RequestManagementScope::scopeFingerprint($enrolleeAll, RequestModule::Enrollees))->toBe('enrollee-management:all');

    // Many Sedi still fit the store's 255-char key, and the same set maps to the same scope.
    $sites = OperationalSite::factory()->count(200)->create();
    $manySites = aggActor(['viewAny', 'viewSite']);
    EmploymentProfile::factory()->remoteSites(...$sites)->create(['user_id' => $manySites->id]);
    $sameSites = aggActor(['viewAny', 'viewSite']);
    EmploymentProfile::factory()->remoteSites(...$sites)->create(['user_id' => $sameSites->id]);
    $otherSite = aggActor(['viewAny', 'viewSite']);
    EmploymentProfile::factory()->remoteSites(OperationalSite::factory()->create())->create(['user_id' => $otherSite->id]);

    $fingerprint = RequestManagementScope::scopeFingerprint($manySites, RequestModule::Requests);
    $siteHash = fn (User $user): string => (string) strstr(RequestManagementScope::scopeFingerprint($user, RequestModule::Requests), ':sites:');

    expect(strlen($fingerprint))->toBeLessThan(100)
        ->and($siteHash($sameSites))->toBe($siteHash($manySites))
        ->and($siteHash($otherSite))->not->toBe($siteHash($manySites));
});

it('AC-019 Richieste and Iscritti tabs are cached under distinct keys', function () {
    $actor = aggActor(['viewAny', 'viewAll']);
    aggActor(['viewAny', 'viewAll'], 'enrollee-management');
    $actor->givePermissionTo(['enrollee-management.viewAny', 'enrollee-management.viewAll']);
    aggQuote(aggCategory('Alpha'));
    Sanctum::actingAs($actor);

    // Iscritti only lists validated/closed_won rows: it must NOT reuse the Richieste counts.
    expect(aggTabs())->toBe(['Alpha' => 1])
        ->and(aggTabs('/api/enrollee-management/product-categories'))->toBe([])
        ->and(Cache::has('aggregates:rm-tabs:request-management:all'))->toBeTrue()
        ->and(Cache::has('aggregates:rm-tabs:enrollee-management:all'))->toBeTrue();
});

/**
 * Swaps the default cache store for one whose reads and writes throw, after
 * loading $actor's permissions (spatie keeps them in that same store).
 */
function aggUseThrowingStore(User $actor): ArrayObject
{
    $actor->can('request-management.viewAll');
    $attempts = new ArrayObject(['reads' => 0]);
    Cache::extend('throwing', fn () => Cache::repository(new class($attempts) extends ArrayStore
    {
        public function __construct(private ArrayObject $attempts)
        {
            parent::__construct();
        }

        public function get($key)
        {
            $this->attempts['reads']++;

            throw new RuntimeException('store down');
        }

        public function put($key, $value, $seconds)
        {
            throw new RuntimeException('store down');
        }
    }));
    config(['cache.stores.throwing' => ['driver' => 'throwing'], 'cache.default' => 'throwing']);
    Cache::forgetDriver('array');

    return $attempts;
}

it('AC-029 tabs answer 200 with the same payload when the cache store throws', function () {
    $actor = aggActor(['viewAny', 'viewAll']);
    aggQuote(aggCategory('Alpha'));
    Sanctum::actingAs($actor);
    $expected = $this->getJson(AGG_TABS_URL)->assertOk()->json();
    Cache::flush();

    $attempts = aggUseThrowingStore($actor);

    $this->getJson(AGG_TABS_URL)->assertOk()->assertExactJson($expected);
    expect($attempts['reads'])->toBeGreaterThan(0);
});

it('AC-029 dashboard and report pickers answer 200 with the same payload when the cache store throws', function (string $path) {
    $actor = aggActor(['report', 'viewAll']);
    aggAdvancedQuote(aggCategory('GOL'));
    Sanctum::actingAs($actor);
    $url = $path === 'dashboard' ? aggDashboardUrl() : "/api/request-management/report/{$path}";
    $expected = $this->getJson($url)->assertOk()->json();
    Cache::flush();

    $attempts = aggUseThrowingStore($actor);

    $this->getJson($url)->assertOk()->assertExactJson($expected);
    expect($attempts['reads'])->toBeGreaterThan(0);
})->with(['dashboard', 'categories', 'operators', 'sites']);

function aggDashboardUrl(array $overrides = []): string
{
    return '/api/request-management/report/dashboard?'.http_build_query(array_merge([
        'date_from' => '2020-01-01',
        'date_to' => '2099-12-31',
        'category_keys' => app(ReportBranchResolver::class)->keys(),
        'row_mode' => 'all',
    ], $overrides));
}

function aggAdvancedQuote(ProductCategory $category): Quote
{
    $status = QuoteWorkflowStatus::factory()->global()->create(['system_key' => null, 'group' => WorkflowStatusGroup::Open]);
    $quote = aggQuote($category);
    $operator = User::factory()->create();
    $quote->forceFill(['quote_workflow_status_id' => $status->id, 'operator_id' => $operator->id])->save();
    Note::factory()->create(['notable_type' => 'opportunity', 'notable_id' => $quote->opportunity_id, 'created_at' => now(), 'user_id' => $operator->id])
        ->forceFill(['quote_id' => $quote->id])->save();

    return $quote;
}

it('AC-020 dashboard: same parameters compute once, different ones use another key', function () {
    $gol = aggCategory('GOL');
    aggAdvancedQuote($gol);
    aggAdvancedQuote(aggCategory('Consulenza'));
    Sanctum::actingAs(aggActor(['report', 'viewAll']));
    $keys = app(ReportBranchResolver::class)->keys();

    $first = $this->getJson(aggDashboardUrl())->assertOk()->json();
    $entries = fn () => collect((fn () => $this->storage)->call(Cache::getStore()))->keys()->filter(fn ($k) => str_contains($k, 'aggregates:rm-dash:'))->count();
    expect($entries())->toBe(1);

    expect($this->getJson(aggDashboardUrl())->assertOk()->json())->toBe($first)
        ->and($entries())->toBe(1);

    $narrow = $this->getJson(aggDashboardUrl(['category_keys' => [$keys[0]], 'row_mode' => 'all']))->assertOk()->json();
    expect($entries())->toBe(2)
        ->and($narrow['data']['applied']['category_keys'])->toBe([$keys[0]]);

    // Key order is normalised: the same set in another order is the same entry.
    $this->getJson(aggDashboardUrl(['category_keys' => array_reverse($keys)]))->assertOk();
    expect($entries())->toBe(2);
});

it('AC-020 the CSV is generated live while the dashboard is served from cache', function () {
    Storage::fake('local');
    $actor = aggActor(['report', 'viewAll']);
    aggAdvancedQuote(aggCategory('GOL'));
    Sanctum::actingAs($actor);
    $keys = app(ReportBranchResolver::class)->keys();
    $csv = function (string $name) use ($actor, $keys): string {
        app(RequestManagementReportGenerator::class)->generate($actor, now()->subDay()->toDateString(), now()->addDay()->toDateString(), $keys, RequestManagementReportRowMode::All, ExportFormat::Csv, Storage::disk('local')->path($name));

        return Storage::disk('local')->get($name);
    };

    $dashboardBefore = $this->getJson(aggDashboardUrl())->assertOk()->json();
    $csvBefore = $csv('before.csv');

    aggAdvancedQuote(ProductCategory::query()->where('name', 'GOL')->firstOrFail());

    expect($this->getJson(aggDashboardUrl())->json())->toBe($dashboardBefore)
        ->and($csv('after.csv'))->not->toBe($csvBefore);
});

it('AC-021 an actor without the report permission gets 403 even when the key is already cached', function () {
    aggAdvancedQuote(aggCategory('GOL'));
    $allowed = aggActor(['report', 'viewAll']);
    $denied = aggActor(['viewAny', 'viewAll']);

    Sanctum::actingAs($allowed);
    foreach ([aggDashboardUrl(), '/api/request-management/report/categories', '/api/request-management/report/operators', '/api/request-management/report/sites'] as $url) {
        $this->getJson($url)->assertOk();
    }

    $this->app['auth']->forgetGuards();
    Sanctum::actingAs($denied);
    foreach ([aggDashboardUrl(), '/api/request-management/report/categories', '/api/request-management/report/operators', '/api/request-management/report/sites'] as $url) {
        $this->getJson($url)->assertForbidden();
    }
});

it('AC-020 report pickers are cached per scope and served from cache on the second read', function () {
    aggAdvancedQuote(aggCategory('GOL'));
    Sanctum::actingAs(aggActor(['report', 'viewAll']));

    $first = $this->getJson('/api/request-management/report/categories')->assertOk()->json();
    $queries = aggCountQueries(fn () => $this->getJson('/api/request-management/report/categories')->assertOk()->assertExactJson($first));

    expect($first['data']['categories'])->not->toBeEmpty()
        ->and(Cache::has('aggregates:rm-report-categories:request-management:all:'.app()->getLocale()))->toBeTrue()
        ->and($queries)->toBe(0);
});
