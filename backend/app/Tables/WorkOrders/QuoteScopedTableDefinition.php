<?php

declare(strict_types=1);

namespace App\Tables\WorkOrders;

use App\Enums\WorkOrderType;
use App\Tables\CategoryTabs\DelegatesRowScopedTableMethods;
use App\Tables\CustomFields\DelegatesUnaugmentedTableMethods;
use App\Tables\RegistryScopable;
use App\Tables\TableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
 */
class QuoteScopedTableDefinition implements RegistryScopable, TableDefinition
{
    use DelegatesRowScopedTableMethods;
    use DelegatesUnaugmentedTableMethods;

    private ?int $quoteScope = null;

    private ?int $registryScope = null;

    private ?WorkOrderType $typeScope = null;

    public function __construct(private readonly TableDefinition $inner) {}

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

        return $query;
    }

    /**
     * Spec 0359, D-2: narrows `baseQuery()` to one commessa type. Only the two
     * menu pages send it; the Contratto and Anagrafica tabs leave it null and
     * keep every type.
     */
    public function scopeToWorkOrderType(?WorkOrderType $type): void
    {
        $this->typeScope = $type;
    }

    /**
     * The figures of the status tabs, which only the "Commesse" page shows:
     * every other grid of the domain is spared the query.
     *
     * @param  Builder<Model>  $query
     * @return array<string, int|float|string|null>
     */
    public function aggregates(Builder $query): array
    {
        return $this->typeScope === WorkOrderType::Processing ? $this->inner->aggregates($query) : [];
    }

    /**
     * Narrows `baseQuery()` to the Commesse of one client's Offerte (null =
     * no scope).
     */
    public function scopeToRegistry(?int $registryId): void
    {
        $this->registryScope = $registryId;
    }
}
