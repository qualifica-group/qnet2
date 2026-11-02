<?php

declare(strict_types=1);

namespace App\Tables\Opportunities;

use App\Products\ProductCategoryBranches;
use App\Tables\CategoryTabs\DelegatesRowScopedTableMethods;
use App\Tables\CustomFields\DelegatesUnaugmentedTableMethods;
use App\Tables\ProductCategoryScopable;
use App\Tables\RegistryScopable;
use App\Tables\TableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Decorator that scopes the `opportunities` domain to one product category
 * (spec 0335, D-2): an Opportunity shows in the tab of every category of its
 * `opportunity_product_lines`. It is the OUTERMOST decorator of the domain,
 * so it also implements `RegistryScopable` and forwards the registry scope to
 * the inner `RegistryScopedTableDefinition`: callers test `instanceof`, and
 * both interfaces must be seen on the same object.
 *
 * Only the row set changes: columns, config and layout are identical with or
 * without a category (D-3), hence pure passthrough for everything else.
 */
class OpportunityCategoryScopedTableDefinition implements ProductCategoryScopable, RegistryScopable, TableDefinition
{
    use DelegatesRowScopedTableMethods;
    use DelegatesUnaugmentedTableMethods;

    private ?int $categoryScope = null;

    private ?int $branchScope = null;

    public function __construct(
        private readonly TableDefinition&RegistryScopable $inner,
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

    public function scopeToRegistry(?int $registryId): void
    {
        $this->inner->scopeToRegistry($registryId);
    }

    /**
     * @return Builder<Model>
     */
    public function baseQuery(): Builder
    {
        $query = $this->inner->baseQuery();

        $categoryIds = $this->scopedCategoryIds();

        if ($categoryIds === null) {
            return $query;
        }

        // IN-subquery rather than whereHas: starts from the category's own index.
        return $query->whereIn(
            $query->qualifyColumn('id'),
            static fn (QueryBuilder $lines) => $lines
                ->select('opportunity_id')
                ->from('opportunity_product_lines')
                ->whereIn('product_category_id', $categoryIds),
        );
    }

    /**
     * The categories the rows are narrowed to: one, a whole branch, or null.
     *
     * @return array<int, int>|null
     */
    private function scopedCategoryIds(): ?array
    {
        if ($this->branchScope !== null) {
            return $this->branches->branchIds($this->branchScope);
        }

        return $this->categoryScope === null ? null : [$this->categoryScope];
    }
}
