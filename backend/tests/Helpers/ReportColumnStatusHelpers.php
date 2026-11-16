<?php

use App\Enums\ReportStatusColumn;
use App\Enums\WorkflowStatusGroup;
use App\Enums\WorkflowStatusSystemKey;
use App\Models\QuoteWorkflowStatus;
use App\Services\RequestManagement\Report\ReportColumnStatusMap;

/**
 * Spec 0391: the statistics columns count only the statuses configured for
 * them. Configures every status existing NOW the way the columns counted
 * before the configurator (status group / first status), so a test written
 * for those rules keeps its fixture: call it after the statuses are created.
 */
function configureReportColumnsByGroup(): void
{
    $statuses = QuoteWorkflowStatus::query()->get(['id', 'group', 'system_key']);
    $ids = static fn (callable $matches): array => $statuses->filter($matches)->pluck('id')->all();
    $closedWon = $ids(static fn (QuoteWorkflowStatus $status): bool => $status->group === WorkflowStatusGroup::ClosedWon);

    app(ReportColumnStatusMap::class)->replace([
        ReportStatusColumn::NewContacts->value => $ids(static fn (QuoteWorkflowStatus $status): bool => $status->system_key === WorkflowStatusSystemKey::Open->value),
        ReportStatusColumn::Potentials->value => $ids(static fn (QuoteWorkflowStatus $status): bool => in_array($status->group, [WorkflowStatusGroup::Pending, WorkflowStatusGroup::Validated], true)),
        ReportStatusColumn::Associated->value => $closedWon,
        ReportStatusColumn::ConcludedDeals->value => $closedWon,
        ReportStatusColumn::IntakeSent->value => $closedWon,
    ]);
}

/**
 * Configures one column with exactly $statuses (the others keep theirs).
 *
 * @param  array<int, QuoteWorkflowStatus>  $statuses
 */
function configureReportColumn(ReportStatusColumn $column, array $statuses): void
{
    $map = app(ReportColumnStatusMap::class);
    $all = $map->all();
    $all[$column->value] = array_map(static fn (QuoteWorkflowStatus $status): int => $status->id, $statuses);
    $map->replace($all);
}
