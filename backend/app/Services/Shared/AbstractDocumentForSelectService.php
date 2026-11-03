<?php

declare(strict_types=1);

namespace App\Services\Shared;

use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Search, pagination and `ids[]` hydration shared by the document for-select
 * endpoints (ADR 0011: invoices, purchase invoices/orders/requests, contracts).
 * A concrete service supplies only what differs: the scoped and projected base
 * query, the searchable columns and the order.
 *
 * The base query IS the visibility boundary: hydrated ids go through it too, so
 * `ids[]` can never reveal a row the index would not show.
 */
abstract class AbstractDocumentForSelectService
{
    /**
     * The module index's own restriction (scope, if any), the minimal column
     * projection and the eager loads the resource reads.
     *
     * @return Builder<Model>
     */
    abstract protected function baseQuery(): Builder;

    /**
     * @param  Builder<Model>  $query
     */
    abstract protected function applySearch(Builder $query, string $term): void;

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    abstract protected function applyOrder(Builder $query): Builder;

    /** Qualified primary key of the base table. */
    abstract protected function keyColumn(): string;

    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = $this->baseQuery();

        if ($query->hasSearch()) {
            $term = (string) $query->search;
            $base->where(fn (Builder $scoped) => $this->applySearch($scoped, $term));
        }

        $window = $query->page($base, fn (Builder $ordered) => $this->applyOrder($ordered));

        /** @var Collection<int, Model> $page */
        $page = $window->items;

        return $window->withItems($this->appendHydratedIds($page, $query));
    }

    /**
     * Edit-mode hydration: the ids the client already holds, deduplicated,
     * bypassing the search filter and never inflating `total`.
     *
     * @param  Collection<int, Model>  $page
     * @return Collection<int, Model>
     */
    private function appendHydratedIds(Collection $page, ForSelectQuery $query): Collection
    {
        if (! $query->hasIds()) {
            return $page;
        }

        $missingIds = array_values(array_diff($query->ids, $page->pluck('id')->all()));

        if ($missingIds === []) {
            return $page;
        }

        return $page->concat($this->applyOrder($this->baseQuery()->whereIn($this->keyColumn(), $missingIds))->get());
    }

    /**
     * "{number}/{year}" documents: a plain term matches the number, "12/2026"
     * matches both parts.
     *
     * @param  Builder<Model>  $query
     */
    protected function searchNumberYear(Builder $query, string $term, string $table): void
    {
        if (preg_match('#^(\d+)\s*/\s*(\d{1,4})$#', $term, $parts) === 1) {
            $query->where(fn (Builder $pair) => $pair->where("{$table}.number", (int) $parts[1])->where("{$table}.year", (int) $parts[2]));

            return;
        }

        $query->where("{$table}.number", 'like', '%'.$term.'%');
    }
}
