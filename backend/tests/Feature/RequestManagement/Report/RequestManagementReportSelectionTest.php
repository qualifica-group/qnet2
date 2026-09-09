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

// RequestManagementReportGenerator (spec 0106 rev-2): category_keys filters
// the branches WITHOUT recomputing them (AC-032), row_mode filters which
// rows a branch emits (AC-033/034), and the job re-reads BOTH from the
// frozen ExportRun.state, never a default (AC-035).

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
    /**
     * The author defaults to the quote's OWN GA2 operator: since spec 0106
     * rev-3 (D-17) that is the only note "N. Telefonate Effettuate" counts.
     * Pass $authorId explicitly for the third-party case.
     */
    function reportNote(Quote $quote, Carbon $createdAt, ?int $authorId = null): void
    {
        $author = $authorId ?? $quote->operator_id;

        Note::factory()->create([
            'notable_type' => 'opportunity',
            'notable_id' => $quote->opportunity_id,
            'created_at' => $createdAt,
            ...($author !== null ? ['user_id' => $author] : []),
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
// AC-032/AC-035 — category_keys filters the output, never recomputes it
// ---------------------------------------------------------------------------

it('CSV contains only the selected categories, with values identical to the integral report (AC-032/AC-035)', function () {
    $categories = reportCategoryTree();
    $ada = User::factory()->create(['name' => 'Ada Rossi']);

    reportNote(reportQuoteWithOpenAdvance($categories['gol'], $ada->id), now());
    reportNote(reportQuoteWithOpenAdvance($categories['gol'], $ada->id), now());
    reportNote(reportQuoteWithOpenAdvance($categories['consulenza']), now());
    reportNote(reportQuoteWithOpenAdvance($categories['apl']), now());

    $actor = reportViewAllActor();
    $range = [now()->subDay()->toDateString(), now()->addDay()->toDateString()];

    $integralRun = createReportRun($actor, ...$range);
    runReportJob($integralRun);
    $integralRows = reportCsvRows(Storage::disk('local')->get($integralRun->fresh()->file_path));

    $filteredRun = createReportRun($actor, ...$range, categoryKeys: ['gol', 'consulenza']);
    runReportJob($filteredRun);
    $filteredRows = reportCsvRows(Storage::disk('local')->get($filteredRun->fresh()->file_path));

    $filteredCategories = array_values(array_unique(array_slice(array_column($filteredRows, 0), 1)));
    expect($filteredCategories)->toBe(['GOL', 'Consulenza']); // APL excluded, not just empty

    // Same VALUES as the integral report for the two kept branches (AC-032:
    // filtering, not a different calculation).
    expect(reportRowsFor($filteredRows, 'GOL'))->toBe(reportRowsFor($integralRows, 'GOL'))
        ->and(reportRowsFor($filteredRows, 'Consulenza'))->toBe(reportRowsFor($integralRows, 'Consulenza'));
});

// ---------------------------------------------------------------------------
// AC-033/AC-034 — row_mode
// ---------------------------------------------------------------------------

it('total_only emits ONLY the TOTALE row per selected category, zero when empty (AC-033/AC-034)', function () {
    $categories = reportCategoryTree();
    reportNote(reportQuoteWithOpenAdvance($categories['gol']), now());
    // 'consulenza' stays empty in the range.

    $actor = reportViewAllActor();
    $run = createReportRun(
        $actor,
        now()->subDay()->toDateString(),
        now()->addDay()->toDateString(),
        categoryKeys: ['gol', 'consulenza'],
        rowMode: 'total_only',
    );

    runReportJob($run);

    $rows = reportCsvRows(Storage::disk('local')->get($run->fresh()->file_path));

    expect(reportRowsFor($rows, 'GOL'))->toHaveKey('TOTALE')
        ->and(reportRowsFor($rows, 'GOL'))->toHaveCount(1)
        ->and(reportRowsFor($rows, 'Consulenza'))->toBe(['TOTALE' => reportRowsFor($rows, 'Consulenza')['TOTALE']]); // TOTALE-only, at zero

    expect(reportRowsFor($rows, 'Consulenza')['TOTALE'][2])->toBe('0');
});

it('operators_only emits GA2 + Non assegnato but never TOTALE, and NO row at all for an empty category (AC-033/AC-034)', function () {
    $categories = reportCategoryTree();
    $ada = User::factory()->create(['name' => 'Ada Rossi']);
    reportNote(reportQuoteWithOpenAdvance($categories['gol'], $ada->id), now());
    // No GA2: since rev-3 (D-17) a note is never a phone call without one, so
    // the "Non assegnato" row comes from "N. Richiami non gestiti".
    reportQuote($categories['gol'], null)->forceFill(['next_callback_at' => now()])->save();
    // 'consulenza' stays empty in the range.

    $actor = reportViewAllActor();
    $run = createReportRun(
        $actor,
        now()->subDay()->toDateString(),
        now()->addDay()->toDateString(),
        categoryKeys: ['gol', 'consulenza'],
        rowMode: 'operators_only',
    );

    runReportJob($run);

    $rows = reportCsvRows(Storage::disk('local')->get($run->fresh()->file_path));

    $golRows = reportRowsFor($rows, 'GOL');
    expect(array_keys($golRows))->toBe(['Ada Rossi', 'Non assegnato'])
        ->and($golRows)->not->toHaveKey('TOTALE');

    // AC-034: a selected category with NO request in range emits NO row at all in operators_only.
    expect(reportRowsFor($rows, 'Consulenza'))->toBe([]);
});

it('all (default) emits both TOTALE and GA2 rows, as before rev-2 (AC-033)', function () {
    $categories = reportCategoryTree();
    $ada = User::factory()->create(['name' => 'Ada Rossi']);
    reportNote(reportQuoteWithOpenAdvance($categories['gol'], $ada->id), now());

    $actor = reportViewAllActor();
    $run = createReportRun(
        $actor,
        now()->subDay()->toDateString(),
        now()->addDay()->toDateString(),
        categoryKeys: ['gol'],
        rowMode: 'all',
    );

    runReportJob($run);

    $rows = reportRowsFor(reportCsvRows(Storage::disk('local')->get($run->fresh()->file_path)), 'GOL');

    expect(array_keys($rows))->toBe(['TOTALE', 'Ada Rossi']);
});
