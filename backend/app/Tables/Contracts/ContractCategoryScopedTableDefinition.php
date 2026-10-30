<?php

declare(strict_types=1);

namespace App\Tables\Contracts;

use App\Products\ProductCategoryBranches;
use App\Tables\CategoryTabs\DelegatesRowScopedTableMethods;
use App\Tables\CategoryTabs\RevenueCategoryQuoteIds;
use App\Tables\CustomFields\DelegatesUnaugmentedTableMethods;
use App\Tables\ProductCategoryScopable;
use App\Tables\TableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Decorator that scopes the `contracts` domain to one product category (spec
 * 0335, D-8): a Contratto shows in the tab of every category of the REVENUE
 * lines of its own quote (`contracts.quote_id`), with no fallback. OUTERMOST
 * decorator of the domain; only the row set changes, the rest is passthrough.
 */
class ContractCategoryScopedTableDefinition implements ProductCategoryScopable, TableDefinition
{
    use DelegatesRowScopedTableMethods;
    use DelegatesUnaugmentedTableMethods;

    private ?int $categoryScope = null;

    private ?int $branchScope = null;

    public function __construct(
        private readonly TableDefinition $inner,
        private readonly ProductCategoryBranches $branches,
    ) {}

    public function scopeToProductCategory(?int $productCategoryId): void
    {
        $this->categoryScope = $productCategoryId;
    }

    public function scopeToProductCategoryBranch(?int $rootId): void
    {
        $this->branchScope = $rootId;
    }

    /**
     * @return Builder<Model>
     */
    public function baseQuery(): Builder
    {
        $query = $this->inner->baseQuery();

        if ($this->branchScope !== null) {
            return $query->whereIn('contracts.quote_id', RevenueCategoryQuoteIds::subquery($this->branches->branchIds($this->branchScope)));
        }

        if ($this->categoryScope === null) {
            return $query;
        }

        return $query->whereIn('contracts.quote_id', RevenueCategoryQuoteIds::subquery($this->categoryScope));
    }
}
