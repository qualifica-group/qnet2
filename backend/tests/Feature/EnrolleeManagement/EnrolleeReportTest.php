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
use App\RequestManagement\RequestModule;
use App\Services\RequestManagement\Report\RequestManagementReportGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0185 D-1 (replaces spec 0130 AC-009, requirement changed): Gestione
// Iscritti has no statistics any more — none of the report/dashboard routes
// exists under `enrollee-management`, whatever the actor holds. The async job
// keeps reading a frozen run's module, and a run of one resource is still
// never readable through another's route.

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

if (! function_exists('enrolleeStatisticsActor')) {
    /** Every grant that ever opened the statistics, so a 404 can only come from the missing route. */
    function enrolleeStatisticsActor(): User
    {
        $permissions = [RequestModule::STATISTICS_PERMISSION, 'enrollee-management.viewAny', 'enrollee-management.viewAll'];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        return User::factory()->create()->givePermissionTo($permissions);
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
            'gol' => ProductCategory::factory()->childOf($formazione)->reportable()->create(['name' => 'GOL']),
            'autoimpiego' => ProductCategory::factory()->childOf($formazione)->reportable()->create(['name' => 'Autoimpiego']),
            'yisu' => ProductCategory::factory()->childOf($formazione)->reportable()->create(['name' => 'Yisu']),
            'autofinanziato' => ProductCategory::factory()->childOf($formazione)->reportable()->create(['name' => 'Autofinanziato']),
            'consulenza' => ProductCategory::factory()->reportable()->create(['name' => 'Consulenza']),
            'apl' => ProductCategory::factory()->reportable()->create(['name' => 'APL']),
        ];
    }
}

// ---------------------------------------------------------------------------
// D-1 — no statistics route under enrollee-management
// ---------------------------------------------------------------------------

it('404s every statistics read under enrollee-management', function (string $path) {
    Sanctum::actingAs(enrolleeStatisticsActor());

    $this->getJson("/api/enrollee-management/report/{$path}")->assertNotFound();
})->with(['dashboard', 'categories', 'operators', 'sites', '1', '1/download']);

it('never creates a report under enrollee-management', function () {
    Sanctum::actingAs(enrolleeStatisticsActor());
    Queue::fake();

    // `enrollee-management/report` only matches the work panel's `{quote}`
    // routes, none of which accepts a POST.
    $this->postJson('/api/enrollee-management/report', [])->assertMethodNotAllowed();
    Queue::assertNothingPushed();
});

// ---------------------------------------------------------------------------
// The async job reads the frozen module, never request-management's
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
            'category_keys' => [(string) $category->id],
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
// An ExportRun of another resource is not readable through the
// request-management route
// ---------------------------------------------------------------------------

it('404s (never 403) polling an enrollee-management report run through the request-management route', function () {
    $actor = enrolleeStatisticsActor();
    $run = ExportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'enrollee-management-report',
        'status' => ExportStatus::Processing,
    ]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/report/{$run->id}")->assertNotFound();
});
