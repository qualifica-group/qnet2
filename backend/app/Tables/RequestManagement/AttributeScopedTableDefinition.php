<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

use App\Authorization\AuthorizationRegistry;
use App\Models\Opportunity;
use App\Models\User;
use App\RequestManagement\ApplicableAttribute;
use App\RequestManagement\ApplicableAttributesResolver;
use App\Services\RequestManagement\RequestManagementService;
use App\Tables\CustomFields\DelegatesUnaugmentedTableMethods;
use App\Tables\RequestManagement\Concerns\WritesAttributeCells;
use App\Tables\TableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Decorator that appends `attr.<code>` columns to the `request-management`
 * domain for a selected product-category scope (spec 0064): the category
 * tabs' backend counterpart, mirroring the architecture of
 * `App\Tables\CustomFieldAwareTableDefinition` (spec 0021) — same
 * decorator-over-decorator composition (`TableRegistry::resolve()` wraps
 * THIS around the already `CustomFieldAwareTableDefinition`-wrapped inner
 * definition, so column order is native, then `custom.*`, then `attr.*`).
 *
 * Applies to `request-management` ONLY (`TableRegistry`) and stays a PURE
 * PASSTHROUGH whenever no scope has been set (D-3: the "Tutte" tab) — every
 * other domain, and every OTHER endpoint of this same domain that never
 * calls a scope setter (export, saved filter views), is byte-identical to
 * today.
 *
 * TWO independent scope concepts, set explicitly by the caller
 * (`TableController` for the request-scoped case, the Table FormRequests for
 * their own independently-resolved definition instance — see each file's
 * `definition()`):
 *  - `scopeToProductCategory(?id)` — ONE category (or none): drives the
 *    request-facing shape (`baseQuery`/`mapRow`/`resolveConfig`/derived
 *    filter-sort-distinct/`editableColumnIds`) AND, unless
 *    `scopeToAllProductCategories()` was also called, the SSRM allow-lists
 *    (`sortableColumnIds`/`filterableColumnIds`/`filterableColumnMap`).
 *  - `scopeToAllProductCategories()` — the UNION across every category
 *    (D-4): ONLY widens the SSRM allow-lists, used exclusively by the
 *    column-preferences/filter-state persistence endpoints, whose saved
 *    layout must never 422 depending on which tab was open when it was
 *    saved.
 *
 * `columns()`/`defaultColumnLayout()` are UNSCOPED by design (always the
 * union): they back `TableCellUpdateService`'s structural PATCH lookup and
 * the preferences default baseline, neither of which is a per-tab concept.
 */
class AttributeScopedTableDefinition implements TableDefinition
{
    use DelegatesUnaugmentedTableMethods, WritesAttributeCells {
        // Both traits declare editableColumnIds()/updateCell(): the passthrough
        // from DelegatesUnaugmentedTableMethods is the WRONG one here (spec
        // 0064 §M4 augments both for `attr.*` columns) — WritesAttributeCells
        // wins, and it delegates to $this->inner itself for every non-`attr.*`
        // column, so the passthrough behavior is preserved either way.
        WritesAttributeCells::editableColumnIds insteadof DelegatesUnaugmentedTableMethods;
        WritesAttributeCells::updateCell insteadof DelegatesUnaugmentedTableMethods;
    }

    private ?int $categoryScope = null;

    private bool $allowListUnion = false;

    /**
     * Set by `WritesAttributeCells::updateCell()` right after a successful
     * `attr.<code>` write, so the SAME definition instance's immediately
     * following `mapRow()` call (`TableCellUpdateService`'s Step 7 row
     * remap) exposes `attr.*` — the PATCH endpoint carries no category-scope
     * param of its own (unlike columns/rows), so the row's OWN applicable
     * attributes (`ApplicableAttributesResolver`, every category across its
     * OWN product lines) stand in for "which attr.* columns this response
     * shows", never the D-3 "Tutte" empty default.
     *
     * @var Collection<int, array<string, mixed>>|null
     */
    private ?Collection $rowAttributesOverride = null;

    public function __construct(
        private readonly TableDefinition $inner,
        private readonly AttributeScopeResolver $scopeResolver,
        private readonly AttributeColumnBuilder $columnBuilder,
        private readonly AuthorizationRegistry $authorizationRegistry,
        private readonly RequestManagementService $service,
        private readonly ApplicableAttributesResolver $attributesResolver,
        private readonly AttributeDateFilterApplier $dateFilterApplier,
    ) {}

