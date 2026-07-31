<?php

declare(strict_types=1);

namespace App\Services\Table;

use App\DataObjects\Shared\ForSelectQuery;
use App\Services\CampaignService;
use App\Services\OperationalSiteService;
use App\Services\ProductService;
use App\Services\RegistryService;
use App\Services\SourceService;
use App\Services\UserService;

/**
 * D-2 of spec 0054: a relation column's submitted id must be MORE than
 * `exists:<table>,id` — it must be among the rows the actor could actually
 * pick from that resource's own `/for-select` endpoint, otherwise inline-edit
 * becomes a channel to link an otherwise-invisible record. The guard mirrors
 * exactly what the real `/for-select` HTTP endpoint enforces: the value must
 * resolve through the SAME query each `<Resource>Service::forSelect()` already
 * runs (never a hand-rolled scope query here): `limit: 0` skips the default
 * page entirely and `ids: [$value]` forces the service's own edit-mode
 * hydration path to resolve just that one id through its real base query.
 *
 * There is no `{resource}.viewAny` pre-check any more: since ADR 0011 was
 * amended (2026-07-31) the `*ForSelectController`s themselves are gated by
 * `auth:sanctum` alone, so requiring it here would refuse a pick the picker
 * offered — the very mismatch D-2 exists to prevent.
 *
 * Bounded to the relation resources spec 0054 activates (leads' 5 columns).
 * Extend the match arm when a future column activates a new relation
 * resource — an unmapped resource fails closed (never editable), consistent
 * with every other fail-safe in this engine.
 */
final class RelationValueScopeChecker
{
    public function __construct(
        private readonly RegistryService $registries,
        private readonly CampaignService $campaigns,
        private readonly OperationalSiteService $operationalSites,
        private readonly SourceService $sources,
        private readonly UserService $users,
        private readonly ProductService $products,
    ) {}

    /**
     * Whether $value is a real id selectable from $resource's own
     * `/for-select` query — a nonexistent id and an out-of-scope id are
     * indistinguishable here by design (D-2: both are 422, neither confirms
     * which).
     */
    public function inScope(string $resource, int $value): bool
    {
        $query = new ForSelectQuery(search: null, offset: 0, limit: 0, ids: [$value]);

        $items = match ($resource) {
            'registries' => $this->registries->forSelect($query)->items,
            'campaigns' => $this->campaigns->forSelect($query)->items,
            'operational-sites' => $this->operationalSites->forSelect($query)->items,
            'sources' => $this->sources->forSelect($query)->items,
            'users' => $this->users->forSelect($query)->items,
            // User directive 2026-07-23: the `products_of_interest` MULTISELECT
            // column checks every submitted id through this same gate, one id
            // at a time (CellValueValidator::validateIdListValue).
            'products' => $this->products->forSelect($query)->items,
            default => null,
        };

        if ($items === null) {
            return false;
        }

        return $items->contains(static fn (mixed $item): bool => (int) $item->getKey() === $value);
    }
}
