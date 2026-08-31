<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

use App\Authorization\AuthorizationRegistry;
use App\Models\Quote;
use App\Models\User;
use App\RequestManagement\ApplicableAttribute;
use App\RequestManagement\RequestAttributeResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

/**
 * The READ side of the `attr.<code>` flexible columns for the
 * `request-management` grid (spec 0064, restored by the user directive
 * 2026-08-31): column shapes, row values, and the filter/sort/distinct hooks
 * over the JSON column the values live in.
 *
 * Split out of `RequestManagementScopedTableDefinition` (file-size budget,
 * engineering.md §6), which keeps the decorator concerns — WHICH rows a tab
 * shows, WHICH scope is set, and the composition itself — while every
 * "what does an attribute column look like / read / filter like" question
 * lands here. Its write twin is the
 * `App\Tables\RequestManagement\Concerns\WritesAttributeCells` trait.
 *
 * Storage (spec 0084, D-1): `quotes.attribute_values`, a REAL JSON column on
 * the row's OWN table — never a joined identifier, and one hop shorter than
 * spec 0064's original `opportunities.attribute_values`.
 *
 * Every `code` reaching a query here comes from the server-resolved
 * definition set (AttributeScopeResolver), never from request input, so the
 * JSON paths below are allow-list values (backend.md §8).
 */
final class AttributeGridColumns
{
    /**
     * The bound base JSON column every attribute value is read/written
     * through: a real column of the row's own table.
     */
    private const string VALUES_COLUMN = 'quotes.attribute_values';

    /** The two attribute types whose contract `filterType` is `date` (see applyFilter). */
    private const array DATE_TYPES = ['date', 'datetime'];

    public function __construct(
        private readonly AttributeScopeResolver $scopeResolver,
        private readonly AttributeColumnBuilder $columnBuilder,
        private readonly AttributeDateFilterApplier $dateFilterApplier,
        private readonly AuthorizationRegistry $authorizationRegistry,
        private readonly RequestAttributeResolver $attributesResolver,
    ) {}

    /**
     * One category's effective attributes (null scope = the "Tutte" tab, D-3:
     * zero `attr.*` columns).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function forCategory(?int $categoryId): Collection
    {
        return $this->scopeResolver->forCategory($categoryId);
    }

    /**
     * The UNION across every category, deduped by `code` (D-4).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function union(): Collection
    {
        return $this->scopeResolver->union();
    }

    /**
     * $quote's OWN applicable attributes, reshaped to the plain-array shape
     * this class expects — `ApplicableAttribute::toArray()` carries the same
     * code/name/type/config/relation_target/options/sort_order fields
     * `CategoryHierarchy::effectiveAttributes()` rows do. Used by the write
     * path only (see WritesAttributeCells::updateCell()).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function rowAttributes(Quote $quote): Collection
    {
        return $this->attributesResolver->resolve($quote)
            ->map(static fn (ApplicableAttribute $attribute): array => $attribute->toArray());
    }

    /**
     * The MINIMAL declarations `TableCellUpdateService`/`CellValueValidator`
     * look a submitted `attr.<code>` up in (see AttributeColumnBuilder::raw()).
     *
     * @param  Collection<int, array<string, mixed>>  $attributes
     * @return array<int, array<string, mixed>>
     */
    public function rawColumns(Collection $attributes): array
    {
        return $attributes->map(fn (array $row): array => $this->columnBuilder->raw($row))->all();
    }