    /**
     * Narrows every request-facing method to ONE product category's
     * effective attributes (null = D-3 "Tutte", zero `attr.*` columns).
     */
    public function scopeToProductCategory(?int $productCategoryId): void
    {
        $this->categoryScope = $productCategoryId;
    }

    /**
     * Widens the SSRM allow-lists (sort/filter column ids) to the UNION of
     * every category's attributes (D-4) — used by the preferences/filter
     * persistence endpoints only.
     */
    public function scopeToAllProductCategories(): void
    {
        $this->allowListUnion = true;
    }

    /**
     * @return Builder<Model>
     */
    public function baseQuery(): Builder
    {
        $query = $this->inner->baseQuery();

        if ($this->categoryScope === null) {
            return $query;
        }

        $categoryId = $this->categoryScope;

        // D-2: EXISTS on the opportunity's OWN product lines — a request
        // with lines on several categories is meant to appear in every
        // matching tab, never deduped here.
        $query->whereHas('productLines', static function (Builder $relatedQuery) use ($categoryId): void {
            $relatedQuery->where('product_category_id', $categoryId);
        });

        return $query;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        $attributes = $this->scopeResolver->union();

        if ($attributes->isEmpty()) {
            return $this->inner->columns();
        }

        return [
            ...$this->inner->columns(),
            ...$attributes->map(fn (array $row): array => $this->columnBuilder->raw($row))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        $mapped = $this->inner->mapRow($actor, $row);
        $attributes = $this->rowAttributesOverride ?? $this->categoryAttributes();

        if ($attributes->isEmpty()) {
            return $mapped;
        }

        /** @var Opportunity $row */
        $values = $row->attribute_values ?? [];

        foreach ($attributes as $attributeRow) {
            $mapped[$this->columnBuilder->id($attributeRow)] = $values[$attributeRow['code']] ?? null;
        }

        return $mapped;
    }

    public function sortableColumnIds(): array
    {
        return [...$this->inner->sortableColumnIds(), ...$this->allowListedColumnIds()];
    }

    public function filterableColumnIds(): array
    {
        return array_keys($this->filterableColumnMap());
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveConfig(User $actor): array
    {
        $config = $this->inner->resolveConfig($actor);
        $attributes = $this->categoryAttributes();

        if ($attributes->isEmpty()) {
            return $config;
        }

        $order = count($config['columns']);
        $editable = $this->attributeValuesEditable($actor);
        $resolved = [];

        foreach ($attributes as $attributeRow) {
            $order++;
            $resolved[] = $this->columnBuilder->resolved($attributeRow, $order, $editable);
        }

        $config['columns'] = [...$config['columns'], ...$resolved];

        return $config;
    }

    /**
     * @return array<string, array{visible: bool, width: int|null, order: int}>
     */
    public function defaultColumnLayout(): array
    {
        $layout = $this->inner->defaultColumnLayout();
        $attributes = $this->scopeResolver->union();

        if ($attributes->isEmpty()) {
            return $layout;
        }

        $order = count($layout);

        foreach ($attributes as $attributeRow) {
            $order++;
            $layout[$this->columnBuilder->id($attributeRow)] = ['visible' => false, 'width' => null, 'order' => $order];
        }

        return $layout;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function filterableColumnMap(): array
    {
        $map = $this->inner->filterableColumnMap();

        foreach ($this->allowListedAttributes() as $attributeRow) {
            $map[$this->columnBuilder->id($attributeRow)] = $this->columnBuilder->raw($attributeRow);
        }

        return $map;
    }

    /**
     * `date`/`datetime` attributes (contract `filterType: "date"`, so the
     * frontend mounts `agDateColumnFilter`) go through
     * `AttributeDateFilterApplier` instead of the handler's own
     * `applyFilter()`: the handler's (`AppliesTextFilter`, via
     * `HandlesScalarStringField`) expects a TEXT payload
     * (`filter`/`type: contains|equals`), correct for spec 0021's custom
     * fields but NOT the `{dateFrom, dateTo}` shape a date-range picker
     * sends — `DateFieldType`/`DateTimeFieldType` stay untouched (see that
     * class' docblock).
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        $attributeRow = $this->attributeRowFor($columnId, $this->categoryAttributes());

        if ($attributeRow === null) {
            return $this->inner->applyDerivedFilter($query, $columnId, $columnConfig, $filter);
        }

        if ($this->isDateAttribute($attributeRow)) {
            $this->dateFilterApplier->apply($query, $this->jsonPathFor($attributeRow), $attributeRow['type'] === 'datetime', $filter);

            return true;
        }

        $this->columnBuilder->handlerFor($attributeRow)->applyFilter($query, self::valuesColumn(), $attributeRow['code'], $filter);

        return true;
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        $attributeRow = $this->attributeRowFor($columnId, $this->categoryAttributes());

        if ($attributeRow === null) {
            return $this->inner->applyDerivedSort($query, $columnId, $direction);
        }

        $this->columnBuilder->handlerFor($attributeRow)->applySort($query, self::valuesColumn(), $attributeRow['code'], $direction);

        return true;
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        $attributeRow = $this->attributeRowFor($columnId, $this->categoryAttributes());

        if ($attributeRow === null) {
            return $this->inner->distinctValues($actor, $columnId, $columnConfig, $search, $query, $limit);
        }

        $values = $this->columnBuilder->handlerFor($attributeRow)->distinctValues($query, self::valuesColumn(), $attributeRow['code']);

        if ($search !== null && $search !== '') {
            $needle = mb_strtolower($search);
            $values = array_values(array_filter(
                $values,
                static fn (mixed $value): bool => str_contains(mb_strtolower((string) $value), $needle),
            ));
        }

        return array_slice($values, 0, $limit);
    }

    public function searchableColumnIds(): array
    {
        return $this->inner->searchableColumnIds();
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function applyDerivedSearch(Builder $query, string $columnId, string $pattern): bool
    {
        return $this->inner->applyDerivedSearch($query, $columnId, $pattern);
    }

    /**
     * The bound base JSON column every attribute value is read/written
     * through (spec 0064 §M1): `opportunities.attribute_values`, a REAL
     * column on the row's own table — never a raw/joined identifier.
     */
    private static function valuesColumn(): string
    {
        return 'opportunities.attribute_values';
    }

    /**
     * The bound JSON-path expression for one attribute row's stored value —
     * built directly (not via a `FieldTypeHandler`) for
     * `AttributeDateFilterApplier`, which operates on the raw column
     * expression rather than a handler-resolved one. `$attributeRow['code']`
     * is always the allow-listed definition key resolved server-side
     * (`categoryAttributes()`), never raw request input.
     *
     * @param  array<string, mixed>  $attributeRow
     */
    private function jsonPathFor(array $attributeRow): string
    {
        return self::valuesColumn().'->'.$attributeRow['code'];
    }

    /**
     * @param  array<string, mixed>  $attributeRow
     */
    private function isDateAttribute(array $attributeRow): bool
    {
        return in_array($attributeRow['type'], ['date', 'datetime'], true);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function categoryAttributes(): Collection
    {
        return $this->scopeResolver->forCategory($this->categoryScope);
    }

    /**
     * $opportunity's OWN applicable attributes (every category across its
     * product lines, deduped by code — `ApplicableAttributesResolver`),
     * reshaped to the same plain-array shape `AttributeColumnBuilder`
     * expects (`ApplicableAttribute::toArray()` carries the same
     * code/name/type/config/relation_target/options/sort_order fields
     * `CategoryHierarchy::effectiveAttributes()` rows do). Used ONLY by
     * `WritesAttributeCells::updateCell()` (see `$rowAttributesOverride`'s
     * docblock).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function applicableAttributes(Opportunity $opportunity): Collection
    {
        return $this->attributesResolver->resolve($opportunity)
            ->map(static fn (ApplicableAttribute $attribute): array => $attribute->toArray());
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function allowListedAttributes(): Collection
    {
        return $this->allowListUnion ? $this->scopeResolver->union() : $this->categoryAttributes();
    }

    /**
     * @return array<int, string>
     */
    private function allowListedColumnIds(): array
    {
        return $this->allowListedAttributes()->map(fn (array $row): string => $this->columnBuilder->id($row))->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $attributes
     * @return array<string, mixed>|null
     */
    private function attributeRowFor(string $columnId, Collection $attributes): ?array
    {
        $code = $this->columnBuilder->codeFor($columnId);

        if ($code === null) {
            return null;
        }

        return $attributes->firstWhere('code', $code);
    }
}
