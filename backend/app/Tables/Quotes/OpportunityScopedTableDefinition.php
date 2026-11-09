<?php

declare(strict_types=1);

namespace App\Tables\Quotes;

use App\Models\User;
use App\Quotes\QuoteAttributeResolver;
use App\Tables\CategoryTabs\AttributeColumnCategories;
use App\Tables\CategoryTabs\AttributeGridTarget;
use App\Tables\CategoryTabs\Concerns\AppendsCategoryAttributeColumns;
use App\Tables\CategoryTabs\Concerns\WritesCategoryAttributeCells;
use App\Tables\CategoryTabs\FilterScopedAttributeColumns;
use App\Tables\CustomFields\DelegatesUnaugmentedTableMethods;
use App\Tables\RegistryScopable;
use App\Tables\RequestManagement\AttributeColumnBuilder;
use App\Tables\RequestManagement\AttributeGridColumns;
use App\Tables\TableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Decorator that scopes the `quotes` domain to a single Opportunity (spec
 * 0067, D-1): the second concrete case of table row-scoping in the codebase,
 * after `App\Tables\RequestManagement\AttributeScopedTableDefinition` — but,
 * unlike that one, `quotes.opportunity_id` is a REAL, NOT NULL, indexed
 * column, so a single `where()` in `baseQuery()` is the entire scope. Every
 * other method (columns/filters/actions/sort-filter-search allow-lists/
 * resolveConfig/defaultColumnLayout) is IDENTICAL scoped or not, hence pure
 * passthrough to $inner: no `scopeToAllOpportunities()` twin is needed, and
 * — unlike `AttributeScopedTableDefinition` — the column-preferences/filter
 * persistence endpoints (`TableController::savePreferences()`/
 * `saveFilters()`) never call the setter and stay entirely unaware this
 * decorator exists (D-4: preferences/filters are shared, not per-Opportunity).
 *
 * Pure passthrough when no scope has been set (rows()/values()/columns()
 * without `opportunityId`): every existing caller of the `quotes` domain
 * (the standalone Offerte list page) is byte-identical to today (AC-002).
 *
 * Composed OUTSIDE `CustomFieldAwareTableDefinition` in
 * `TableRegistry::resolve()` (quotes is custom-fieldable): `$inner` is
 * already the custom-field-augmented definition, so `custom.*` columns work
 * unchanged inside an Opportunity-scoped grid too.
 *
 * Also the `RegistryScopable` of `quotes` (spec 0199): the client of an
 * Offerta is its Opportunity's, so the scope is a `whereIn` on the client's
 * opportunities, in AND with the Opportunity scope. This is the outermost
 * decorator of the domain, hence the one `instanceof RegistryScopable` sees.
 *
 * It also adds the `attr.<code>` columns of the Quote context (spec 0374) for
 * the category the advanced filters select (`FilterScopedAttributeColumns`):
 * no row logic of its own, the filters already narrow the rows. The "columns /
 * filters / sort-filter-search allow-lists" passthrough above holds for every
 * other column.
 */
class OpportunityScopedTableDefinition implements FilterScopedAttributeColumns, RegistryScopable, TableDefinition
{
    use AppendsCategoryAttributeColumns, DelegatesUnaugmentedTableMethods, WritesCategoryAttributeCells {
        // Both declare editableColumnIds()/updateCell(); the attr.* aware pair
        // delegates to $this->inner for every other column.
        WritesCategoryAttributeCells::editableColumnIds insteadof DelegatesUnaugmentedTableMethods;
        WritesCategoryAttributeCells::updateCell insteadof DelegatesUnaugmentedTableMethods;
    }

    private readonly AttributeGridColumns $attributeColumns;

    private ?int $opportunityScope = null;

    private ?int $registryScope = null;

    public function __construct(
        private readonly TableDefinition $inner,
        AttributeGridColumns $attributeColumns,
        private readonly AttributeColumnCategories $columnCategories,
        private readonly QuoteCellWriter $cellWriter,
        private readonly QuoteAttributeResolver $attributeResolver,
    ) {
        $this->attributeColumns = $attributeColumns->forTarget(AttributeGridTarget::quote());
    }

    /**
     * Narrows `baseQuery()` to one Opportunity's Offerte (null = no scope,
     * the "Offerte" list page's own unscoped behavior).
     */
    public function scopeToOpportunity(?int $opportunityId): void
    {
        $this->opportunityScope = $opportunityId;
    }

    /**
     * @return Builder<Model>
     */
    public function baseQuery(): Builder
    {
        $query = $this->inner->baseQuery();

        if ($this->opportunityScope !== null) {
            $query->where('quotes.opportunity_id', $this->opportunityScope);
        }

        if ($this->registryScope !== null) {
            $query->whereIn(
                'quotes.opportunity_id',
                DB::table('opportunities')->select('id')->where('registry_id', $this->registryScope),
            );
        }

        return $query;
    }

    /**
     * Narrows `baseQuery()` to the Offerte of one client's Opportunities
     * (null = no scope).
     */
    public function scopeToRegistry(?int $registryId): void
    {
        $this->registryScope = $registryId;
    }

    protected function writeAttributeValue(Model $row, string $code, mixed $value, User $actor): Model
    {
        return $this->cellWriter->write($row, AttributeColumnBuilder::EDITABLE_FIELD, [$code => $value], $actor, null);
    }

    protected function applicableAttributesOf(Model $row): Collection
    {
        return $this->attributeColumns->fromApplicable($this->attributeResolver->resolve($row));
    }
}
