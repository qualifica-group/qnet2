<?php

use App\Enums\RequestManagementDashboardChartScope;
use App\Enums\RequestManagementReportRowMode;
use App\Enums\WorkflowStatusGroup;
use App\Models\BusinessFunction;
use App\Models\Note;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\Services\RequestManagement\Report\Dashboard\RequestManagementDashboardBuilder;
use App\Services\RequestManagement\Report\Dashboard\RequestManagementDashboardResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;

// RequestManagementDashboardBuilder (spec 0107, rev-3): AC-004 (row_mode
// gates scope), AC-005/006 (points content), AC-007 (deterministic
// ordering), AC-008 (nothing is dropped for being 0), AC-009 (scope
// respected), AC-016/AC-017 (a section per category, every column emitted).

uses(RefreshDatabase::class);

if (! function_exists('dashboardCategoryTree2')) {
    /**
     * @return array<string, ProductCategory>
     */
    function dashboardCategoryTree2(): array
    {
        $formazione = ProductCategory::factory()->create(['name' => 'Formazione']);

        return [
            'gol' => ProductCategory::factory()->childOf($formazione)->create(['name' => 'GOL']),
            'autoimpiego' => ProductCategory::factory()->childOf($formazione)->create(['name' => 'Autoimpiego']),
            'yisu' => ProductCategory::factory()->childOf($formazione)->create(['name' => 'Yisu']),
            'autofinanziato' => ProductCategory::factory()->childOf($formazione)->create(['name' => 'Autofinanziato']),
            'consulenza' => ProductCategory::factory()->create(['name' => 'Consulenza']),
            'apl' => ProductCategory::factory()->create(['name' => 'APL']),
        ];
    }
}

if (! function_exists('dashboardQuote')) {
    function dashboardQuote(ProductCategory $category, ?int $operatorId = null, ?int $statusId = null): Quote
    {
        $opportunity = Opportunity::factory()->create();
        OpportunityProductLine::factory()->create([
            'opportunity_id' => $opportunity->id,
            'business_function_id' => BusinessFunction::factory()->create()->id,
            'product_category_id' => $category->id,
        ]);

        $attributes = $statusId !== null ? ['quote_workflow_status_id' => $statusId] : [];
        $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id, ...$attributes]);

        if ($operatorId !== null) {
            $quote->forceFill(['operator_id' => $operatorId])->save();
        }

        return $quote;
    }
}

if (! function_exists('dashboardQuoteAdvanced')) {
    function dashboardQuoteAdvanced(ProductCategory $category, ?int $operatorId = null): Quote
    {
        $custom = QuoteWorkflowStatus::factory()->global()->create(['system_key' => null, 'group' => WorkflowStatusGroup::Open]);

        return dashboardQuote($category, $operatorId, $custom->id);
    }
}

if (! function_exists('dashboardNote')) {
    function dashboardNote(Quote $quote, Carbon $createdAt): void
    {
        Note::factory()->create([
            'notable_type' => 'opportunity',
            'notable_id' => $quote->opportunity_id,
            'created_at' => $createdAt,
        ])->forceFill(['quote_id' => $quote->id])->save();
    }
}

if (! function_exists('dashboardViewAllActor')) {
    function dashboardViewAllActor(): User
    {
        Permission::findOrCreate('request-management.viewAll');
        $actor = User::factory()->create();
        $actor->givePermissionTo('request-management.viewAll');

        return $actor;
    }
}

if (! function_exists('buildDashboard')) {
    /**
     * @param  array<int, string>  $categoryKeys
     */
    function buildDashboard(?User $actor, string $dateFrom, string $dateTo, array $categoryKeys, RequestManagementReportRowMode $rowMode): RequestManagementDashboardResult
    {
        return app(RequestManagementDashboardBuilder::class)->build($actor, $dateFrom, $dateTo, $categoryKeys, $rowMode);
    }
}

