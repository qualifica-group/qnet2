<?php

declare(strict_types=1);

namespace App\Tables\WorkOrders;

use App\Enums\WorkOrderType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Products\ProductCategoryBranches;
use App\Tables\CategoryTabs\AttributeColumnsScopable;
use App\Tables\CategoryTabs\AttributeGridTarget;
use App\Tables\CategoryTabs\Concerns\AppendsCategoryAttributeColumns;
use App\Tables\CategoryTabs\Concerns\WritesCategoryAttributeCells;
use App\Tables\CategoryTabs\WorkOrderSourceQuotes;
use App\Tables\CustomFields\DelegatesUnaugmentedTableMethods;
use App\Tables\ProductCategoryScopable;
use App\Tables\RegistryScopable;
use App\Tables\RequestManagement\AttributeColumnBuilder;
use App\Tables\RequestManagement\AttributeGridColumns;
use App\Tables\TableDefinition;
use App\WorkOrders\WorkOrderAttributeResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Decorator that scopes the `work-orders` domain to a single Quote (spec
 * 0095, D-8): the Contratto detail's "Commesse" tab, filtered to the
 * offer's own work orders. `work_orders.quote_id` is a REAL, NOT NULL,
 * indexed column (D-5 of spec 0093), so a single `where()` in `baseQuery()`
 * is the entire scope — the scope is `quoteId`, not `contractId`, because
 * Contratto and Offerta are 1:1 (`contracts.quote_id` UNIQUE) and
 * `work_orders.quote_id` already exists, so filtering by it needs no join.
 * Ricalca 1:1 `App\Tables\Quotes\OpportunityScopedTableDefinition` (spec
 * 0067) — every other method (columns/filters/actions/sort-filter-search
 * allow-lists/resolveConfig/defaultColumnLayout) is IDENTICAL scoped or
 * not, hence pure passthrough to $inner.
 *
 * Pure passthrough when no scope has been set (rows()/values()/columns()
 * without `quoteId`): every existing caller of the `work-orders` domain
 * (the standalone Commesse list page) is byte-identical to today (AC-054).
 *
 * Composed OUTSIDE `CustomFieldAwareTableDefinition` in
 * `TableRegistry::resolve()` (`work-orders` is custom-fieldable): `$inner`
 * is already the custom-field-augmented definition, so `custom.*` columns
 * work unchanged inside a Quote-scoped grid too.
 *
 * Also the `RegistryScopable` of `work-orders` (spec 0199): the client of a
 * Commessa is its Offerta's Opportunity's, so the scope is a `whereIn` on the
 * client's Offerte, in AND with the Quote scope. This is the outermost
 * decorator of the domain, hence the one `instanceof RegistryScopable` sees.
 *
 * And its `ProductCategoryScopable` (spec 0335 rev.3, D-9): a Commessa shows in
 * the tab of every category of the REVENUE lines of its source offers.
 *
 * And the `attr.<code>` columns of the tab's category (spec 0350, D-2): the
 * WorkOrder-context flexible fields of `work_orders.attribute_values` (never
 * the offer's), editable in the cell, via the two `*CategoryAttribute*` traits.
 */
class QuoteScopedTableDefinition implements AttributeColumnsScopable, ProductCategoryScopable, RegistryScopable, TableDefinition
{
    use AppendsCategoryAttributeColumns, DelegatesUnaugmentedTableMethods, WritesCategoryAttributeCells {
        // Both declare editableColumnIds()/updateCell(); the attr.* aware pair
        // delegates to $this->inner for every other column.
        WritesCategoryAttributeCells::editableColumnIds insteadof DelegatesUnaugmentedTableMethods;
        WritesCategoryAttributeCells::updateCell insteadof DelegatesUnaugmentedTableMethods;
    }

    private readonly AttributeGridColumns $attributeColumns;

    private ?int $quoteScope = null;

    private ?int $registryScope = null;

    private ?int $categoryScope = null;

    private ?int $branchScope = null;

    private ?WorkOrderType $typeScope = null;

    public function __construct(
        private readonly TableDefinition $inner,
        private readonly ProductCategoryBranches $branches,
        AttributeGridColumns $attributeColumns,
        private readonly WorkOrderCellWriter $cellWriter,
        private readonly WorkOrderAttributeResolver $attributeResolver,
    ) {
        $this->attributeColumns = $attributeColumns->forTarget(AttributeGridTarget::workOrder());
    }

    /**
     * Narrows `baseQuery()` to one Quote's own work orders (null = no scope,
     * the "Commesse" list page's own unscoped behavior).
     */
    public function scopeToQuote(?int $quoteId): void
    {
        $this->quoteScope = $quoteId;
    }

    /**
     * @return Builder<Model>
     */
    public function baseQuery(): Builder
    {
        $query = $this->inner->baseQuery();

        // Spec 0267, D-9: a multi-offer commessa belongs to every offer / client
        // it holds at least one line of.
        if ($this->quoteScope !== null) {
            $query->withQuoteLineOfQuote($this->quoteScope);
        }

        if ($this->typeScope !== null) {
            $query->where('work_orders.type', $this->typeScope->value);
        }

        if ($this->registryScope !== null) {
            $query->withQuoteLineOfRegistry($this->registryScope);
        }

        if ($this->categoryScope !== null) {
            $query->whereIn('work_orders.id', WorkOrderSourceQuotes::withRevenueCategory($this->categoryScope));
        }

        if ($this->branchScope !== null) {
            $query->whereIn('work_orders.id', WorkOrderSourceQuotes::withRevenueCategory($this->branches->branchIds($this->branchScope)));
        }

        return $query;
    }

    /**
     * Spec 0352, D-2: narrows `baseQuery()` to one commessa type. Only the two
     * menu pages send it; the Contratto and Anagrafica tabs leave it null and
     * keep every type.
     */
    public function scopeToWorkOrderType(?WorkOrderType $type): void
    {
        $this->typeScope = $type;
    }

    public function scopeToProductCategory(?int $productCategoryId): void
    {
        $this->categoryScope = $productCategoryId;
    }

    public function scopeToProductCategoryBranch(?int $rootId): void
    {
        $this->branchScope = $rootId;
    }

    /**
     * Narrows `baseQuery()` to the Commesse of one client's Offerte (null =
     * no scope).
     */
    public function scopeToRegistry(?int $registryId): void
    {
        $this->registryScope = $registryId;
    }

    protected function writeAttributeValue(Model $row, string $code, mixed $value, User $actor): Model
    {
        /** @var WorkOrder $row */
        return $this->cellWriter->write($row, AttributeColumnBuilder::EDITABLE_FIELD, [$code => $value], $actor);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function applicableAttributesOf(Model $row): Collection
    {
        /** @var WorkOrder $row */
        return $this->attributeColumns->fromApplicable($this->attributeResolver->resolve($row));
    }
}
