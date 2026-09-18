<?php

declare(strict_types=1);

use App\Enums\ExportFormat;
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
use App\Services\RequestManagement\Report\RequestManagementReportGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0141 AC-005/AC-006: two DIFFERENTLY configured categories, real export
 * + dashboard build (never the factory's `reportable()` full-catalog default,
 * which masks per-category restriction). GOL: telefonate, richiami,
 * nuovi_contatti. APL: telefonate, trattative_concluse, associati.
 */
uses(RefreshDatabase::class);

if (! function_exists('columnConfigActor')) {
    function columnConfigActor(): User
    {
        Permission::findOrCreate('request-management.viewAll');
        $actor = User::factory()->create();
        $actor->givePermissionTo('request-management.viewAll');

        return $actor;
    }
}

if (! function_exists('columnConfigCategories')) {
    /**
     * @return array{gol: ProductCategory, apl: ProductCategory}
     */
    function columnConfigCategories(): array
    {
        return [
            'gol' => ProductCategory::factory()->reportable()->reportColumns(['telefonate', 'richiami', 'nuovi_contatti'])->create(['name' => 'GOL']),
            'apl' => ProductCategory::factory()->reportable()->reportColumns(['telefonate', 'trattative_concluse', 'associati'])->create(['name' => 'APL']),
        ];
    }
}

if (! function_exists('columnConfigOpportunity')) {
    function columnConfigOpportunity(ProductCategory $category): Opportunity
    {
        $opportunity = Opportunity::factory()->create();
        OpportunityProductLine::factory()->create([
            'opportunity_id' => $opportunity->id,
            'business_function_id' => BusinessFunction::factory()->create()->id,
            'product_category_id' => $category->id,
        ]);

        return $opportunity;
    }
}

if (! function_exists('columnConfigQuote')) {
    function columnConfigQuote(Opportunity $opportunity, int $operatorId, int $statusId): Quote
    {
        $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id, 'quote_workflow_status_id' => $statusId]);
        $quote->forceFill(['operator_id' => $operatorId])->save();

        return $quote;
    }
}

if (! function_exists('columnConfigTransitionLog')) {
    /** Writes an activity_log row exactly as the real workflow write would (D-3 shape). */
    function columnConfigTransitionLog(Opportunity $opportunity, int $targetStatusId, Carbon $createdAt): void
    {
        Carbon::setTestNow($createdAt);

        activity('opportunities')
            ->performedOn($opportunity)
            ->event('updated')
            ->withProperties(['attributes' => ['quote_workflow_status_id' => $targetStatusId], 'old' => ['quote_workflow_status_id' => null]])
            ->log('Request management work update');

        Carbon::setTestNow();
    }
}

if (! function_exists('columnConfigFixture')) {
    /**
     * GOL: one worked (non-open) request with a "telefonate"-counting note
     * from its own GA2 (Ada). APL: one request transitioned to closed_won
     * (Bruno), which drives BOTH "associati" and "trattative_concluse" —
     * the same WorkflowTransitionIndicator instance.
     *
     * @return array{categories: array{gol: ProductCategory, apl: ProductCategory}, ada: User, bruno: User}
     */
    function columnConfigFixture(): array
    {
        $categories = columnConfigCategories();
        $worked = QuoteWorkflowStatus::factory()->global()->create(['system_key' => null, 'group' => WorkflowStatusGroup::Pending]);
        $won = QuoteWorkflowStatus::factory()->global()->create(['system_key' => null, 'group' => WorkflowStatusGroup::ClosedWon]);

        $ada = User::factory()->create(['name' => 'Ada Rossi']);
        $golOpportunity = columnConfigOpportunity($categories['gol']);
        $golQuote = columnConfigQuote($golOpportunity, $ada->id, $worked->id);
        Note::factory()->create([
            'notable_type' => 'opportunity',
            'notable_id' => $golOpportunity->id,
            'created_at' => now(),
            'user_id' => $ada->id,
        ])->forceFill(['quote_id' => $golQuote->id])->save();

        $bruno = User::factory()->create(['name' => 'Bruno Verdi']);
        $aplOpportunity = columnConfigOpportunity($categories['apl']);
        columnConfigQuote($aplOpportunity, $bruno->id, $won->id);
        columnConfigTransitionLog($aplOpportunity, $won->id, now());

        return ['categories' => $categories, 'ada' => $ada, 'bruno' => $bruno];
    }
}

if (! function_exists('columnConfigCsvRows')) {
    /**
     * @return array<int, array<int, string>>
     */
    function columnConfigCsvRows(string $csv): array
    {
        return array_map(
            static fn (string $line): array => str_getcsv($line, ',', '"', ''),
            array_filter(explode("\n", trim($csv, "\xEF\xBB\xBF\n"))),
        );
    }
}

if (! function_exists('columnConfigRow')) {
    /**
     * @param  array<int, array<int, string>>  $rows
     * @return array<int, string>
     */
    function columnConfigRow(array $rows, string $categoryLabel): array
    {
        foreach ($rows as $row) {
            if (($row[0] ?? null) === $categoryLabel) {
                return $row;
            }
        }

        throw new RuntimeException("No CSV row for [{$categoryLabel}].");
    }
}

// ---------------------------------------------------------------------------
// AC-005 — real CSV/XLSX export: union header, per-row empty vs valued cells
// ---------------------------------------------------------------------------

