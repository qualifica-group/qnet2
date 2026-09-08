<?php

use App\Enums\ExportStatus;
use App\Enums\WorkflowStatusGroup;
use App\Jobs\GenerateRequestManagementReportJob;
use App\Models\BusinessFunction;
use App\Models\ExportRun;
use App\Models\Note;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\Services\RequestManagement\Report\RequestManagementReportGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

// GenerateRequestManagementReportJob (spec 0106, D-8): the two mandatory
// queue traps — AC-003-quater (no HTTP session), AC-003-quinquies (frozen
// locale) — plus AC-003-sexies (failure leaves no completed file).

uses(RefreshDatabase::class);

if (! function_exists('reportCategoryTree')) {
    /**
     * The six report branches' real category tree, named EXACTLY as
     * QualificaCatalogSeeder::CATALOG (spec 0106 constraints).
     *
     * @return array<string, ProductCategory>
     */
    function reportCategoryTree(): array
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

if (! function_exists('reportQuote')) {
    /**
     * A Quote classified on $category through a fresh Opportunity, on the
     * GLOBAL default 'open' status unless $statusId overrides it.
     */
    function reportQuote(ProductCategory $category, ?int $operatorId = null, ?int $statusId = null, ?Opportunity $opportunity = null): Quote
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

if (! function_exists('reportQuoteWithOpenAdvance')) {
    /**
     * A quote on a CUSTOM (non-open) status, so its notes count for
     * "telefonate" (AC-011's own custom-status case).
     */
    function reportQuoteWithOpenAdvance(ProductCategory $category, ?int $operatorId = null): Quote
    {
        $custom = QuoteWorkflowStatus::factory()->global()->create(['system_key' => null, 'group' => WorkflowStatusGroup::Open]);

        return reportQuote($category, $operatorId, $custom->id);
    }
}

if (! function_exists('reportNote')) {
    function reportNote(Quote $quote, Carbon $createdAt): void
    {
        Note::factory()->create([
            'notable_type' => 'opportunity',
            'notable_id' => $quote->opportunity_id,
            'created_at' => $createdAt,
        ])->forceFill(['quote_id' => $quote->id])->save();
    }
}

if (! function_exists('createReportRun')) {
    function createReportRun(User $actor, string $dateFrom, string $dateTo, string $locale = 'it', ?array $categoryKeys = null, string $rowMode = 'all'): ExportRun
    {
        return ExportRun::factory()->create([
            'user_id' => $actor->id,
            'resource' => 'request-management-report',
            'state' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'locale' => $locale,
                'category_keys' => $categoryKeys ?? array_keys((array) config('request-management-report.branches')),
                'row_mode' => $rowMode,
            ],
        ]);
    }
}

if (! function_exists('runReportJob')) {
    function runReportJob(ExportRun $run): void
    {
        (new GenerateRequestManagementReportJob($run->id))->handle(app(RequestManagementReportGenerator::class));
    }
}

if (! function_exists('reportCsvRows')) {
    /**
     * @return array<int, array<int, string>>
     */
    function reportCsvRows(string $csv): array
    {
        $lines = array_filter(explode("\n", trim($csv, "\xEF\xBB\xBF\n")));

        return array_map(static fn (string $line): array => str_getcsv($line, ',', '"', ''), $lines);
    }
}

if (! function_exists('reportRowsFor')) {
    /**
     * Every data row whose "Categoria" cell equals $categoryLabel, keyed by
     * the "GA2" cell.
     *
     * @param  array<int, array<int, string>>  $rows
     * @return array<string, array<int, string>>
     */
    function reportRowsFor(array $rows, string $categoryLabel): array
    {
        $matched = array_values(array_filter($rows, static fn (array $row): bool => ($row[0] ?? null) === $categoryLabel));

        $byGa2 = [];
        foreach ($matched as $row) {
            $byGa2[$row[1]] = $row;
        }

        return $byGa2;
    }
}

beforeEach(function () {
    Storage::fake('local');
});

// ---------------------------------------------------------------------------
// AC-003-quater — the job authenticates the frozen actor BEFORE any query
// ---------------------------------------------------------------------------

it('is scoped as the frozen actor even with NO HTTP session (AC-003-quater)', function () {
    $categories = reportCategoryTree();

    $operator = User::factory()->create();
    $outsider = User::factory()->create();

    // Two quotes in the GOL branch: one operated by $operator, one by a
    // stranger — only the first must be visible without viewAll/viewSite.
    reportNote(reportQuoteWithOpenAdvance($categories['gol'], $operator->id), now());
    reportNote(reportQuoteWithOpenAdvance($categories['gol'], $outsider->id), now());

    $run = createReportRun($operator, now()->subDay()->toDateString(), now()->addDay()->toDateString());

    // Deliberately NO Sanctum::actingAs()/Auth::login(): this simulates a
    // real queue worker with no HTTP session at all — the exact trap
    // ExportService::generate() guards against at :82.
    expect(Auth::user())->toBeNull();

    runReportJob($run);

    $rows = reportRowsFor(reportCsvRows(Storage::disk('local')->get($run->fresh()->file_path)), 'GOL');

    expect($rows['TOTALE'][2])->toBe('1'); // only the operator's own note, not the outsider's
});

// ---------------------------------------------------------------------------
// AC-003-quinquies — the job translates in the run's FROZEN locale
// ---------------------------------------------------------------------------

it('writes the header row translated in the run frozen locale, not config(app.locale) (AC-003-quinquies)', function () {
    reportCategoryTree();
    config(['app.locale' => 'en']); // the applicative locale a queue worker would carry
    expect(app()->getLocale())->toBe('en');

    $actor = User::factory()->create();
    $run = createReportRun($actor, '2026-09-01', '2026-09-30', locale: 'it');

    runReportJob($run);

    $headers = reportCsvRows(Storage::disk('local')->get($run->fresh()->file_path))[0];

    expect($headers[0])->toBe('Categoria')
        ->and($headers[2])->toBe('N. Telefonate Effettuate');
});

// ---------------------------------------------------------------------------
// AC-003-sexies — an exception fails the run, no partial file downloadable
// ---------------------------------------------------------------------------

it('fails the run on an unhandled exception, leaving no completed file (AC-003-sexies)', function () {
    // No category tree seeded at all: ReportBranchResolver throws.
    $actor = User::factory()->create();
    $run = createReportRun($actor, '2026-09-01', '2026-09-30');

    expect(fn () => runReportJob($run))->toThrow(Exception::class);

    expect($run->fresh()->status)->toBe(ExportStatus::Failed)
        ->and($run->fresh()->file_path)->toBeNull();
});
