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
use App\Services\RequestManagement\Report\RequestManagementReportGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

// AC-010 (D-2's own safety net): every dashboard value must equal the
// corresponding CSV cell of spec 0106, for the SAME filters. AC-011: the
// summary is NOT the sum of the categories (D-8). AC-012: the dashboard
// builder contains no query/business-rule of its own (static inspection).

uses(RefreshDatabase::class);

if (! function_exists('parityCategoryTree')) {
    /**
     * @return array<string, ProductCategory>
     */
    function parityCategoryTree(): array
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

if (! function_exists('parityQuote')) {
    function parityQuote(ProductCategory $category, ?int $operatorId = null, ?int $statusId = null, ?Opportunity $opportunity = null): Quote
    {
        $opportunity ??= Opportunity::factory()->create();
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

if (! function_exists('parityQuoteAdvanced')) {
    function parityQuoteAdvanced(ProductCategory $category, ?int $operatorId = null): Quote
    {
        $custom = QuoteWorkflowStatus::factory()->global()->create(['system_key' => null, 'group' => WorkflowStatusGroup::Open]);

        return parityQuote($category, $operatorId, $custom->id);
    }
}

if (! function_exists('parityNote')) {
    function parityNote(Quote $quote, Carbon $createdAt): void
    {
        Note::factory()->create([
            'notable_type' => 'opportunity',
            'notable_id' => $quote->opportunity_id,
            'created_at' => $createdAt,
        ])->forceFill(['quote_id' => $quote->id])->save();
    }
}

if (! function_exists('parityViewAllActor')) {
    function parityViewAllActor(): User
    {
        Permission::findOrCreate('request-management.viewAll');
        $actor = User::factory()->create();
        $actor->givePermissionTo('request-management.viewAll');

        return $actor;
    }
}

if (! function_exists('parityCsvRows')) {
    /**
     * @return array<int, array<int, string>>
     */
    function parityCsvRows(string $csv): array
    {
        $lines = array_filter(explode("\n", trim($csv, "\xEF\xBB\xBF\n")));

        return array_map(static fn (string $line): array => str_getcsv($line, ',', '"', ''), $lines);
    }
}

if (! function_exists('parityCsvCell')) {
    /**
     * The CSV cell for ($categoryLabel, $ga2Label, $indicatorKey) — column
     * position resolved from ReportCsvBuilder's own public contract order.
     *
     * @param  array<int, array<int, string>>  $rows
     */
    function parityCsvCell(array $rows, string $categoryLabel, string $ga2Label, string $indicatorKey): string
    {
        $columnIndex = array_search($indicatorKey, (array) config('request-management-report.indicator_columns'), true) + 2;

        foreach ($rows as $row) {
            if (($row[0] ?? null) === $categoryLabel && ($row[1] ?? null) === $ga2Label) {
                return $row[$columnIndex];
            }
        }

        throw new RuntimeException("No CSV row for [{$categoryLabel}/{$ga2Label}].");
    }
}

// ---------------------------------------------------------------------------
// AC-010 — every dashboard value equals the corresponding CSV cell
// ---------------------------------------------------------------------------

it('every dashboard point equals the corresponding CSV cell, for the same filters (AC-010)', function () {
    Storage::fake('local');
    $categories = parityCategoryTree();
    $ada = User::factory()->create(['name' => 'Ada Rossi']);
    $zoe = User::factory()->create(['name' => 'Zoe Bianchi']);

    parityNote(parityQuoteAdvanced($categories['gol'], $ada->id), now());
    parityNote(parityQuoteAdvanced($categories['gol'], $ada->id), now());
    parityNote(parityQuoteAdvanced($categories['gol'], $zoe->id), now());
    parityNote(parityQuoteAdvanced($categories['gol'], null), now());
    parityNote(parityQuoteAdvanced($categories['consulenza'], $ada->id), now());

    $actor = parityViewAllActor();
    app()->setLocale('it');
    $dateFrom = now()->subDay()->toDateString();
    $dateTo = now()->addDay()->toDateString();
    $categoryKeys = array_keys((array) config('request-management-report.branches'));

    // Generate the CSV via the SAME 0106 pipeline (no queue: call the
    // generator directly, mirroring GenerateRequestManagementReportJob).
    $csvPath = Storage::disk('local')->path('parity-test.csv');
    app(RequestManagementReportGenerator::class)->generate($actor, $dateFrom, $dateTo, $categoryKeys, RequestManagementReportRowMode::All, $csvPath);
    $rows = parityCsvRows(Storage::disk('local')->get('parity-test.csv'));

    // Build the dashboard for the IDENTICAL filters.
    $result = app(RequestManagementDashboardBuilder::class)->build($actor, $dateFrom, $dateTo, $categoryKeys, RequestManagementReportRowMode::All);

    expect($result->charts)->not->toBeEmpty();

    foreach ($result->charts as $chart) {
        foreach ($chart->points as $point) {
            $categoryLabel = $chart->categoryLabel ?? $point->label; // scope=category: the point IS the category
            $ga2Label = $chart->categoryLabel === null ? 'TOTALE' : $point->label;

            $csvValue = (int) parityCsvCell($rows, $categoryLabel, $ga2Label, $chart->indicatorKey);

            expect($point->value)->toBe($csvValue);
        }
    }
});

// ---------------------------------------------------------------------------
// AC-011 — summary is NOT the sum of the categories (D-8)
// ---------------------------------------------------------------------------

it('summary is LESS than the sum of the two category totals when a request is shared between them (AC-011)', function () {
    $categories = parityCategoryTree();

    // One opportunity classified on BOTH gol and autoimpiego (shared quote).
    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => BusinessFunction::factory()->create()->id,
        'product_category_id' => $categories['gol']->id,
    ]);
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => BusinessFunction::factory()->create()->id,
        'product_category_id' => $categories['autoimpiego']->id,
    ]);
    Quote::factory()->create(['opportunity_id' => $opportunity->id]); // created_at = now, still 'open'

    // One more request, GOL-only, distinct from the shared one.
    parityQuote($categories['gol']);

    $actor = parityViewAllActor();
    $result = app(RequestManagementDashboardBuilder::class)->build(
        $actor,
        now()->subDay()->toDateString(),
        now()->addDay()->toDateString(),
        ['gol', 'autoimpiego'],
        RequestManagementReportRowMode::TotalOnly,
    );

    $summaryValue = collect($result->summary)->first(fn ($item) => $item->key === 'nuovi_contatti')->value;

    $categoryChart = collect($result->charts)->first(fn ($c) => $c->indicatorKey === 'nuovi_contatti');
    $sumOfCategories = array_sum(array_map(fn ($p) => $p->value, $categoryChart->points));

    expect($summaryValue)->toBe(2) // the shared quote + the GOL-only one, counted ONCE each
        ->and($sumOfCategories)->toBe(3) // gol=2 (shared + own) + autoimpiego=1 (shared) — double-counts the shared quote
        ->and($summaryValue)->toBeLessThan($sumOfCategories);
});

// ---------------------------------------------------------------------------
// AC-012 — no query/business-rule of its own (static inspection)
// ---------------------------------------------------------------------------

it('the dashboard builder never references the Quote model or a query builder facade (AC-012)', function () {
    $source = file_get_contents(app_path('Services/RequestManagement/Report/Dashboard/RequestManagementDashboardBuilder.php'));

    expect($source)->not->toContain('Quote::')
        ->and($source)->not->toContain('use App\Models\Quote;')
        ->and($source)->not->toContain('DB::')
        ->and($source)->not->toContain('->where(')
        ->and($source)->not->toContain('->join(');
});