if (! function_exists('dashboardIndicatorKeys')) {
    /**
     * @return array<int, string>
     */
    function dashboardIndicatorKeys(): array
    {
        return (array) config('request-management-report.indicator_columns');
    }
}

if (! function_exists('dashboardScopes')) {
    /**
     * Every chart scope of every section, deduplicated in encounter order.
     *
     * @return array<int, string>
     */
    function dashboardScopes(RequestManagementDashboardResult $result): array
    {
        $scopes = [];

        foreach ($result->categories as $category) {
            foreach ($category->charts as $chart) {
                $scopes[] = $chart->scope->value;
            }
        }

        return array_values(array_unique($scopes));
    }
}

// ---------------------------------------------------------------------------
// AC-016 — one section per selected category, in the report's branch order
// ---------------------------------------------------------------------------

it('emits one section per selected category, keyed and labelled like the report branch (AC-016)', function () {
    $categories = dashboardCategoryTree2();
    dashboardNote(dashboardQuoteAdvanced($categories['gol']), now());

    $actor = dashboardViewAllActor();
    $result = buildDashboard($actor, now()->subDay()->toDateString(), now()->addDay()->toDateString(), ['consulenza', 'gol'], RequestManagementReportRowMode::All);

    // Config order (gol before consulenza), not the order the caller passed.
    expect(array_map(fn ($c) => $c->key, $result->categories))->toBe(['gol', 'consulenza'])
        ->and(array_map(fn ($c) => $c->label, $result->categories))->toBe(['GOL', 'Consulenza']);
});

it('keeps a section for a category with no data at all, tiles and chart included (AC-008, AC-016)', function () {
    dashboardCategoryTree2();
    $actor = dashboardViewAllActor();

    $result = buildDashboard($actor, '2026-09-01', '2026-09-30', ['gol'], RequestManagementReportRowMode::All);

    $gol = collect($result->categories)->firstWhere('key', 'gol');
    $chart = collect($gol->charts)->firstWhere('scope', RequestManagementDashboardChartScope::Indicator);

    expect($gol)->not->toBeNull()
        ->and(array_map(fn ($item) => $item->value, $gol->summary))->toBe(array_fill(0, count(dashboardIndicatorKeys()), 0))
        ->and($chart)->not->toBeNull()
        ->and(array_map(fn ($p) => $p->value, $chart->points))->toBe(array_fill(0, count(dashboardIndicatorKeys()), 0));
});

// ---------------------------------------------------------------------------
// AC-017 — every indicator column is emitted, zeros included
// ---------------------------------------------------------------------------

