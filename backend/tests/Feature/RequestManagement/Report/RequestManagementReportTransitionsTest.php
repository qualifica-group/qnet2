<?php

use App\Enums\PersonalDataTypeEnum;
use App\Enums\WorkflowStatusGroup;
use App\Jobs\GenerateRequestManagementReportJob;
use App\Models\BusinessFunction;
use App\Models\EmploymentProfile;
use App\Models\ExportRun;
use App\Models\Note;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\PersonalData;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowStatus;
use App\Models\Registry;
use App\Models\User;
use App\Services\RequestManagement\Report\RequestManagementReportGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

// RequestManagementReportGenerator (spec 0106): "Aziende inserite" (AC-015/
// 015-bis), multi-category dedup + descendant expansion (AC-016/017),
// activity_log transition indicators + D-3 disambiguation (AC-018/019/020),
// RequestManagementScope (AC-021), inclusive range (AC-022).

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

if (! function_exists('reportTransitionLog')) {
    /**
     * Writes an activity_log row exactly as
     * RequestManagementService::logOperationalChange() would, for
     * $opportunity — the D-3 shape (log_name/subject_type/event/
     * properties.attributes.quote_workflow_status_id).
     */
    function reportTransitionLog(Opportunity $opportunity, int $targetStatusId, Carbon $createdAt): void
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

if (! function_exists('reportCompanyRegistry')) {
    function reportCompanyRegistry(Carbon $createdAt): Registry
    {
        $registry = Registry::factory()->create(['created_at' => $createdAt]);
        PersonalData::factory()->company()->create([
            'personable_type' => 'registry',
            'personable_id' => $registry->id,
            'type' => PersonalDataTypeEnum::Company->value,
        ]);

        return $registry;
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
// AC-015/AC-015-bis — "Aziende inserite"
// ---------------------------------------------------------------------------

it('dedups a company linked to two operators (total < sum) and skips a registry with no personal_data (AC-015/AC-015-bis)', function () {
    $categories = reportCategoryTree();
    $opA = User::factory()->create(['name' => 'Operator A']);
    $opB = User::factory()->create(['name' => 'Operator B']);

    $registry = reportCompanyRegistry(Carbon::parse('2026-09-10'));
    reportQuote($categories['consulenza'], $opA->id, null, Opportunity::factory()->create(['registry_id' => $registry->id]));
    reportQuote($categories['consulenza'], $opB->id, null, Opportunity::factory()->create(['registry_id' => $registry->id]));

    // AC-015-bis: a registry with NO personal_data card must not break the query.
    $bareRegistry = Registry::factory()->create(['created_at' => Carbon::parse('2026-09-11')]);
    reportQuote($categories['consulenza'], $opA->id, null, Opportunity::factory()->create(['registry_id' => $bareRegistry->id]));

    $actor = reportViewAllActor();
    $run = createReportRun($actor, '2026-09-01', '2026-09-30');

    runReportJob($run);

    $rows = reportRowsFor(reportCsvRows(Storage::disk('local')->get($run->fresh()->file_path)), 'Consulenza');

    expect($rows['Operator A'][9])->toBe('1')
        ->and($rows['Operator B'][9])->toBe('1')
        ->and($rows['TOTALE'][9])->toBe('1'); // deduped, less than the sum of the GA2 rows (2)
});

// ---------------------------------------------------------------------------
// AC-016/AC-017 — multi-category dedup + descendant expansion
// ---------------------------------------------------------------------------

it('counts a request once per DISTINCT category, and once even with two lines on the SAME category (AC-016)', function () {
    $categories = reportCategoryTree();

    reportNote(reportQuoteWithOpenAdvance($categories['gol']), now()); // unrelated noise on GOL

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
    $custom = QuoteWorkflowStatus::factory()->global()->create(['system_key' => null, 'group' => WorkflowStatusGroup::Open]);
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id, 'quote_workflow_status_id' => $custom->id]);
    reportNote($quote, now());

    $actor = reportViewAllActor();
    $run = createReportRun($actor, now()->subDay()->toDateString(), now()->addDay()->toDateString());

    runReportJob($run);

    $rows = reportCsvRows(Storage::disk('local')->get($run->fresh()->file_path));
    $gol = reportRowsFor($rows, 'GOL')['TOTALE'];
    $autoimpiego = reportRowsFor($rows, 'Autoimpiego')['TOTALE'];

    expect($gol[2])->toBe('2') // the noise quote's own note + this one — counted once here, not duplicated by its own two product lines
        ->and($autoimpiego[2])->toBe('1'); // and again once in the OTHER category
});

it('classifies a request on a descendant category under its branch root (AC-017)', function () {
    $categories = reportCategoryTree();
    $child = ProductCategory::factory()->childOf($categories['gol'])->create(['name' => 'GOL - Lombardia']);

    reportNote(reportQuoteWithOpenAdvance($child), now());

    $actor = reportViewAllActor();
    $run = createReportRun($actor, now()->subDay()->toDateString(), now()->addDay()->toDateString());

    runReportJob($run);

    $rows = reportRowsFor(reportCsvRows(Storage::disk('local')->get($run->fresh()->file_path)), 'GOL');

    expect($rows['TOTALE'][2])->toBe('1');
});

// ---------------------------------------------------------------------------
// AC-018/AC-019/AC-020 — transition indicators (potenziali/associati) + D-3
// ---------------------------------------------------------------------------

it('counts a transition to pending/validated only when logged inside the range (AC-018)', function () {
    $categories = reportCategoryTree();
    $workflow = QuoteWorkflow::factory()->create();
    $pending = QuoteWorkflowStatus::factory()->for($workflow, 'workflow')->create(['group' => WorkflowStatusGroup::Pending]);

    $inRange = reportQuote($categories['gol'], null, $pending->id);
    reportTransitionLog($inRange->opportunity, $pending->id, Carbon::parse('2026-09-10'));

    $outOfRange = reportQuote($categories['gol'], null, $pending->id);
    reportTransitionLog($outOfRange->opportunity, $pending->id, Carbon::parse('2026-08-01'));

    $actor = reportViewAllActor();
    $run = createReportRun($actor, '2026-09-01', '2026-09-30');

    runReportJob($run);

    $rows = reportRowsFor(reportCsvRows(Storage::disk('local')->get($run->fresh()->file_path)), 'GOL');

    expect($rows['TOTALE'][5])->toBe('1');
});

it('counts a transition to closed_won only when logged inside the range, under Associati/Trattative Concluse (AC-019)', function () {
    $categories = reportCategoryTree();
    $workflow = QuoteWorkflow::factory()->create();
    $wonStatus = QuoteWorkflowStatus::factory()->for($workflow, 'workflow')->create(['group' => WorkflowStatusGroup::ClosedWon]);

    // "Associati" (GOL — applicable there): same formula, checked separately
    // since the two columns never share a branch's applicability list.
    $wonGol = reportQuote($categories['gol'], null, $wonStatus->id);
    reportTransitionLog($wonGol->opportunity, $wonStatus->id, Carbon::parse('2026-09-10'));

    // "Trattative Concluse" (Consulenza — applicable there).
    $wonInRange = reportQuote($categories['consulenza'], null, $wonStatus->id);
    reportTransitionLog($wonInRange->opportunity, $wonStatus->id, Carbon::parse('2026-09-10'));

    // Chiusa positiva OGGI, ma la transizione loggata e' PRIMA del range: non contata.
    $wonBeforeRange = reportQuote($categories['consulenza'], null, $wonStatus->id);
    reportTransitionLog($wonBeforeRange->opportunity, $wonStatus->id, Carbon::parse('2026-08-01'));

    $actor = reportViewAllActor();
    $run = createReportRun($actor, '2026-09-01', '2026-09-30');

    runReportJob($run);

    $rows = reportCsvRows(Storage::disk('local')->get($run->fresh()->file_path));

    expect(reportRowsFor($rows, 'GOL')['TOTALE'][8])->toBe('1') // Associati
        ->and(reportRowsFor($rows, 'Consulenza')['TOTALE'][11])->toBe('1'); // Trattative Concluse — same formula, same value
});

it('attributes a logged transition to the offer on the SAME workflow only (two offers, two workflows) (AC-020)', function () {
    $categories = reportCategoryTree();

    $workflowA = QuoteWorkflow::factory()->create();
    $wonA = QuoteWorkflowStatus::factory()->for($workflowA, 'workflow')->create(['group' => WorkflowStatusGroup::ClosedWon]);

    $workflowB = QuoteWorkflow::factory()->create();
    $wonB = QuoteWorkflowStatus::factory()->for($workflowB, 'workflow')->create(['group' => WorkflowStatusGroup::ClosedWon]);

    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => BusinessFunction::factory()->create()->id,
        'product_category_id' => $categories['consulenza']->id,
    ]);

    Quote::factory()->create(['opportunity_id' => $opportunity->id, 'quote_workflow_status_id' => $wonA->id]);
    Quote::factory()->create(['opportunity_id' => $opportunity->id, 'quote_workflow_status_id' => $wonB->id]);

    // A single logged transition toward workflow A's own closed_won status.
    reportTransitionLog($opportunity, $wonA->id, Carbon::parse('2026-09-10'));

    $actor = reportViewAllActor();
    $run = createReportRun($actor, '2026-09-01', '2026-09-30');

    runReportJob($run);

    $rows = reportRowsFor(reportCsvRows(Storage::disk('local')->get($run->fresh()->file_path)), 'Consulenza');

    // Exactly ONE request counted (the offer on workflow A), not two.
    expect($rows['TOTALE'][11])->toBe('1');
});