it('AC-005: CSV header is the catalog-ordered union of GOL and APL own columns, empty cells for what each does not configure', function () {
    Storage::fake('local');
    $fixture = columnConfigFixture();
    $categories = $fixture['categories'];
    $actor = columnConfigActor();
    $range = [now()->subDay()->toDateString(), now()->addDay()->toDateString()];
    $path = Storage::disk('local')->path('ac-005.csv');

    app(RequestManagementReportGenerator::class)->generate(
        $actor,
        ...$range,
        categoryKeys: [(string) $categories['gol']->id, (string) $categories['apl']->id],
        rowMode: RequestManagementReportRowMode::TotalOnly,
        format: ExportFormat::Csv,
        absolutePath: $path,
    );

    $rows = columnConfigCsvRows(Storage::disk('local')->get('ac-005.csv'));

    $expectedHeader = array_map(
        static fn (string $key): string => __("request-management-report.headers.{$key}"),
        ['category', 'ga2', 'telefonate', 'richiami', 'nuovi_contatti', 'associati', 'trattative_concluse'],
    );
    expect($rows[0])->toBe($expectedHeader);

    $total = __('request-management-report.labels.total');

    $gol = columnConfigRow($rows, 'GOL');
    // Categoria, GA2, Telefonate, Richiami, Nuovi contatti, Associati, Trattative concluse
    expect($gol)->toBe(['GOL', $total, '1', '0', '0', '', '']);

    $apl = columnConfigRow($rows, 'APL');
    expect($apl)->toBe(['APL', $total, '0', '', '', '1', '1']);
});

it('AC-005: the same union/empty-cell shape holds for the XLSX writer', function () {
    Storage::fake('local');
    $fixture = columnConfigFixture();
    $categories = $fixture['categories'];
    $actor = columnConfigActor();
    $range = [now()->subDay()->toDateString(), now()->addDay()->toDateString()];
    $path = Storage::disk('local')->path('ac-005.xlsx');

    app(RequestManagementReportGenerator::class)->generate(
        $actor,
        ...$range,
        categoryKeys: [(string) $categories['gol']->id, (string) $categories['apl']->id],
        rowMode: RequestManagementReportRowMode::TotalOnly,
        format: ExportFormat::Xlsx,
        absolutePath: $path,
    );

    $reader = new XlsxReader;
    $reader->open($path);

    $sheetRows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $sheetRows[] = $row->toArray();
        }
    }
    $reader->close();

    $expectedHeader = array_map(
        static fn (string $key): string => __("request-management-report.headers.{$key}"),
        ['category', 'ga2', 'telefonate', 'richiami', 'nuovi_contatti', 'associati', 'trattative_concluse'],
    );
    expect($sheetRows[0])->toBe($expectedHeader)
        ->and(count($sheetRows[0]))->toBe(7);
});

// ---------------------------------------------------------------------------
// AC-006 — dashboard: per-category restriction, overall union
// ---------------------------------------------------------------------------

it('AC-006: dashboard sections show only their own configured columns, overall summary is the catalog-ordered union', function () {
    $fixture = columnConfigFixture();
    $categories = $fixture['categories'];
    $actor = columnConfigActor();
    $range = [now()->subDay()->toDateString(), now()->addDay()->toDateString()];

    /** @var RequestManagementDashboardResult $result */
    $result = app(RequestManagementDashboardBuilder::class)->build(
        $actor,
        ...$range,
        categoryKeys: [(string) $categories['gol']->id, (string) $categories['apl']->id],
        rowMode: RequestManagementReportRowMode::All,
    );

    $gol = collect($result->categories)->firstWhere('key', (string) $categories['gol']->id);
    $apl = collect($result->categories)->firstWhere('key', (string) $categories['apl']->id);

    expect(array_map(fn ($item) => $item->key, $gol->summary))->toBe(['telefonate', 'richiami', 'nuovi_contatti'])
        ->and(array_map(fn ($item) => $item->key, $apl->summary))->toBe(['telefonate', 'associati', 'trattative_concluse']);

    // The indicator chart mirrors the summary's own columns, in catalog order.
    $golIndicatorChart = collect($gol->charts)->first(fn ($c) => $c->indicatorKey === null);
    $aplIndicatorChart = collect($apl->charts)->first(fn ($c) => $c->indicatorKey === null);
    expect(count($golIndicatorChart->points))->toBe(3)
        ->and(count($aplIndicatorChart->points))->toBe(3);

    // One operator chart per configured column — never the other category's.
    $golOperatorKeys = collect($gol->charts)->filter(fn ($c) => $c->indicatorKey !== null)->pluck('indicatorKey')->all();
    $aplOperatorKeys = collect($apl->charts)->filter(fn ($c) => $c->indicatorKey !== null)->pluck('indicatorKey')->all();
    expect($golOperatorKeys)->toBe(['telefonate', 'richiami', 'nuovi_contatti'])
        ->and($aplOperatorKeys)->toBe(['telefonate', 'associati', 'trattative_concluse']);

    // Overall summary: union of both, catalog order — never the full catalog.
    expect(array_map(fn ($item) => $item->key, $result->summary))
        ->toBe(['telefonate', 'richiami', 'nuovi_contatti', 'associati', 'trattative_concluse']);
});
