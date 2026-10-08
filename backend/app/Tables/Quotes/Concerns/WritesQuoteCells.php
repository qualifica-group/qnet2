<?php

declare(strict_types=1);

namespace App\Tables\Quotes\Concerns;

use App\Models\Quote;
use App\Models\User;
use App\Tables\Shared\QuoteWorkflowStatusOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Inline cell-editing of the quotes grid (spec 0206), split out of
 * QuotesTableDefinition for the file-size budget (engineering.md §6), same
 * convention as Gestione Richieste's WritesInlineEditableCells.
 *
 * The using class must expose `private readonly QuoteCellWriter $cellWriter`
 * and `private readonly QuoteWorkflowResolver $workflowResolver`.
 */
trait WritesQuoteCells
{
    /** The status column's displayed id (what `optionsFor()` is asked about). */
    private const string WORKFLOW_STATUS_COLUMN = 'quote_workflow_status';

    /**
     * Every editable column writes through QuoteCellWriter (the form's own
     * UpdateQuoteRequest + QuoteService). `$actor` and the status `note` are
     * not part of this contract method (spec 0053): they are read from the
     * ambient request, as Gestione Richieste does — TableCellUpdateService
     * has already refused a `note` on any column but the `notable` status.
     */
    public function updateCell(Model $row, string $columnId, mixed $value): Model
    {
        /** @var Quote $row */
        /** @var User $actor */
        $actor = Auth::user();

        return $this->cellWriter->write($row, $columnId, $value, $actor, request()->input('note'));
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    protected function optionsFor(string $columnId, User $actor): ?array
    {
        return $columnId === self::WORKFLOW_STATUS_COLUMN ? QuoteWorkflowStatusOptions::catalog() : null;
    }

    /**
     * The status ids the select editor offers for THIS offer: the set of the
     * workflow QuoteWorkflowResolver resolves for it (memoized per request,
     * so a page costs one query per distinct workflow, never one per row).
     *
     * @return array<int, int>
     */
    private function allowedWorkflowStatusIds(Quote $quote): array
    {
        return $this->workflowResolver
            ->statusesFor($this->workflowResolver->resolve($quote))
            ->pluck('id')
            ->all();
    }
}
