<?php

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

// RequestManagementDashboardBuilder (spec 0107): AC-004 (row_mode gates
// scope), AC-005/006 (points content), AC-007 (deterministic ordering),
// AC-008 (all-zero charts dropped), AC-009 (scope respected).

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

// ---------------------------------------------------------------------------
// AC-004 — row_mode gates which scope(s) appear
// ---------------------------------------------------------------------------

it('total_only yields ONLY scope=category charts (AC-004)', function () {
    $categories = dashboardCategoryTree2();
    dashboardNote(dashboardQuoteAdvanced($categories['gol']), now());

    $actor = dashboardViewAllActor();
    $result = buildDashboard($actor, now()->subDay()->toDateString(), now()->addDay()->toDateString(), ['gol'], RequestManagementReportRowMode::TotalOnly);

    expect($result->charts)->not->toBeEmpty();
    foreach ($result->charts as $chart) {
        expect($chart->scope->value)->toBe('category');
    }
});

it('operators_only yields ONLY scope=operator charts (AC-004)', function () {
    $categories = dashboardCategoryTree2();
    dashboardNote(dashboardQuoteAdvanced($categories['gol']), now());

    $actor = dashboardViewAllActor();
    $result = buildDashboard($actor, now()->subDay()->toDateString(), now()->addDay()->toDateString(), ['gol'], RequestManagementReportRowMode::OperatorsOnly);

    expect($result->charts)->not->toBeEmpty();
    foreach ($result->charts as $chart) {
        expect($chart->scope->value)->toBe('operator');
    }
});

it('all yields both scopes, category charts first (AC-004)', function () {
    $categories = dashboardCategoryTree2();
    dashboardNote(dashboardQuoteAdvanced($categories['gol']), now());

    $actor = dashboardViewAllActor();
    $result = buildDashboard($actor, now()->subDay()->toDateString(), now()->addDay()->toDateString(), ['gol'], RequestManagementReportRowMode::All);

    $scopes = array_map(fn ($c) => $c->scope->value, $result->charts);

    expect($scopes)->toContain('category')
        ->and($scopes)->toContain('operator');

    // Every 'category' entry comes before every 'operator' entry.
    $categoryIndices = array_keys(array_filter($scopes, fn ($s) => $s === 'category'));
    $operatorIndices = array_keys(array_filter($scopes, fn ($s) => $s === 'operator'));
    expect(max($categoryIndices))->toBeLessThan(min($operatorIndices));
});

// ---------------------------------------------------------------------------
// AC-005/AC-006 — points content
// ---------------------------------------------------------------------------

it('scope=category has one point per selected category, labelled with the category domain name (AC-005)', function () {
    $categories = dashboardCategoryTree2();
    dashboardNote(dashboardQuoteAdvanced($categories['gol']), now());
    dashboardNote(dashboardQuoteAdvanced($categories['consulenza']), now());

    $actor = dashboardViewAllActor();
    $result = buildDashboard($actor, now()->subDay()->toDateString(), now()->addDay()->toDateString(), ['gol', 'consulenza'], RequestManagementReportRowMode::TotalOnly);

    $telefonate = collect($result->charts)->first(fn ($c) => $c->indicatorKey === 'telefonate');

    expect($telefonate)->not->toBeNull();
    expect(array_map(fn ($p) => $p->label, $telefonate->points))->toEqualCanonicalizing(['GOL', 'Consulenza']);
});

it('scope=operator has one point per GA2, including Non assegnato with the CSV label (AC-006)', function () {
    app()->setLocale('it'); // the label is resolved from the SAME it/request-management-report.php catalogue as the CSV.
    $categories = dashboardCategoryTree2();
    $ada = User::factory()->create(['name' => 'Ada Rossi']);
    dashboardNote(dashboardQuoteAdvanced($categories['gol'], $ada->id), now());
    dashboardNote(dashboardQuoteAdvanced($categories['gol'], null), now());

    $actor = dashboardViewAllActor();
    $result = buildDashboard($actor, now()->subDay()->toDateString(), now()->addDay()->toDateString(), ['gol'], RequestManagementReportRowMode::OperatorsOnly);

    $telefonate = collect($result->charts)->first(fn ($c) => $c->indicatorKey === 'telefonate' && $c->categoryKey === 'gol');

    expect($telefonate)->not->toBeNull();
    expect(array_map(fn ($p) => $p->label, $telefonate->points))->toEqualCanonicalizing(['Ada Rossi', 'Non assegnato']);
});

// ---------------------------------------------------------------------------
// AC-007 — deterministic ordering
// ---------------------------------------------------------------------------

it('points are ordered by value desc then label asc, identically across repeated calls (AC-007)', function () {
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

    $chart = collect($first->charts)->first(fn ($c) => $c->indicatorKey === 'telefonate');
    $labels = array_map(fn ($p) => $p->label, $chart->points);
    $values = array_map(fn ($p) => $p->value, $chart->points);

    // Ada and Mia tie at 2 (desc value), broken by label asc; Zoe last at 1.
    expect($labels)->toBe(['Ada Rossi', 'Mia Verdi', 'Zoe Bianchi'])
        ->and($values)->toBe([2, 2, 1]);

    $chartAgain = collect($second->charts)->first(fn ($c) => $c->indicatorKey === 'telefonate');
    expect(array_map(fn ($p) => $p->label, $chartAgain->points))->toBe($labels);
});

// ---------------------------------------------------------------------------
// AC-008 — an all-zero chart is dropped
// ---------------------------------------------------------------------------

it('drops a chart whose points are all 0, and returns charts=[] when nothing survives (AC-008)', function () {
    dashboardCategoryTree2();
    $actor = dashboardViewAllActor();

    $result = buildDashboard($actor, '2026-09-01', '2026-09-30', ['gol'], RequestManagementReportRowMode::All);

    expect($result->charts)->toBe([]);
});

// ---------------------------------------------------------------------------
// AC-009 — RequestManagementScope respected
// ---------------------------------------------------------------------------

it('an actor scoped to their own requests only sees their own numbers (AC-009)', function () {
    $categories = dashboardCategoryTree2();
    $operator = User::factory()->create();
    dashboardNote(dashboardQuoteAdvanced($categories['gol'], $operator->id), now());
    dashboardNote(dashboardQuoteAdvanced($categories['gol'], User::factory()->create()->id), now());

    $result = buildDashboard($operator, now()->subDay()->toDateString(), now()->addDay()->toDateString(), ['gol'], RequestManagementReportRowMode::TotalOnly);

    $telefonate = collect($result->charts)->first(fn ($c) => $c->indicatorKey === 'telefonate');
    $golPoint = collect($telefonate->points)->first(fn ($p) => $p->label === 'GOL');

    expect($golPoint->value)->toBe(1); // only the operator's own note
});
