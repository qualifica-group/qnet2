<?php

declare(strict_types=1);

namespace App\Tables\Quotes\Concerns;

use App\Models\Quote;
use App\Models\User;
use App\Tables\Quotes\QuoteSystemStatusColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Inline cell-editing of the quotes grid (spec 0206), split out of
 * QuotesTableDefinition for the file-size budget (engineering.md §6), same
 * convention as Gestione Richieste's WritesInlineEditableCells.
 *
 * The using class must expose `private readonly QuoteCellWriter $cellWriter`
 * and `private readonly QuoteSystemStatusMap $systemStatusMap`.
 */
trait WritesQuoteCells
{
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
        return $columnId === QuoteSystemStatusColumn::COLUMN_ID ? QuoteSystemStatusColumn::catalog() : null;
    }

    /**
     * Spec 0404 A-D7: the row's system status, the ones its select editor
     * offers (linked in the offer's resolved set, memoized per workflow) and
     * those whose destination demands a note.
     *
     * @return array<string, mixed>
     */
    private function systemStatusCell(Quote $quote): array
    {
        $rowOptions = $this->systemStatusMap->rowOptions($quote);

        return [
            QuoteSystemStatusColumn::COLUMN_ID => $this->systemStatusColumn->summarize($quote),
            QuoteSystemStatusColumn::COLUMN_ID.'_options' => $rowOptions['options'],
            QuoteSystemStatusColumn::COLUMN_ID.'_notes' => $rowOptions['notes'],
        ];
    }
}
