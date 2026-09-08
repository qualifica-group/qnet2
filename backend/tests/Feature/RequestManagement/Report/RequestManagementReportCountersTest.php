<?php

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
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

// RequestManagementReportGenerator (spec 0106): "N. Telefonate Effettuate"
// (AC-009/010/011), "N. Richiami non gestiti" (AC-012), "N. Nuovi contatti
// non gestiti" (AC-013).

uses(RefreshDatabase::class);

if (! function_exists('reportCategoryTree')) {
    /**
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

if (! function_exists('reportViewAllActor')) {
    function reportViewAllActor(): User
    {
        Permission::findOrCreate('request-management.viewAll');
        $actor = User::factory()->create();
        $actor->givePermissionTo('request-management.viewAll');

        return $actor;
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
// AC-009/AC-010/AC-011 — "N. Telefonate Effettuate"
// ---------------------------------------------------------------------------

it('counts NOTES not requests, excludes deleted/out-of-range/general notes and first-state requests, includes custom statuses (AC-009/010/011)', function () {
    $categories = reportCategoryTree();

    // AC-011: a request on a CUSTOM status (system_key null) still counts.
    $onCustom = reportQuoteWithOpenAdvance($categories['gol']);
    reportNote($onCustom, Carbon::parse('2026-09-10'));
    reportNote($onCustom, Carbon::parse('2026-09-11')); // AC-009: 2 notes, same quote -> 2

    // Deleted note: excluded.
    $deleted = Note::factory()->create([
        'notable_type' => 'opportunity',
        'notable_id' => $onCustom->opportunity_id,
        'created_at' => Carbon::parse('2026-09-12'),
        'deleted_at' => Carbon::parse('2026-09-12'),
    ]);
    $deleted->forceFill(['quote_id' => $onCustom->id])->save();

    // Out of range: excluded.
    reportNote($onCustom, Carbon::parse('2026-08-01'));

    // General note (quote_id null): excluded.
    Note::factory()->create([
        'notable_type' => 'opportunity',
        'notable_id' => $onCustom->opportunity_id,
        'created_at' => Carbon::parse('2026-09-10'),
    ]);

    // Still in the FIRST state ('open'): excluded.
    $stillOpen = reportQuote($categories['gol']);
    reportNote($stillOpen, Carbon::parse('2026-09-10'));

    $actor = reportViewAllActor();
    $run = createReportRun($actor, '2026-09-01', '2026-09-30');

    runReportJob($run);

    $rows = reportRowsFor(reportCsvRows(Storage::disk('local')->get($run->fresh()->file_path)), 'GOL');

    expect($rows['TOTALE'][2])->toBe('2');
});

// ---------------------------------------------------------------------------
// AC-012 — "N. Richiami non gestiti"
// ---------------------------------------------------------------------------

it('counts requests with next_callback_at in range still in the first state (AC-012)', function () {
    $categories = reportCategoryTree();

    $unhandled = reportQuote($categories['gol']); // stays on the global 'open' status
    $unhandled->forceFill(['next_callback_at' => Carbon::parse('2026-09-15')])->save();

    // Advanced past the first state: excluded even with next_callback_at in range.
    $advanced = reportQuoteWithOpenAdvance($categories['gol']);
    $advanced->forceFill(['next_callback_at' => Carbon::parse('2026-09-15')])->save();

    // Out of range: excluded.
    $outOfRange = reportQuote($categories['gol']);
    $outOfRange->forceFill(['next_callback_at' => Carbon::parse('2026-08-01')])->save();

    $actor = reportViewAllActor();
    $run = createReportRun($actor, '2026-09-01', '2026-09-30');

    runReportJob($run);

    $rows = reportRowsFor(reportCsvRows(Storage::disk('local')->get($run->fresh()->file_path)), 'GOL');

    expect($rows['TOTALE'][3])->toBe('1');
});

// ---------------------------------------------------------------------------
// AC-013 — "N. Nuovi contatti non gestiti"
// ---------------------------------------------------------------------------

it('counts requests created in range still in the first state (AC-013)', function () {
    $categories = reportCategoryTree();

    Carbon::setTestNow('2026-09-10');
    reportQuote($categories['gol']);
    Carbon::setTestNow();

    // Created in range but already advanced: excluded.
    Carbon::setTestNow('2026-09-11');
    reportQuoteWithOpenAdvance($categories['gol']);
    Carbon::setTestNow();

    $actor = reportViewAllActor();
    $run = createReportRun($actor, '2026-09-01', '2026-09-30');

    runReportJob($run);

    $rows = reportRowsFor(reportCsvRows(Storage::disk('local')->get($run->fresh()->file_path)), 'GOL');

    expect($rows['TOTALE'][4])->toBe('1');
});
