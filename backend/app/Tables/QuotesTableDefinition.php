<?php

namespace App\Tables;

use App\Models\Quote;
use App\Models\QuoteStatus;
use App\Models\User;
use App\Services\QuoteService;
use App\Tables\Quotes\QuoteAdvancedFilterCatalog;
use App\Tables\Quotes\QuoteColumnCatalog;
use App\Tables\Quotes\QuoteRelationColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `quotes` domain (spec 0065, MT-05).
 *
 * `code`/`title`/`created_at` and the 3 persisted header aggregates
 * (`revenue_net`/`cost_net`/`margin_net`, D-9) are real DB columns handled
 * entirely by the generic engine. `opportunity`/`quote_status`/`commercial`/
 * `reporter`/`supervisor` are relation-derived columns delegated to
 * QuoteRelationColumns (file-size split, engineering.md §6): own-FK simple
 * relations, mirroring OpportunitiesTableDefinition.
 */
class QuotesTableDefinition extends AbstractTableDefinition
{
    public function __construct(
        private readonly QuoteRelationColumns $relationColumns,
        private readonly QuoteService $service,
    ) {}

    public function domain(): string
    {
        return 'quotes';
    }

    /**
     * @return class-string<Quote>
     */
    public function modelClass(): string
    {
        return Quote::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives QuotePolicy::viewAny from
    // modelClass() (quotes.viewAny).

    /**
     * @return Builder<Quote>
     */
    public function baseQuery(): Builder
    {
        // Eager-load every relation mapRow touches to avoid N+1 across the page.
        return Quote::query()->with(['opportunity', 'quoteStatus', 'commercial', 'reporter', 'supervisor']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return QuoteColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return QuoteColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return QuoteColumnCatalog::actions();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        return QuoteAdvancedFilterCatalog::advancedFilters();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'created_at', 'direction' => 'desc'],
        ];
    }

    /**
     * @return array{limit: int}
     */
    public function defaultPagination(): array
    {
        return ['limit' => 25];
    }

    /**
     * Map a Quote to the row payload. `actions` is attached by the generic
     * TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var Quote $row */
        return [
            'id' => $row->id,
            'code' => $row->code,
            'title' => $row->title,
            'opportunity' => $this->summarize($row->opportunity),
            'quote_status' => $this->summarizeQuoteStatus($row->quoteStatus),
            'commercial' => $this->summarize($row->commercial),
            'reporter' => $this->summarize($row->reporter),
            'supervisor' => $this->summarize($row->supervisor),
            'revenue_net' => $row->revenue_net,
            'cost_net' => $row->cost_net,
            'margin_net' => $row->margin_net,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function summarize(?Model $related): ?array
    {
        return $related === null ? null : ['id' => $related->id, 'name' => $related->name];
    }

    /**
     * The quote status projected WITH its `color` token, so the grid renders
     * the colored status badge; the generic summarize() would drop it.
     *
     * @return array{id: int, name: string, color: string|null}|null
     */
    private function summarizeQuoteStatus(?QuoteStatus $status): ?array
    {
        return $status === null ? null : ['id' => $status->id, 'name' => $status->name, 'color' => $status->color];
    }

    /**
     * Allowed action keys for a single row, via QuotePolicy.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if (Gate::forUser($actor)->allows('update', $row)) {
            $allowed[] = 'edit';
        }

        if (Gate::forUser($actor)->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        return $allowed;
    }

    /**
     * @param  Builder<Quote>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        return $this->relationColumns->applyFilter($query, $columnId, $filter);
    }

    /**
     * @param  Builder<Quote>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        return $this->relationColumns->applySort($query, $columnId, $direction);
    }

    /**
     * Excel-like distinct values (spec 0004/0005).
     *
     * @param  Builder<Quote>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return $this->relationColumns->distinctValues($columnId, $search, $query, $limit);
    }

    /**
     * Delegate to QuoteService::delete() so the generic bulk-delete endpoint
     * cascades the quote's lines (AC-026) exactly like the single DELETE
     * /quotes/{quote} endpoint.
     */
    public function deleteModel(Model $model): void
    {
        /** @var Quote $model */
        $this->service->delete($model);
    }
}
