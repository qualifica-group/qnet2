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

// RequestManagementReportGenerator (spec 0106): the CSV shape itself —
// AC-004/005 (header + six categories in order, empty branch), AC-006/007/
// 014 (GA2 breakdown, sort, "Non assegnato", TOTALE = sum) and AC-008
// (empty cell vs explicit stub 0).

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
    /**
     * An actor with `request-management.viewAll` (RequestManagementScope):
     * without it, scopeToActor() narrows every query to `operator_id =
     * actor->id`, so a report actor unrelated to the fixtures would see
     * nothing at all — these tests exercise the AGGREGATION, not the scope
     * (that is AC-021's own test).
     */
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
// AC-004/AC-005
// ---------------------------------------------------------------------------

it('writes the 13-column header + all six categories in order, an empty one as TOTALE-only zeros (AC-004/AC-005)', function () {
    reportCategoryTree();
    $actor = User::factory()->create();
    $run = createReportRun($actor, '2026-09-01', '2026-09-30');

    runReportJob($run);

    $rows = reportCsvRows(Storage::disk('local')->get($run->fresh()->file_path));

    expect($rows[0])->toBe([
        'Categoria', 'GA2', 'N. Telefonate Effettuate', 'N. Richiami non gestiti', 'N. Nuovi contatti non gestiti',
        'N. Potenziali associati', 'Aule in gestione', 'Aule in partenza', 'Associati', 'Aziende inserite',
        'Presa Appuntamenti', 'Trattative Concluse', 'Invio Presa in carico',
    ]);

    $categories = array_column($rows, 0);
    array_shift($categories); // drop the header row's "Categoria"
    expect(array_values(array_unique($categories)))->toBe(['GOL', 'Autoimpiego', 'Yisu', 'Autofinanziato', 'Consulenza', 'APL']);

    $golTotal = reportRowsFor($rows, 'GOL')['TOTALE'];
    // D-15 (rev-2, overrides D-9): every numeric cell is '0', applicable or not.
    expect(array_slice($golTotal, 2))->toBe(['0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0']);
});

// ---------------------------------------------------------------------------
// AC-006/AC-007/AC-014
// ---------------------------------------------------------------------------

it('breaks a category down by GA2 sorted by name, with Non assegnato last, TOTALE = sum (AC-006/AC-007/AC-014)', function () {
    $categories = reportCategoryTree();
    $zoe = User::factory()->create(['name' => 'Zoe Bianchi']);
    $ada = User::factory()->create(['name' => 'Ada Rossi']);

    $q1 = reportQuoteWithOpenAdvance($categories['gol'], $ada->id);
    $q2 = reportQuoteWithOpenAdvance($categories['gol'], $zoe->id);
    $q3 = reportQuoteWithOpenAdvance($categories['gol'], null);

    reportNote($q1, now());
    reportNote($q2, now());
    reportNote($q2, now());
    reportNote($q3, now());

    $actor = reportViewAllActor();
    $run = createReportRun($actor, now()->subDay()->toDateString(), now()->addDay()->toDateString());

    runReportJob($run);

    $rows = reportRowsFor(reportCsvRows(Storage::disk('local')->get($run->fresh()->file_path)), 'GOL');

    expect(array_keys($rows))->toBe(['TOTALE', 'Ada Rossi', 'Zoe Bianchi', 'Non assegnato'])
        ->and($rows['Ada Rossi'][2])->toBe('1')
        ->and($rows['Zoe Bianchi'][2])->toBe('2')
        ->and($rows['Non assegnato'][2])->toBe('1')
        ->and($rows['TOTALE'][2])->toBe('4'); // 1 + 2 + 1, AC-014
});

// ---------------------------------------------------------------------------
// AC-008
// ---------------------------------------------------------------------------

it('renders a non-applicable column and an applicable stub identically as 0, never empty (AC-008, D-15 overrides D-9)', function () {
    reportCategoryTree();
    $actor = User::factory()->create();
    $run = createReportRun($actor, '2026-09-01', '2026-09-30');

    runReportJob($run);

    $rows = reportCsvRows(Storage::disk('local')->get($run->fresh()->file_path));

    $golTotal = reportRowsFor($rows, 'GOL')['TOTALE'];
    expect($golTotal[9])->toBe('0'); // Aziende inserite — NOT applicable to GOL, still 0 (D-15)
    expect($golTotal[6])->toBe('0'); // Aule in gestione — applicable stub, explicit 0

    $aplTotal = reportRowsFor($rows, 'APL')['TOTALE'];
    expect($aplTotal[12])->toBe('0'); // Invio Presa in carico — applicable stub
    expect($aplTotal[8])->toBe('0'); // Associati — NOT applicable to APL, still 0 (D-15)

    // The only two text cells left are Categoria/GA2 — no other cell is ever empty.
    foreach (array_slice($golTotal, 2) as $cell) {
        expect($cell)->not->toBe('');
    }
});
