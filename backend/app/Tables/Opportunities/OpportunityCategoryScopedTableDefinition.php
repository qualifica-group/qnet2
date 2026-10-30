<?php

declare(strict_types=1);

namespace App\Tables\Opportunities;

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

    public function __construct(private readonly TableDefinition&RegistryScopable $inner) {}

    public function scopeToProductCategory(?int $productCategoryId): void
    {
        $this->categoryScope = $productCategoryId;
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

        if ($this->categoryScope === null) {
            return $query;
        }

        $categoryScope = $this->categoryScope;

        // IN-subquery rather than whereHas: starts from the category's own index.
        return $query->whereIn(
            $query->qualifyColumn('id'),
            static fn (QueryBuilder $lines) => $lines
                ->select('opportunity_id')
                ->from('opportunity_product_lines')
                ->where('product_category_id', $categoryScope),
        );
    }
}