it('emits every indicator column in the overall tiles and in each section, even the ones the branch never computes (AC-017)', function () {
    $categories = dashboardCategoryTree2();
    dashboardNote(dashboardQuoteAdvanced($categories['apl']), now());

    $actor = dashboardViewAllActor();
    $result = buildDashboard($actor, now()->subDay()->toDateString(), now()->addDay()->toDateString(), ['apl'], RequestManagementReportRowMode::TotalOnly);

    $apl = collect($result->categories)->firstWhere('key', 'apl');

    expect(array_map(fn ($item) => $item->key, $result->summary))->toBe(dashboardIndicatorKeys())
        ->and(array_map(fn ($item) => $item->key, $apl->summary))->toBe(dashboardIndicatorKeys());

    // `aule_gestione` is NOT in the APL branch's applicability list: it is
    // never computed, and still shows up as a 0 tile (rev-3 D-11) — exactly
    // the cell the CSV prints for it (spec 0106 D-15).
    expect(collect($apl->summary)->firstWhere('key', 'aule_gestione')->value)->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-004 — row_mode gates which scope(s) appear
// ---------------------------------------------------------------------------

it('total_only yields ONLY scope=indicator charts (AC-004)', function () {
    $categories = dashboardCategoryTree2();
    dashboardNote(dashboardQuoteAdvanced($categories['gol']), now());

    $actor = dashboardViewAllActor();
    $result = buildDashboard($actor, now()->subDay()->toDateString(), now()->addDay()->toDateString(), ['gol'], RequestManagementReportRowMode::TotalOnly);

    expect(dashboardScopes($result))->toBe(['indicator']);
});

it('operators_only yields ONLY scope=operator charts (AC-004)', function () {
    $categories = dashboardCategoryTree2();
    dashboardNote(dashboardQuoteAdvanced($categories['gol']), now());

    $actor = dashboardViewAllActor();
    $result = buildDashboard($actor, now()->subDay()->toDateString(), now()->addDay()->toDateString(), ['gol'], RequestManagementReportRowMode::OperatorsOnly);

    expect(dashboardScopes($result))->toBe(['operator']);
});

it('all yields both scopes, the indicator chart first inside its section (AC-004)', function () {
    $categories = dashboardCategoryTree2();
    dashboardNote(dashboardQuoteAdvanced($categories['gol']), now());

    $actor = dashboardViewAllActor();
    $result = buildDashboard($actor, now()->subDay()->toDateString(), now()->addDay()->toDateString(), ['gol'], RequestManagementReportRowMode::All);

    $gol = collect($result->categories)->firstWhere('key', 'gol');
    $scopes = array_map(fn ($c) => $c->scope->value, $gol->charts);

    expect($scopes)->toContain('indicator')
        ->and($scopes)->toContain('operator')
        ->and($scopes[0])->toBe('indicator')
        ->and(array_slice($scopes, 1))->toBe(array_fill(0, count($scopes) - 1, 'operator'));
});

// ---------------------------------------------------------------------------
// AC-005/AC-006 — points content
// ---------------------------------------------------------------------------

it('scope=indicator has one point per indicator column, in the report column order (AC-005)', function () {
    app()->setLocale('it');
    $categories = dashboardCategoryTree2();
    dashboardNote(dashboardQuoteAdvanced($categories['gol']), now());

    $actor = dashboardViewAllActor();
    $result = buildDashboard($actor, now()->subDay()->toDateString(), now()->addDay()->toDateString(), ['gol'], RequestManagementReportRowMode::TotalOnly);

    $chart = collect(collect($result->categories)->firstWhere('key', 'gol')->charts)->first();
    $expected = array_map(fn (string $key) => __("request-management-report.headers.{$key}"), dashboardIndicatorKeys());

    expect(array_map(fn ($p) => $p->label, $chart->points))->toBe($expected)
        ->and($chart->indicatorKey)->toBeNull();
});

it('scope=operator has one point per GA2, including Non assegnato with the CSV label (AC-006)', function () {
    app()->setLocale('it'); // the label is resolved from the SAME it/request-management-report.php catalogue as the CSV.
    $categories = dashboardCategoryTree2();
    $ada = User::factory()->create(['name' => 'Ada Rossi']);
    dashboardNote(dashboardQuoteAdvanced($categories['gol'], $ada->id), now());
    dashboardNote(dashboardQuoteAdvanced($categories['gol'], null), now());

    $actor = dashboardViewAllActor();
    $result = buildDashboard($actor, now()->subDay()->toDateString(), now()->addDay()->toDateString(), ['gol'], RequestManagementReportRowMode::OperatorsOnly);

    $gol = collect($result->categories)->firstWhere('key', 'gol');
    $telefonate = collect($gol->charts)->first(fn ($c) => $c->indicatorKey === 'telefonate');

    expect($telefonate)->not->toBeNull();
    expect(array_map(fn ($p) => $p->label, $telefonate->points))->toEqualCanonicalizing(['Ada Rossi', 'Non assegnato']);
});

// ---------------------------------------------------------------------------
// AC-007 — deterministic ordering
// ---------------------------------------------------------------------------

it('operator points are ordered by value desc then label asc, identically across repeated calls (AC-007)', function () {
    $categories = dashboardCategoryTree2();
    $zoe = User::factory()->create(['name' => 'Zoe Bianchi']);
    $ada = User::factory()->create(['name' => 'Ada Rossi']);
    $mia = User::factory()->create(['name' => 'Mia Verdi']);

    dashboardNote(dashboardQuoteAdvanced($categories['gol'], $zoe->id), now());
    dashboardNote(dashboardQuoteAdvanced($categories['gol'], $ada->id), now());
    dashboardNote(dashboardQuoteAdvanced($categories['gol'], $ada->id), now());
    dashboardNote(dashboardQuoteAdvanced($categories['gol'], $mia->id), now());
    dashboardNote(dashboardQuoteAdvanced($categories['gol'], $mia->id), now());

    $actor = dashboardViewAllActor();
    $range = [now()->subDay()->toDateString(), now()->addDay()->toDateString()];

    $first = buildDashboard($actor, ...$range, categoryKeys: ['gol'], rowMode: RequestManagementReportRowMode::OperatorsOnly);
    $second = buildDashboard($actor, ...$range, categoryKeys: ['gol'], rowMode: RequestManagementReportRowMode::OperatorsOnly);

    $chartOf = fn ($result) => collect(collect($result->categories)->firstWhere('key', 'gol')->charts)
        ->first(fn ($c) => $c->indicatorKey === 'telefonate');

    $chart = $chartOf($first);
    $labels = array_map(fn ($p) => $p->label, $chart->points);
    $values = array_map(fn ($p) => $p->value, $chart->points);

    // Ada and Mia tie at 2 (desc value), broken by label asc; Zoe last at 1.
    expect($labels)->toBe(['Ada Rossi', 'Mia Verdi', 'Zoe Bianchi'])
        ->and($values)->toBe([2, 2, 1])
        ->and(array_map(fn ($p) => $p->label, $chartOf($second)->points))->toBe($labels);
});

// ---------------------------------------------------------------------------
// AC-008 (rev-3) — an all-zero chart is KEPT
// ---------------------------------------------------------------------------

it('keeps the indicator chart of a branch whose every value is 0 (AC-008, rev-3)', function () {
    $categories = dashboardCategoryTree2();
    dashboardNote(dashboardQuoteAdvanced($categories['gol']), now());

    $actor = dashboardViewAllActor();
    $result = buildDashboard($actor, now()->subDay()->toDateString(), now()->addDay()->toDateString(), ['gol', 'apl'], RequestManagementReportRowMode::TotalOnly);

    $apl = collect($result->categories)->firstWhere('key', 'apl');
    $chart = collect($apl->charts)->first();

    expect($chart)->not->toBeNull()
        ->and(array_sum(array_map(fn ($p) => $p->value, $chart->points)))->toBe(0);
});

it('emits no operator chart for a branch with no GA2 row at all (AC-008, rev-3)', function () {
    dashboardCategoryTree2();
    $actor = dashboardViewAllActor();

    $result = buildDashboard($actor, '2026-09-01', '2026-09-30', ['gol'], RequestManagementReportRowMode::OperatorsOnly);

    expect(collect($result->categories)->firstWhere('key', 'gol')->charts)->toBe([]);
});

// ---------------------------------------------------------------------------
// AC-009 — RequestManagementScope respected
// ---------------------------------------------------------------------------

it('an actor scoped to their own requests only sees their own numbers (AC-009)', function () {
    app()->setLocale('it');
    $categories = dashboardCategoryTree2();
    $operator = User::factory()->create();
    dashboardNote(dashboardQuoteAdvanced($categories['gol'], $operator->id), now());
    dashboardNote(dashboardQuoteAdvanced($categories['gol'], User::factory()->create()->id), now());

    $result = buildDashboard($operator, now()->subDay()->toDateString(), now()->addDay()->toDateString(), ['gol'], RequestManagementReportRowMode::TotalOnly);

    $gol = collect($result->categories)->firstWhere('key', 'gol');

    expect(collect($gol->summary)->firstWhere('key', 'telefonate')->value)->toBe(1); // only the operator's own note
});
