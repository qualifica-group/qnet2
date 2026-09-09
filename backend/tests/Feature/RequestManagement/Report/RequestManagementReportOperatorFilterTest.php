<?php

use App\Enums\ExportFormat;
use App\Enums\RequestManagementReportRowMode;
use App\Jobs\GenerateRequestManagementReportJob;
use App\Models\BusinessFunction;
use App\Models\ExportRun;
use App\Models\Note;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\User;
use App\Services\RequestManagement\Report\Dashboard\RequestManagementDashboardBuilder;
use App\Services\RequestManagement\Report\ReportOperatorFilter;
use App\Services\RequestManagement\Report\RequestManagementReportGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

// Spec 0108 — the operator filter RESTRICTS THE CALCULATION (D-1), it does
// not hide rows after the fact: AC-002..AC-006 and AC-014..AC-016.

uses(RefreshDatabase::class);

if (! function_exists('operatorFilterFixture')) {
    /**
     * Two GA2 plus one unassigned request on GOL, one on Consulenza, each
     * with one note in range — a phone call only where the request has a GA2
     * to have written it (rev-3 D-17), so GOL totals 3, not 4. Returns the
     * actors and the branch categories the assertions address.
     *
     * @return array{ada: User, zoe: User, gol: ProductCategory, consulenza: ProductCategory}
     */
    function operatorFilterFixture(): array
    {
        $formazione = ProductCategory::factory()->create(['name' => 'Formazione']);
        $gol = ProductCategory::factory()->childOf($formazione)->create(['name' => 'GOL']);
        ProductCategory::factory()->childOf($formazione)->create(['name' => 'Autoimpiego']);
        ProductCategory::factory()->childOf($formazione)->create(['name' => 'Yisu']);
        ProductCategory::factory()->childOf($formazione)->create(['name' => 'Autofinanziato']);
        $consulenza = ProductCategory::factory()->create(['name' => 'Consulenza']);
        ProductCategory::factory()->create(['name' => 'APL']);

        $ada = User::factory()->create(['name' => 'Ada Rossi']);
        $zoe = User::factory()->create(['name' => 'Zoe Bianchi']);

        operatorFilterCall(operatorFilterQuote($gol, $ada->id));
        operatorFilterCall(operatorFilterQuote($gol, $ada->id));
        operatorFilterCall(operatorFilterQuote($gol, $zoe->id));
        operatorFilterCall(operatorFilterQuote($gol, null));
        operatorFilterCall(operatorFilterQuote($consulenza, $ada->id));

        return ['ada' => $ada, 'zoe' => $zoe, 'gol' => $gol, 'consulenza' => $consulenza];
    }
}

if (! function_exists('operatorFilterQuote')) {
    /**
     * A request on the FIRST workflow state with a callback due in range: its
     * notes count as phone calls all the same since rev-3 (D-16), and
     * "N. Richiami non gestiti" gives it a GA2 row even when it has no
     * operator at all — which a note alone can no longer do (D-17).
     */
    function operatorFilterQuote(ProductCategory $category, ?int $operatorId): Quote
    {
        $opportunity = Opportunity::factory()->create();
        OpportunityProductLine::factory()->create([
            'opportunity_id' => $opportunity->id,
            'business_function_id' => BusinessFunction::factory()->create()->id,
            'product_category_id' => $category->id,
        ]);

        return Quote::factory()
            ->create(['opportunity_id' => $opportunity->id])
            ->forceFill(['operator_id' => $operatorId, 'next_callback_at' => now()]);
    }
}

if (! function_exists('operatorFilterCall')) {
    /** The note is written BY the request's own GA2: the only phone call rev-3 counts (D-17). */
    function operatorFilterCall(Quote $quote): void
    {
        $quote->save();

        Note::factory()->create([
            'notable_type' => 'opportunity',
            'notable_id' => $quote->opportunity_id,
            'created_at' => now(),
            ...($quote->operator_id !== null ? ['user_id' => $quote->operator_id] : []),
        ])->forceFill(['quote_id' => $quote->id])->save();
    }
}

if (! function_exists('operatorFilterActor')) {
    function operatorFilterActor(): User
    {
        Permission::findOrCreate('request-management.viewAll');
        $actor = User::factory()->create();
        $actor->givePermissionTo('request-management.viewAll');

        return $actor;
    }
}

