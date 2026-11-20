<?php

declare(strict_types=1);

use App\Enums\ReportStatusColumn;
use App\Enums\WorkflowStatusGroup;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowStatus;
use App\Models\ReportColumnStatus;
use App\Models\User;
use App\Services\RequestManagement\Report\ReportDateRange;
use App\Services\RequestManagement\Report\ReportIndicatorRegistry;
use App\Services\RequestManagement\Report\ReportOperatorFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0391 AC-001..AC-004, AC-009: a status-based column counts the requests
 * whose CURRENT status is configured for it; the "nel periodo" columns add the
 * request creation date. Range 2026-09-01 .. 2026-09-30, today 2026-09-18.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-09-18 10:00:00');
    $this->category = ProductCategory::factory()->reportable()->create(['name' => 'GOL']);
    $this->workflow = QuoteWorkflow::factory()->create(['name' => 'Flusso test']);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function columnCountStatus(QuoteWorkflow $workflow, WorkflowStatusGroup $group, string $name): QuoteWorkflowStatus
{
    return QuoteWorkflowStatus::factory()->for($workflow, 'workflow')->create(['group' => $group, 'name' => $name]);
}

function columnCountQuote(ProductCategory $category, QuoteWorkflowStatus $status, string $createdAt = '2026-09-10'): Quote
{
    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => BusinessFunction::factory()->create()->id,
        'product_category_id' => $category->id,
    ]);

    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id, 'quote_workflow_status_id' => $status->id]);
    $quote->forceFill(['created_at' => Carbon::parse($createdAt)])->save();

    return $quote;
}

function columnCountTotal(string $column, ProductCategory $category, string $from = '2026-09-01', string $to = '2026-09-30'): int
{
    Permission::findOrCreate('request-management.viewAll');
    $actor = User::factory()->create();
    $actor->givePermissionTo('request-management.viewAll');

    return app(ReportIndicatorRegistry::class)->resolve($column)
        ->compute([$category->id], $actor, ReportDateRange::fromRequest($from, $to), ReportOperatorFilter::all())
        ->total;
}

it('AC-001: new contacts count only requests currently in a configured status, with and without the period', function (): void {
    $chosen = columnCountStatus($this->workflow, WorkflowStatusGroup::Open, 'Nuovo Contatto');
    $notChosen = columnCountStatus($this->workflow, WorkflowStatusGroup::Open, 'Aperta non scelta');
    configureReportColumn(ReportStatusColumn::NewContacts, [$chosen]);

    columnCountQuote($this->category, $chosen, '2026-01-15'); // outside the range
    columnCountQuote($this->category, $chosen, '2026-09-10');
    columnCountQuote($this->category, $notChosen, '2026-09-10'); // an initial status that is not chosen

    expect(columnCountTotal('unhandled_new_contacts', $this->category))->toBe(2)
        ->and(columnCountTotal('nuovi_contatti', $this->category))->toBe(1);
});

it('AC-002: potentials and their twin match when the range covers the creation; a moved-on request counts in neither', function (): void {
    $pending = columnCountStatus($this->workflow, WorkflowStatusGroup::Pending, 'In trattativa');
    $validated = columnCountStatus($this->workflow, WorkflowStatusGroup::Validated, 'Validato');
    $postponed = columnCountStatus($this->workflow, WorkflowStatusGroup::Pending, 'Rimandata');
    configureReportColumn(ReportStatusColumn::Potentials, [$pending, $validated]);

    columnCountQuote($this->category, $pending, '2026-09-10');
    columnCountQuote($this->category, $validated, '2026-01-10'); // created outside the range
    $moved = columnCountQuote($this->category, $pending, '2026-09-11');
    $moved->forceFill(['quote_workflow_status_id' => $postponed->id])->save();

    // Range not covering the second request: only the range-free column sees it.
    expect(columnCountTotal('current_potentials', $this->category))->toBe(2)
        ->and(columnCountTotal('potenziali', $this->category))->toBe(1)
        // Range covering both creations: the two columns agree.
        ->and(columnCountTotal('current_potentials', $this->category, '2026-01-01', '2026-12-31'))->toBe(2)
        ->and(columnCountTotal('potenziali', $this->category, '2026-01-01', '2026-12-31'))->toBe(2);
});

it('AC-003: associati, trattative concluse and invio presa in carico count their own statuses, created in the period', function (): void {
    $guidance = QuoteWorkflow::factory()->create(['name' => 'Orientamento specialistico']);
    $toSummon = columnCountStatus($guidance, WorkflowStatusGroup::ClosedWon, 'Da convocare');
    $fileClosed = columnCountStatus($guidance, WorkflowStatusGroup::ClosedWon, 'Fine pratica');
    $associated = columnCountStatus($this->workflow, WorkflowStatusGroup::ClosedWon, 'Associato');

    configureReportColumn(ReportStatusColumn::IntakeSent, [$toSummon]);
    configureReportColumn(ReportStatusColumn::ConcludedDeals, [$fileClosed]);
    configureReportColumn(ReportStatusColumn::Associated, [$associated]);

    columnCountQuote($this->category, $toSummon);
    columnCountQuote($this->category, $toSummon);
    columnCountQuote($this->category, $toSummon, '2026-08-01'); // outside the range
    columnCountQuote($this->category, $fileClosed);
    columnCountQuote($this->category, $associated);
    columnCountQuote($this->category, $associated);
    columnCountQuote($this->category, $associated);

    expect(columnCountTotal('invio_presa_in_carico', $this->category))->toBe(2)
        ->and(columnCountTotal('trattative_concluse', $this->category))->toBe(1)
        ->and(columnCountTotal('associati', $this->category))->toBe(3);
});

it('AC-004: a column with no chosen status is 0 whatever the requests are', function (): void {
    foreach ([WorkflowStatusGroup::Open, WorkflowStatusGroup::Pending, WorkflowStatusGroup::Validated, WorkflowStatusGroup::ClosedWon] as $group) {
        columnCountQuote($this->category, columnCountStatus($this->workflow, $group, "Stato {$group->value}"));
    }

    foreach (['unhandled_new_contacts', 'nuovi_contatti', 'current_potentials', 'potenziali', 'associati', 'trattative_concluse', 'invio_presa_in_carico'] as $column) {
        expect(columnCountTotal($column, $this->category))->toBe(0);
    }
});

it('AC-009: deleting a status removes its configuration rows and leaves the others', function (): void {
    $doomed = columnCountStatus($this->workflow, WorkflowStatusGroup::ClosedWon, 'Da cancellare');
    $kept = columnCountStatus($this->workflow, WorkflowStatusGroup::ClosedWon, 'Da tenere');
    configureReportColumn(ReportStatusColumn::Associated, [$doomed, $kept]);
    configureReportColumn(ReportStatusColumn::ConcludedDeals, [$doomed]);

    $doomed->delete();

    expect(ReportColumnStatus::query()->where('quote_workflow_status_id', $doomed->id)->count())->toBe(0)
        ->and(ReportColumnStatus::query()->pluck('quote_workflow_status_id')->all())->toBe([$kept->id]);
});
