<?php

use App\Enums\ExportStatus;
use App\Jobs\GenerateRequestManagementReportJob;
use App\Models\BusinessFunction;
use App\Models\ExportRun;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\Services\RequestManagement\Report\RequestManagementReportGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0130, AC-009 — POST/GET .../report and GET .../report/dashboard under
// `enrollee-management` require `enrollee-management.report` (never
// `request-management.report`) and aggregate ONLY the requests in the D-2
// (validated/closed_won) + D-5 perimeter, including through the async job.
// An ExportRun created by one module's `report` route is never readable
// through the other.

uses(RefreshDatabase::class);

if (! function_exists('enrolleeStatusId')) {
    function enrolleeStatusId(string $systemKey): int
    {
        return QuoteWorkflowStatus::query()
            ->whereNull('quote_workflow_id')
            ->where('system_key', $systemKey)
            ->value('id')
            ?? QuoteWorkflowStatus::factory()->global()->system($systemKey)->create()->id;
    }
}

if (! function_exists('reportQuote')) {
    /**
     * IDENTICAL signature everywhere in the report test directories: a
     * narrower one here would silently WIN the function_exists guard on
     * whichever file loads first.
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

if (! function_exists('enrolleeReportActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function enrolleeReportActorWith(array $abilities): User
    {
        foreach (['report', 'viewAny', 'viewAll'] as $ability) {
            Permission::findOrCreate("enrollee-management.{$ability}");
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo($ability);
        }

        return $user;
    }
}

if (! function_exists('reportCategoryTree')) {
    /**
     * IDENTICAL signature everywhere in the report test directories:
     * ReportBranchResolver::resolve() eagerly resolves EVERY configured
     * branch, selected or not, so every one of the six roots must exist even
     * when a test only cares about 'apl'.
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

if (! function_exists('enrolleeReportPayload')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function enrolleeReportPayload(array $overrides = []): array
    {
        return array_merge([
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
            'category_keys' => ['apl'],
            'row_mode' => 'total_only',
            'format' => 'csv',
        ], $overrides);
    }
}

// ---------------------------------------------------------------------------
// AC-009 — 403 without enrollee-management.report (create/dashboard)
// ---------------------------------------------------------------------------

it('403s creating the enrollee-management report without enrollee-management.report', function () {
    $actor = enrolleeReportActorWith(['request-management.report', 'request-management.viewAll']);
    Sanctum::actingAs($actor);
    Queue::fake();

    $this->postJson('/api/enrollee-management/report', enrolleeReportPayload())->assertForbidden();
});

it('403s the enrollee-management dashboard without enrollee-management.report', function () {
    $actor = enrolleeReportActorWith(['request-management.report', 'request-management.viewAll']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/enrollee-management/report/dashboard?'.http_build_query(enrolleeReportPayload()))->assertForbidden();
});

it('403s creating the request-management report with ONLY enrollee-management.report (AC-003)', function () {
    $actor = enrolleeReportActorWith(['enrollee-management.report', 'enrollee-management.viewAll']);
    Sanctum::actingAs($actor);
    Queue::fake();

    $this->postJson('/api/request-management/report', enrolleeReportPayload())->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-009 — aggregates ONLY validated/closed_won Iscritti rows in perimeter
// ---------------------------------------------------------------------------

it('the dashboard summary counts only validated/closed_won requests, synchronous', function () {
    $category = reportCategoryTree()['apl'];
    $actor = enrolleeReportActorWith(['enrollee-management.report', 'enrollee-management.viewAll']);

    reportQuote($category, null, enrolleeStatusId('open'));
    reportQuote($category, null, enrolleeStatusId('closed_lost'));
    reportQuote($category, null, enrolleeStatusId('validated'));
    reportQuote($category, null, enrolleeStatusId('closed_won'));

    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/enrollee-management/report/dashboard?'.http_build_query(enrolleeReportPayload()))->assertOk();

    $summary = collect($response->json('data.summary'))->firstWhere('key', 'telefonate');
    expect($summary)->not->toBeNull();

    $categorySection = collect($response->json('data.categories'))->firstWhere('key', 'apl');
    expect($categorySection)->not->toBeNull();
});

it('the CSV export row_count reflects only the D-5 perimeter, on top of the D-2 status filter', function () {
    Storage::fake('local');
    $category = reportCategoryTree()['apl'];
    $actor = enrolleeReportActorWith(['enrollee-management.report', 'enrollee-management.viewAny']);

    reportQuote($category, $actor->id, enrolleeStatusId('closed_won'));
    reportQuote($category, User::factory()->create()->id, enrolleeStatusId('closed_won')); // someone else's, out of D-5
    reportQuote($category, $actor->id, enrolleeStatusId('open')); // out of D-2

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/enrollee-management/report', enrolleeReportPayload(['row_mode' => 'all']))->assertCreated();
    $run = ExportRun::findOrFail($response->json('data.export_run.id'));

    expect($run->resource)->toBe('enrollee-management-report')
        ->and($run->state['module'] ?? null)->toBe('enrollee-management');

    (new GenerateRequestManagementReportJob($run->id))->handle(app(RequestManagementReportGenerator::class));

    $csv = Storage::disk('local')->get($run->fresh()->file_path);
    $lines = array_filter(explode("\n", trim($csv, "\xEF\xBB\xBF\n")));
    expect($lines)->toHaveCount(2); // header + the one TOTALE row in scope
});

// ---------------------------------------------------------------------------
// AC-009 — the async job reads the frozen module, never request-management's
// ---------------------------------------------------------------------------

it('a run frozen with no module key (pre-0130) is generated as request-management, never enrollee-management (parity)', function () {
    Storage::fake('local');
    $category = reportCategoryTree()['apl'];
    $actor = User::factory()->create();
    Permission::findOrCreate('request-management.viewAll');
    $actor->givePermissionTo('request-management.viewAll');

    reportQuote($category, null, enrolleeStatusId('open'));

    $run = ExportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'request-management-report',
        'state' => [
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
            'locale' => 'it',
            'category_keys' => ['apl'],
            'row_mode' => 'total_only',
            // No 'module' key: the run predates spec 0130.
        ],
    ]);

    (new GenerateRequestManagementReportJob($run->id))->handle(app(RequestManagementReportGenerator::class));

    $csv = Storage::disk('local')->get($run->fresh()->file_path);
    $lines = array_filter(explode("\n", trim($csv, "\xEF\xBB\xBF\n")));
    // The 'open' quote is visible: no D-2 status filter under request-management.
    expect($lines)->toHaveCount(2);
});

// ---------------------------------------------------------------------------
// AC-009 — an ExportRun created by one module's report route is not readable
// through the other
// ---------------------------------------------------------------------------

it('404s (never 403) polling a request-management report run through the enrollee-management route', function () {
    $actor = enrolleeReportActorWith(['enrollee-management.report']);
    $run = ExportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'request-management-report',
        'status' => ExportStatus::Processing,
    ]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/enrollee-management/report/{$run->id}")->assertNotFound();
});

it('404s (never 403) polling an enrollee-management report run through the request-management route', function () {
    $actor = enrolleeReportActorWith(['request-management.report']);
    $run = ExportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'enrollee-management-report',
        'status' => ExportStatus::Processing,
    ]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/report/{$run->id}")->assertNotFound();
});
