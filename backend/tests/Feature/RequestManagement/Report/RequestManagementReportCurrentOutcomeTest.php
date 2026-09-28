<?php

declare(strict_types=1);

use App\Enums\WorkflowStatusGroup;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\Services\RequestManagement\Report\ReportDateRange;
use App\Services\RequestManagement\Report\ReportIndicatorRegistry;
use App\Services\RequestManagement\Report\ReportOperatorFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0170: the closed_won columns read the CURRENT status (period on the
 * request creation date), "Presa Appuntamenti" the direct logged move
 * "OK App. Fissato..." -> "Assegnato". Range 2026-09-01 .. 2026-09-30,
 * today 2026-09-18.
 */
uses(RefreshDatabase::class);

const CURRENT_OUTCOME_CLOSED_WON_COLUMNS = ['associati', 'trattative_concluse', 'invio_presa_in_carico'];

beforeEach(function (): void {
    Carbon::setTestNow('2026-09-18 10:00:00');
    $this->category = ProductCategory::factory()->reportable()->create(['name' => 'APL']);
    $this->workflow = QuoteWorkflow::factory()->create();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

if (! function_exists('currentOutcomeQuote')) {
    function currentOutcomeQuote(ProductCategory $category, int $statusId, string $createdAt = '2026-09-10'): Quote
    {
        $opportunity = Opportunity::factory()->create();
        OpportunityProductLine::factory()->create([
            'opportunity_id' => $opportunity->id,
            'business_function_id' => BusinessFunction::factory()->create()->id,
            'product_category_id' => $category->id,
        ]);

        $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id, 'quote_workflow_status_id' => $statusId]);
        $quote->forceFill(['created_at' => Carbon::parse($createdAt)])->save();

        return $quote;
    }
}

if (! function_exists('currentOutcomeStatus')) {
    function currentOutcomeStatus(QuoteWorkflow $workflow, WorkflowStatusGroup $group, ?string $name = null): int
    {
        $attributes = ['group' => $group, 'system_key' => null, ...($name !== null ? ['name' => $name] : [])];

        return QuoteWorkflowStatus::factory()->for($workflow, 'workflow')->create($attributes)->id;
    }
}

if (! function_exists('currentOutcomeLog')) {
    /** Same shape RequestManagementService / QuoteStatusChangeLogger write. */
    function currentOutcomeLog(Quote $quote, ?int $previousStatusId, int $statusId, string $at): void
    {
        activity('opportunities')
            ->performedOn($quote->opportunity)
            ->event('updated')
            ->withProperties([
                'attributes' => ['quote_workflow_status_id' => $statusId],
                'old' => ['quote_workflow_status_id' => $previousStatusId],
            ])
            ->createdAt(Carbon::parse($at))
            ->log('Request management work update');
    }
}

if (! function_exists('currentOutcomeTotal')) {
    function currentOutcomeTotal(string $column, ProductCategory $category): int
    {
        Permission::findOrCreate('request-management.viewAll');
        $actor = User::factory()->create();
        $actor->givePermissionTo('request-management.viewAll');

        return app(ReportIndicatorRegistry::class)->resolve($column)
            ->compute([$category->id], $actor, ReportDateRange::fromRequest('2026-09-01', '2026-09-30'), ReportOperatorFilter::all())
            ->total;
    }
}

it('AC-001: a request moved to closed_won and then elsewhere is not counted by the closed_won columns', function (): void {
    $won = currentOutcomeStatus($this->workflow, WorkflowStatusGroup::ClosedWon);
    $lost = currentOutcomeStatus($this->workflow, WorkflowStatusGroup::ClosedLost);

    $reverted = currentOutcomeQuote($this->category, $lost);
    currentOutcomeLog($reverted, null, $won, '2026-09-10 09:00:00');
    currentOutcomeLog($reverted, $won, $lost, '2026-09-10 09:01:00');

    foreach (CURRENT_OUTCOME_CLOSED_WON_COLUMNS as $column) {
        expect(currentOutcomeTotal($column, $this->category))->toBe(0);
    }
});

it('AC-002/AC-003: counts requests closed_won today and created in the range, with no logged transition', function (): void {
    $won = currentOutcomeStatus($this->workflow, WorkflowStatusGroup::ClosedWon);

    currentOutcomeQuote($this->category, $won, '2026-09-10');
    currentOutcomeQuote($this->category, $won, '2026-09-30 23:30:00');
    currentOutcomeQuote($this->category, $won, '2026-08-31');
    currentOutcomeQuote($this->category, currentOutcomeStatus($this->workflow, WorkflowStatusGroup::Pending));

    foreach (CURRENT_OUTCOME_CLOSED_WON_COLUMNS as $column) {
        expect(currentOutcomeTotal($column, $this->category))->toBe(2);
    }
});

it('AC-004: counts a direct move from "OK App. Fissato" to "Assegnato" in the range, whatever the status today', function (): void {
    $booked = currentOutcomeStatus($this->workflow, WorkflowStatusGroup::Open, 'ok app. fissato APL');
    $assigned = currentOutcomeStatus($this->workflow, WorkflowStatusGroup::ClosedWon, 'ASSEGNATO');
    $lost = currentOutcomeStatus($this->workflow, WorkflowStatusGroup::ClosedLost);

    $stillAssigned = currentOutcomeQuote($this->category, $assigned);
    currentOutcomeLog($stillAssigned, $booked, $assigned, '2026-09-10');

    $movedOn = currentOutcomeQuote($this->category, $lost);
    currentOutcomeLog($movedOn, $booked, $assigned, '2026-09-11');
    currentOutcomeLog($movedOn, $assigned, $lost, '2026-09-12');

    expect(currentOutcomeTotal('presa_appuntamenti', $this->category))->toBe(2);
});

it('AC-005: ignores a move to "Assegnato" from another status, outside the range, or across workflows', function (): void {
    $booked = currentOutcomeStatus($this->workflow, WorkflowStatusGroup::Open, 'OK App. Fissato CPI');
    $assigned = currentOutcomeStatus($this->workflow, WorkflowStatusGroup::ClosedWon, 'Assegnato');
    $other = currentOutcomeStatus($this->workflow, WorkflowStatusGroup::Open, 'Da Richiamare');

    $fromOther = currentOutcomeQuote($this->category, $assigned);
    currentOutcomeLog($fromOther, $other, $assigned, '2026-09-10');

    $beforeRange = currentOutcomeQuote($this->category, $assigned);
    currentOutcomeLog($beforeRange, $booked, $assigned, '2026-08-31');

    $otherWorkflow = QuoteWorkflow::factory()->create();
    $assignedElsewhere = currentOutcomeStatus($otherWorkflow, WorkflowStatusGroup::ClosedWon, 'Assegnato');
    $crossWorkflow = currentOutcomeQuote($this->category, $assignedElsewhere);
    currentOutcomeLog($crossWorkflow, $booked, $assignedElsewhere, '2026-09-10');

    expect(currentOutcomeTotal('presa_appuntamenti', $this->category))->toBe(0);
});
