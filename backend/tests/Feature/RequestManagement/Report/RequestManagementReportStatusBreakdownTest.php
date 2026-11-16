<?php

declare(strict_types=1);

use App\Enums\RequestManagementReportRowMode;
use App\Enums\WorkflowStatusGroup;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\Services\RequestManagement\Report\Dashboard\RequestManagementDashboardBuilder;
use App\Services\RequestManagement\Report\Dashboard\RequestManagementDashboardResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;

/**
 * Task 427193: every column counting requests (or their notes) splits its
 * tile and each operator of the ranking by current working status; "Aziende
 * inserite" (companies, not requests) does not. Range 2026-09-01 .. 2026-09-30.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-09-18 10:00:00');
    $this->category = ProductCategory::factory()->reportable()->create(['name' => 'ISO']);
    $this->workflow = QuoteWorkflow::factory()->create();
    $this->waiting = QuoteWorkflowStatus::factory()->for($this->workflow, 'workflow')->create(['group' => WorkflowStatusGroup::Pending, 'name' => 'Attesa DOC']);
    $this->validated = QuoteWorkflowStatus::factory()->for($this->workflow, 'workflow')->create(['group' => WorkflowStatusGroup::Validated, 'name' => 'Validato']);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function breakdownQuote(ProductCategory $category, QuoteWorkflowStatus $status, ?User $operator = null): Quote
{
    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => BusinessFunction::factory()->create()->id,
        'product_category_id' => $category->id,
    ]);

    return Quote::factory()->create(['opportunity_id' => $opportunity->id, 'quote_workflow_status_id' => $status->id, 'operator_id' => $operator?->id]);
}

function breakdownDashboard(ProductCategory $category, RequestManagementReportRowMode $rowMode): RequestManagementDashboardResult
{
    Permission::findOrCreate('request-management.viewAll');
    $actor = User::factory()->create();
    $actor->givePermissionTo('request-management.viewAll');

    return app(RequestManagementDashboardBuilder::class)->build($actor, '2026-09-01', '2026-09-30', [(string) $category->id], $rowMode);
}

it('splits the Potenziali tile by current status, largest first; a column with nothing counted has no split', function (): void {
    foreach ([$this->validated, $this->waiting, $this->waiting] as $status) {
        breakdownQuote($this->category, $status);
    }

    // Spec 0391: the columns count only configured statuses.
    configureReportColumnsByGroup();

    $tiles = collect(breakdownDashboard($this->category, RequestManagementReportRowMode::TotalOnly)->summary)->keyBy('key');

    expect($tiles['current_potentials']->breakdown)->toBe([['label' => 'Attesa DOC', 'value' => 2], ['label' => 'Validato', 'value' => 1]])
        ->and($tiles['associati']->breakdown)->toBe([])
        ->and($tiles['telefonate']->breakdown)->toBe([]);
});

it('gives each operator of the ranking their own split by status', function (): void {
    $anna = User::factory()->create(['name' => 'Anna']);
    $bruno = User::factory()->create(['name' => 'Bruno']);
    breakdownQuote($this->category, $this->waiting, $anna);
    breakdownQuote($this->category, $this->validated, $anna);
    breakdownQuote($this->category, $this->waiting, $bruno);

    // Spec 0391: the columns count only configured statuses.
    configureReportColumnsByGroup();

    $result = breakdownDashboard($this->category, RequestManagementReportRowMode::All);
    $chart = collect($result->categories[0]->charts)->firstWhere('id', "operator-{$this->category->id}-current_potentials");
    $points = collect($chart->points)->keyBy('label');

    expect($points['Anna']->breakdown)->toBe([['label' => 'Attesa DOC', 'value' => 1], ['label' => 'Validato', 'value' => 1]])
        ->and($points['Bruno']->breakdown)->toBe([['label' => 'Attesa DOC', 'value' => 1]]);
});

it('splits the other request columns too, never Aziende inserite', function (): void {
    $won = QuoteWorkflowStatus::factory()->for($this->workflow, 'workflow')->create(['group' => WorkflowStatusGroup::ClosedWon, 'name' => 'Associato SI']);
    breakdownQuote($this->category, $won)->forceFill(['created_at' => Carbon::parse('2026-09-10')])->save();
    breakdownQuote($this->category, $won)->forceFill(['created_at' => Carbon::parse('2026-09-11')])->save();

    // Spec 0391: the columns count only configured statuses.
    configureReportColumnsByGroup();

    $tiles = collect(breakdownDashboard($this->category, RequestManagementReportRowMode::TotalOnly)->summary)->keyBy('key');

    expect($tiles['associati']->breakdown)->toBe([['label' => 'Associato SI', 'value' => 2]])
        ->and($tiles['aziende_inserite']->breakdown)->toBe([]);
});
