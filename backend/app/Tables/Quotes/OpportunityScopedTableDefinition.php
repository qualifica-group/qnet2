<?php

declare(strict_types=1);

namespace App\Tables\Quotes;

use App\Tables\CategoryTabs\DelegatesRowScopedTableMethods;
use App\Tables\CustomFields\DelegatesUnaugmentedTableMethods;
use App\Tables\RegistryScopable;
use App\Tables\TableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
 */
class OpportunityScopedTableDefinition implements RegistryScopable, TableDefinition
{
    use DelegatesRowScopedTableMethods;
    use DelegatesUnaugmentedTableMethods;

    private ?int $opportunityScope = null;

    private ?int $registryScope = null;

    public function __construct(private readonly TableDefinition $inner) {}

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
}
