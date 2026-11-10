<?php

declare(strict_types=1);

namespace App\Tables\Contracts;

use App\Models\Quote;
use App\Models\User;
use App\Tables\CategoryTabs\AttributeColumnCategories;
use App\Tables\CategoryTabs\AttributeGridTarget;
use App\Tables\CategoryTabs\Concerns\AppendsCategoryAttributeColumns;
use App\Tables\CategoryTabs\FilterScopedAttributeColumns;
use App\Tables\CustomFields\DelegatesUnaugmentedTableMethods;
use App\Tables\RequestManagement\AttributeGridColumns;
use App\Tables\TableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Decorator that adds the `attr.<code>` columns of the Quote context to the
 * `contracts` grid (spec 0374), for the category the advanced filters select
 * (`FilterScopedAttributeColumns`), READ-ONLY: a Contratto has no flexible
 * fields of its own, so the values are its source offer's
 * (`quote.attribute_values`, already eager-loaded by
 * `ContractsTableDefinition::baseQuery()`) and are edited from the offer.
 * Filter, sort and distinct values reach the offer's JSON without touching the
 * base query: a `whereIn` on a quotes sub-query, a join on a dedicated alias
 * added only when sorting, and the offers of the visible contracts. No row
 * logic: the filters already narrow the rows. OUTERMOST decorator of the domain.
 */
class ContractAttributeColumnsTableDefinition implements FilterScopedAttributeColumns, TableDefinition
{
    use AppendsCategoryAttributeColumns, DelegatesUnaugmentedTableMethods;

    /** Alias of the quotes join added when sorting by an `attr.*` column. */
    private const string SORT_QUOTE_ALIAS = 'contract_quote_attrs';

    private readonly AttributeGridColumns $attributeColumns;

    /** Same attribute set, bound to the sort alias' JSON column. */
    private readonly AttributeGridColumns $sortColumns;

    public function __construct(
        private readonly TableDefinition $inner,
        AttributeGridColumns $attributeColumns,
        private readonly AttributeColumnCategories $columnCategories,
    ) {
        $this->attributeColumns = $attributeColumns->forTarget(AttributeGridTarget::quote());
        $this->sortColumns = $attributeColumns->forTarget(AttributeGridTarget::quote(self::SORT_QUOTE_ALIAS.'.attribute_values'));
    }

    /**
     * @return Builder<Model>
     */
    public function baseQuery(): Builder
    {
        return $this->inner->baseQuery();
    }

    protected function attributeValuesEditable(User $actor): bool
    {
        return false;
    }

    /**
     * Declared non-editable so a PATCH on an `attr.*` cell is the generic
     * "Column not editable" 422 and never reaches a write.
     *
     * @param  Collection<int, array<string, mixed>>  $attributes
     * @return array<int, array<string, mixed>>
     */
    protected function rawAttributeColumns(Collection $attributes): array
    {
        return array_map(
            static fn (array $column): array => [...$column, 'editable' => false],
            $this->attributeColumns->rawColumns($attributes),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function storedAttributeValues(Model $row): ?array
    {
        return $row->quote?->attribute_values;
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $attributeRow
     * @param  array<string, mixed>  $filter
     */
    protected function applyAttributeFilter(Builder $query, array $attributeRow, array $filter): void
    {
        $quotes = Quote::query();
        $this->attributeColumns->applyFilter($quotes, $attributeRow, $filter);

        $query->whereIn('contracts.quote_id', $quotes->select('quotes.id'));
    }

    /**
     * One row per contract is kept: the join is on the offer's primary key.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $attributeRow
     */
    protected function applyAttributeSort(Builder $query, array $attributeRow, string $direction): void
    {
        if ($query->getQuery()->columns === null) {
            $query->select('contracts.*');
        }

        $alreadyJoined = collect($query->getQuery()->joins ?? [])
            ->contains(fn ($join): bool => str_contains((string) $join->table, self::SORT_QUOTE_ALIAS));

        if (! $alreadyJoined) {
            $query->leftJoin('quotes as '.self::SORT_QUOTE_ALIAS, self::SORT_QUOTE_ALIAS.'.id', '=', 'contracts.quote_id');
        }

        $this->sortColumns->applySort($query, $attributeRow, $direction);
    }

    /**
     * Read from the offers of the contracts $query can see, never from every
     * offer.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $attributeRow
     * @return array<int, string>
     */
    protected function attributeDistinctValues(Builder $query, array $attributeRow, ?string $search, int $limit): array
    {
        $visibleQuoteIds = (clone $query)->reorder()->select('contracts.quote_id');

        return $this->attributeColumns->distinctValues(
            Quote::query()->whereIn('quotes.id', $visibleQuoteIds),
            $attributeRow,
            $search,
            $limit,
        );
    }
}