if (! function_exists('operatorFilterCsv')) {
    /**
     * @return array<int, array<int, string>>
     */
    function operatorFilterCsv(
        User $actor,
        RequestManagementReportRowMode $rowMode,
        ?ReportOperatorFilter $operators,
        string $file = 'operator-filter.csv',
    ): array {
        $path = Storage::disk('local')->path($file);

        app(RequestManagementReportGenerator::class)->generate(
            $actor,
            now()->subDay()->toDateString(),
            now()->addDay()->toDateString(),
            array_keys((array) config('request-management-report.branches')),
            $rowMode,
            ExportFormat::Csv,
            $path,
            $operators,
        );

        $lines = array_filter(explode("\n", trim(Storage::disk('local')->get($file), "\xEF\xBB\xBF\n")));

        return array_map(static fn (string $line): array => str_getcsv($line, ',', '"', ''), $lines);
    }
}

if (! function_exists('operatorFilterCell')) {
    /**
     * @param  array<int, array<int, string>>  $rows
     */
    function operatorFilterCell(array $rows, string $categoryLabel, string $ga2Label, string $indicatorKey): int
    {
        $columnIndex = array_search($indicatorKey, (array) config('request-management-report.indicator_columns'), true) + 2;

        foreach ($rows as $row) {
            if (($row[0] ?? null) === $categoryLabel && ($row[1] ?? null) === $ga2Label) {
                return (int) $row[$columnIndex];
            }
        }

        throw new RuntimeException("No CSV row for [{$categoryLabel}/{$ga2Label}].");
    }
}

if (! function_exists('operatorFilterGa2Labels')) {
    /**
     * @param  array<int, array<int, string>>  $rows
     * @return array<int, string>
     */
    function operatorFilterGa2Labels(array $rows, string $categoryLabel): array
    {
        $labels = [];

        foreach (array_slice($rows, 1) as $row) { // slice off the header row
            if (($row[0] ?? null) === $categoryLabel) {
                $labels[] = $row[1];
            }
        }

        return $labels;
    }
}

// ---------------------------------------------------------------------------
// AC-002 / AC-003 — the filter narrows the calculation, not just the rows
// ---------------------------------------------------------------------------

it('emits only the selected operator row per branch with operators_only (AC-002)', function () {
    Storage::fake('local');
    $fixture = operatorFilterFixture();

    $rows = operatorFilterCsv(
        operatorFilterActor(),
        RequestManagementReportRowMode::OperatorsOnly,
        ReportOperatorFilter::fromKeys([(string) $fixture['ada']->id]),
    );

    expect(operatorFilterGa2Labels($rows, 'GOL'))->toBe(['Ada Rossi'])
        ->and(operatorFilterGa2Labels($rows, 'Consulenza'))->toBe(['Ada Rossi']);
});

it('makes the TOTALE row equal the selected operator row with row_mode all (AC-003)', function () {
    Storage::fake('local');
    $fixture = operatorFilterFixture();
    app()->setLocale('it');

    $rows = operatorFilterCsv(
        operatorFilterActor(),
        RequestManagementReportRowMode::All,
        ReportOperatorFilter::fromKeys([(string) $fixture['ada']->id]),
    );

    // Ada has two GOL calls; the branch total collapses onto hers, and Zoe's
    // and the unassigned request's calls are gone from the calculation.
    expect(operatorFilterCell($rows, 'GOL', 'TOTALE', 'telefonate'))->toBe(2)
        ->and(operatorFilterCell($rows, 'GOL', 'Ada Rossi', 'telefonate'))->toBe(2)
        ->and(operatorFilterGa2Labels($rows, 'GOL'))->toBe(['TOTALE', 'Ada Rossi']);
});

it('leaves every value untouched when no filter is passed (AC-001)', function () {
    Storage::fake('local');
    operatorFilterFixture();
    app()->setLocale('it');

    $rows = operatorFilterCsv(operatorFilterActor(), RequestManagementReportRowMode::All, null);

    expect(operatorFilterCell($rows, 'GOL', 'TOTALE', 'telefonate'))->toBe(3) // the unassigned request contributes 0 (rev-3 D-17)
        ->and(operatorFilterGa2Labels($rows, 'GOL'))->toBe(['TOTALE', 'Ada Rossi', 'Zoe Bianchi', 'Non assegnato']);
});