    /**
     * The FULL shapes `GET /columns` emits, ordered after the native columns.
     *
     * @param  Collection<int, array<string, mixed>>  $attributes
     * @return array<int, array<string, mixed>>
     */
    public function resolvedColumns(Collection $attributes, int $startOrder, bool $editable): array
    {
        $order = $startOrder;
        $resolved = [];

        foreach ($attributes as $attributeRow) {
            $order++;
            $resolved[] = $this->columnBuilder->resolved($attributeRow, $order, $editable);
        }

        return $resolved;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $attributes
     * @return array<int, string>
     */
    public function columnIds(Collection $attributes): array
    {
        return $attributes->map(fn (array $row): string => $this->columnBuilder->id($row))->all();
    }

    public function codeFor(string $columnId): ?string
    {
        return $this->columnBuilder->codeFor($columnId);
    }

    /**
     * The stored values of $attributes for one row, keyed by column id.
     *
     * @param  Collection<int, array<string, mixed>>  $attributes
     * @return array<string, mixed>
     */
    public function rowValues(Quote $row, Collection $attributes): array
    {
        $values = $row->attribute_values ?? [];
        $mapped = [];

        foreach ($attributes as $attributeRow) {
            $mapped[$this->columnBuilder->id($attributeRow)] = $values[$attributeRow['code']] ?? null;
        }

        return $mapped;
    }

    /**
     * `date`/`datetime` attributes (contract `filterType: "date"`, so the
     * frontend mounts `agDateColumnFilter`) go through
     * `AttributeDateFilterApplier` instead of the handler's own
     * `applyFilter()`: the handler's (`AppliesTextFilter`, via
     * `HandlesScalarStringField`) expects a TEXT payload, correct for spec
     * 0021's custom fields but NOT the `{dateFrom, dateTo}` shape a
     * date-range picker sends.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $attributeRow
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, array $attributeRow, array $filter): void
    {
        if (in_array($attributeRow['type'], self::DATE_TYPES, true)) {
            $this->dateFilterApplier->apply(
                $query,
                self::VALUES_COLUMN.'->'.$attributeRow['code'],
                $attributeRow['type'] === 'datetime',
                $filter,
            );

            return;
        }

        $this->columnBuilder->handlerFor($attributeRow)->applyFilter($query, self::VALUES_COLUMN, $attributeRow['code'], $filter);
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $attributeRow
     */
    public function applySort(Builder $query, array $attributeRow, string $direction): void
    {
        $this->columnBuilder->handlerFor($attributeRow)->applySort($query, self::VALUES_COLUMN, $attributeRow['code'], $direction);
    }

    /**
     * Excel-like distinct values (spec 0004/0005) of one attribute, narrowed
     * by $search in memory — the handler resolves the whole distinct set from
     * the JSON column, which has no `LIKE`-able name column of its own.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $attributeRow
     * @return array<int, string>
     */
    public function distinctValues(Builder $query, array $attributeRow, ?string $search, int $limit): array
    {
        $values = $this->columnBuilder->handlerFor($attributeRow)->distinctValues($query, self::VALUES_COLUMN, $attributeRow['code']);

        if ($search !== null && $search !== '') {
            $needle = mb_strtolower($search);
            $values = array_values(array_filter(
                $values,
                static fn (mixed $value): bool => str_contains(mb_strtolower((string) $value), $needle),
            ));
        }

        return array_slice($values, 0, $limit);
    }

    /**
     * The attribute row a column id addresses within $attributes, or null when
     * `$columnId` is not an `attr.<code>` column of that set.
     *
     * @param  Collection<int, array<string, mixed>>  $attributes
     * @return array<string, mixed>|null
     */
    public function attributeRowFor(string $columnId, Collection $attributes): ?array
    {
        $code = $this->columnBuilder->codeFor($columnId);

        return $code === null ? null : $attributes->firstWhere('code', $code);
    }

    /**
     * `request-management.update` AND `attribute_values` editable in the
     * `role_field_permissions` matrix (spec 0064 contract) — the SAME combined
     * ceiling+DB-config check `ResolvesEditableColumns`/
     * `TableCellUpdateService::assertFieldEditable()` apply to every other
     * native column, resolved directly here since `attr.*` columns are not
     * part of `columnsWithDefaultId()`.
     */
    public function valuesEditable(User $actor): bool
    {
        try {
            $authorization = $this->authorizationRegistry->resolve('request-management');
        } catch (ModelNotFoundException) {
            return false;
        }

        return $authorization->fieldPermissions($actor, new Quote)[AttributeColumnBuilder::EDITABLE_FIELD]->editable ?? false;
    }
}