// ---------------------------------------------------------------------------
// AC-021 — RequestManagementScope respected
// ---------------------------------------------------------------------------

it('never leaks a request outside the frozen actor visibility scope (AC-021)', function () {
    $categories = reportCategoryTree();

    $operator = User::factory()->create();
    reportNote(reportQuoteWithOpenAdvance($categories['gol'], $operator->id), now());
    reportNote(reportQuoteWithOpenAdvance($categories['gol'], User::factory()->create()->id), now());

    $run = createReportRun($operator, now()->subDay()->toDateString(), now()->addDay()->toDateString());

    runReportJob($run);

    $rows = reportRowsFor(reportCsvRows(Storage::disk('local')->get($run->fresh()->file_path)), 'GOL');

    expect($rows['TOTALE'][2])->toBe('1');
    expect($rows)->not->toHaveKey('Non assegnato');
});

it('a viewSite actor (not the operator) sees requests of their own Sede, not another Sede, and never a Sede-less one (AC-021, viewSite tier)', function () {
    $categories = reportCategoryTree();
    Permission::findOrCreate('request-management.viewSite');

    $mySite = OperationalSite::factory()->create();
    $otherSite = OperationalSite::factory()->create();

    $actor = User::factory()->create();
    $actor->givePermissionTo('request-management.viewSite');
    EmploymentProfile::factory()->physicalSite($mySite)->create(['user_id' => $actor->id]);

    $otherOperator = User::factory()->create();

    // In the actor's OWN Sede, operated by someone else -> visible (D-1/D-4).
    $inMySite = reportQuoteWithOpenAdvance($categories['gol'], $otherOperator->id);
    $inMySite->forceFill(['operational_site_id' => $mySite->id])->save();
    reportNote($inMySite, now());

    // In ANOTHER Sede, not operated by the actor -> invisible.
    $elsewhere = reportQuoteWithOpenAdvance($categories['gol'], $otherOperator->id);
    $elsewhere->forceFill(['operational_site_id' => $otherSite->id])->save();
    reportNote($elsewhere, now());

    // No Sede at all -> out of tier 3 for everyone, fail-closed (D-3).
    $noSite = reportQuoteWithOpenAdvance($categories['gol'], $otherOperator->id);
    reportNote($noSite, now());

    $run = createReportRun($actor, now()->subDay()->toDateString(), now()->addDay()->toDateString());

    runReportJob($run);

    $rows = reportRowsFor(reportCsvRows(Storage::disk('local')->get($run->fresh()->file_path)), 'GOL');

    expect($rows['TOTALE'][2])->toBe('1');
});

// ---------------------------------------------------------------------------
// AC-022 — inclusive range, both ends
// ---------------------------------------------------------------------------

it('includes a note created at 23:30 on the date_to day (AC-022)', function () {
    $categories = reportCategoryTree();
    reportNote(reportQuoteWithOpenAdvance($categories['gol']), Carbon::parse('2026-09-30 23:30:00'));

    $actor = reportViewAllActor();
    $run = createReportRun($actor, '2026-09-01', '2026-09-30');

    runReportJob($run);

    $rows = reportRowsFor(reportCsvRows(Storage::disk('local')->get($run->fresh()->file_path)), 'GOL');

    expect($rows['TOTALE'][2])->toBe('1');
});
