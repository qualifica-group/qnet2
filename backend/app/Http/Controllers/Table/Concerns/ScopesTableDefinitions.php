<?php

declare(strict_types=1);

namespace App\Http\Controllers\Table\Concerns;

use App\Tables\CategoryTabs\AttributeColumnsScopable;
use App\Tables\ProductCategoryScopable;
use App\Tables\Quotes\OpportunityScopedTableDefinition;
use App\Tables\ReferentScopable;
use App\Tables\RegistryScopable;
use App\Tables\TableDefinition;
use App\Tables\WorkOrders\QuoteScopedTableDefinition;

/**
 * The `scopeTo*` helpers of `TableController`: each narrows a definition that
 * supports the scope and is a no-op for every other domain.
 */
trait ScopesTableDefinitions
{
    /**
     * Spec 0064/0130: narrows a `ProductCategoryScopable` definition
     * (`request-management`, `enrollee-management`) to one product category —
     * its rows, its `attr.*` columns and the GA2 relabel. A no-op for every
     * other domain.
     *
     * Also called by `savePreferences()`/`saveFilters()` AFTER their write, on
     * the tab the client sent: those responses are read straight into the
     * client's per-tab config cache, so they must carry the same `attr.*`
     * columns the GET does. It reaches nothing already persisted. `$branchId`
     * is a root tab (spec 0337): its rows and the root's `attr.*` columns.
     */
    protected function scopeToProductCategory(TableDefinition $definition, ?int $productCategoryId, ?int $branchId = null): void
    {
        if ($definition instanceof ProductCategoryScopable) {
            $definition->scopeToProductCategory($productCategoryId);
            $definition->scopeToProductCategoryBranch($branchId);
        }
    }

    /**
     * Spec 0064, D-4 (restored with the `attr.*` columns, user directive
     * 2026-08-31): widens an `AttributeColumnsScopable` definition's SSRM
     * allow-lists to the union of every category's `attr.*` columns, so
     * saving filter preferences from any tab never 422s. A no-op for every
     * other domain.
     */
    protected function scopeToAllProductCategories(TableDefinition $definition): void
    {
        if ($definition instanceof AttributeColumnsScopable) {
            $definition->scopeToAllProductCategories();
        }
    }

    /**
     * Spec 0067: narrows an `OpportunityScopedTableDefinition` (only
     * `quotes`) to one Opportunity's Offerte. A no-op for every other
     * domain. Never called from `savePreferences()`/`saveFilters()`/
     * `resetPreferences()`/`resetFilters()`/`bulkDelete()`/`updateRow()`
     * (D-4/D-1: those endpoints are not Opportunity-scoped).
     */
    protected function scopeToOpportunity(TableDefinition $definition, ?int $opportunityId): void
    {
        if ($definition instanceof OpportunityScopedTableDefinition) {
            $definition->scopeToOpportunity($opportunityId);
        }
    }

    /**
     * Spec 0095: narrows a `QuoteScopedTableDefinition` (only `work-orders`)
     * to one Quote's own Commesse. A no-op for every other domain. Never
     * called from `savePreferences()`/`saveFilters()`/`resetPreferences()`/
     * `resetFilters()`/`bulkDelete()`/`updateRow()`, mirroring
     * `scopeToOpportunity()`.
     */
    protected function scopeToQuote(TableDefinition $definition, ?int $quoteId): void
    {
        if ($definition instanceof QuoteScopedTableDefinition) {
            $definition->scopeToQuote($quoteId);
        }
    }

    /**
     * Spec 0199: narrows a `RegistryScopable` definition (`opportunities`,
     * `quotes`, `work-orders`, `tasks`) to one client's records. A no-op for
     * every other domain, and never called from the persistence endpoints,
     * mirroring `scopeToQuote()`.
     */
    protected function scopeToRegistry(TableDefinition $definition, ?int $registryId): void
    {
        if ($definition instanceof RegistryScopable) {
            $definition->scopeToRegistry($registryId);
        }
    }

    /**
     * Spec 0336: narrows a `ReferentScopable` definition
     * (`commission-configurations`) to one Referente's rules, mirroring
     * `scopeToRegistry()`.
     */
    protected function scopeToReferent(TableDefinition $definition, ?int $referentId): void
    {
        if ($definition instanceof ReferentScopable) {
            $definition->scopeToReferent($referentId);
        }
    }
}