// ---------------------------------------------------------------------------
// AC-004 — the dashboard summary narrows too
// ---------------------------------------------------------------------------

it('narrows the dashboard summary tiles to the selected operators (AC-004)', function () {
    $fixture = operatorFilterFixture();
    $actor = operatorFilterActor();
    $categoryKeys = array_keys((array) config('request-management-report.branches'));

    $unfiltered = app(RequestManagementDashboardBuilder::class)
        ->build($actor, now()->subDay()->toDateString(), now()->addDay()->toDateString(), $categoryKeys, RequestManagementReportRowMode::All);

    $filtered = app(RequestManagementDashboardBuilder::class)
        ->build(
            $actor,
            now()->subDay()->toDateString(),
            now()->addDay()->toDateString(),
            $categoryKeys,
            RequestManagementReportRowMode::All,
            ReportOperatorFilter::fromKeys([(string) $fixture['zoe']->id]),
        );

    $tile = static fn ($result): int => collect($result->summary)->firstWhere('key', 'telefonate')->value;

    expect($tile($unfiltered))->toBe(4) // 3 GOL + 1 Consulenza
        ->and($tile($filtered))->toBe(1); // Zoe's single GOL call
});

// ---------------------------------------------------------------------------
// AC-005 — "Non assegnato" is selectable like any other GA2
// ---------------------------------------------------------------------------

it('includes only unassigned requests when unassigned is the sole selection (AC-005)', function () {
    Storage::fake('local');
    operatorFilterFixture();
    app()->setLocale('it');

    $rows = operatorFilterCsv(
        operatorFilterActor(),
        RequestManagementReportRowMode::All,
        ReportOperatorFilter::fromKeys([ReportOperatorFilter::UNASSIGNED_KEY]),
    );

    // "telefonate" is 0 for an unassigned request by construction (rev-3 D-17),
    // so the narrowing is asserted on the indicator that DOES count it.
    expect(operatorFilterGa2Labels($rows, 'GOL'))->toBe(['TOTALE', 'Non assegnato'])
        ->and(operatorFilterCell($rows, 'GOL', 'TOTALE', 'richiami'))->toBe(1)
        ->and(operatorFilterCell($rows, 'GOL', 'TOTALE', 'telefonate'))->toBe(0);
});

it('excludes unassigned requests when the key is not selected (AC-005)', function () {
    Storage::fake('local');
    $fixture = operatorFilterFixture();
    app()->setLocale('it');

    $rows = operatorFilterCsv(
        operatorFilterActor(),
        RequestManagementReportRowMode::All,
        ReportOperatorFilter::fromKeys([(string) $fixture['ada']->id, (string) $fixture['zoe']->id]),
    );

    expect(operatorFilterGa2Labels($rows, 'GOL'))->toBe(['TOTALE', 'Ada Rossi', 'Zoe Bianchi'])
        ->and(operatorFilterCell($rows, 'GOL', 'TOTALE', 'telefonate'))->toBe(3);
});

if (! function_exists('operatorFilterIndicatorKeyOf')) {
    /** The indicator key whose CSV header is $label — the dashboard labels its indicator bars with it. */
    function operatorFilterIndicatorKeyOf(string $label): string
    {
        foreach ((array) config('request-management-report.indicator_columns') as $key) {
            if (__("request-management-report.headers.{$key}") === $label) {
                return $key;
            }
        }

        throw new RuntimeException("No indicator column labelled [{$label}].");
    }
}

// ---------------------------------------------------------------------------
// AC-006 — CSV/dashboard parity holds UNDER the filter too
// ---------------------------------------------------------------------------

it('keeps every dashboard point equal to its CSV cell under an operator filter (AC-006)', function () {
    Storage::fake('local');
    $fixture = operatorFilterFixture();
    $actor = operatorFilterActor();
    app()->setLocale('it');

    $operators = ReportOperatorFilter::fromKeys([
        (string) $fixture['ada']->id,
        ReportOperatorFilter::UNASSIGNED_KEY,
    ]);

    $rows = operatorFilterCsv($actor, RequestManagementReportRowMode::All, $operators, 'parity-filtered.csv');

    $result = app(RequestManagementDashboardBuilder::class)->build(
        $actor,
        now()->subDay()->toDateString(),
        now()->addDay()->toDateString(),
        array_keys((array) config('request-management-report.branches')),
        RequestManagementReportRowMode::All,
        $operators,
    );

    expect($result->categories)->not->toBeEmpty();

    foreach ($result->categories as $category) {
        foreach ($category->charts as $chart) {
            foreach ($chart->points as $point) {
                $isIndicatorScope = $chart->indicatorKey === null;
                $ga2Label = $isIndicatorScope ? 'TOTALE' : $point->label;
                $indicatorKey = $isIndicatorScope ? operatorFilterIndicatorKeyOf($point->label) : $chart->indicatorKey;

                expect($point->value)->toBe(operatorFilterCell($rows, $category->label, $ga2Label, $indicatorKey));
            }
        }
    }
});

// ---------------------------------------------------------------------------
// AC-014 / AC-015 — the frozen ExportRun state
// ---------------------------------------------------------------------------

it('generates on every operator for a run whose state has no operator_keys (AC-014)', function () {
    Storage::fake('local');
    operatorFilterFixture();
    app()->setLocale('it');

    $run = ExportRun::factory()->create([
        'user_id' => operatorFilterActor()->id,
        'resource' => 'request-management-report',
        'state' => [
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
            'locale' => 'it',
            'category_keys' => array_keys((array) config('request-management-report.branches')),
            'row_mode' => 'all',
            // no operator_keys: the shape every run frozen before spec 0108 has
        ],
    ]);

    (new GenerateRequestManagementReportJob($run->id))->handle(app(RequestManagementReportGenerator::class));

    $rows = array_map(
        static fn (string $line): array => str_getcsv($line, ',', '"', ''),
        array_filter(explode("\n", trim(Storage::disk('local')->get($run->fresh()->file_path), "\xEF\xBB\xBF\n"))),
    );

    expect(operatorFilterGa2Labels($rows, 'GOL'))->toBe(['TOTALE', 'Ada Rossi', 'Zoe Bianchi', 'Non assegnato']);
});

it('re-reads the frozen operator_keys when the job runs (AC-015)', function () {
    Storage::fake('local');
    $fixture = operatorFilterFixture();
    app()->setLocale('it');

    $run = ExportRun::factory()->create([
        'user_id' => operatorFilterActor()->id,
        'resource' => 'request-management-report',
        'state' => [
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
            'locale' => 'it',
            'category_keys' => array_keys((array) config('request-management-report.branches')),
            'row_mode' => 'all',
            'operator_keys' => [(string) $fixture['zoe']->id],
        ],
    ]);

    (new GenerateRequestManagementReportJob($run->id))->handle(app(RequestManagementReportGenerator::class));

    $rows = array_map(
        static fn (string $line): array => str_getcsv($line, ',', '"', ''),
        array_filter(explode("\n", trim(Storage::disk('local')->get($run->fresh()->file_path), "\xEF\xBB\xBF\n"))),
    );

    expect(operatorFilterGa2Labels($rows, 'GOL'))->toBe(['TOTALE', 'Zoe Bianchi']);
});

// ---------------------------------------------------------------------------
// AC-016 — no indicator learned anything about operators
// ---------------------------------------------------------------------------

it('keeps the operator condition out of every indicator (AC-016)', function () {
    // Spec 0106 rev-3 (D-17) made "N. Telefonate Effettuate" DEPEND on the
    // GA2 column: it compares two columns of the same row (`whereColumn`) to
    // define what a phone call is. That is the indicator's own formula, not
    // the operator SELECTION this AC guards — which is why it is excluded
    // here by name and checked separately below.
    $phoneCalls = app_path('Services/RequestManagement/Report/Indicators/PhoneCallsIndicator.php');

    foreach (glob(app_path('Services/RequestManagement/Report/Indicators/*.php')) as $file) {
        if ($file !== $phoneCalls) {
            expect(file_get_contents($file))->not->toContain('operator_id');
        }
    }

    expect(file_get_contents($phoneCalls))
        ->toContain("whereColumn('notes.user_id', 'quotes.operator_id')")
        ->not->toContain('operator_keys');

    // The one place that knows the column, and the one place that applies it.
    expect(file_get_contents(app_path('Services/RequestManagement/Report/ReportOperatorFilter.php')))
        ->toContain("'quotes.operator_id'")
        ->and(file_get_contents(app_path('Services/RequestManagement/Report/ReportBranchQuery.php')))
        ->toContain('$operators->applyTo(');
});
