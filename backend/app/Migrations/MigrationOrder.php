<?php

namespace App\Migrations;

/**
 * Recommended chronological order for running the migration sources (spec 0013).
 *
 * To import every record AND its relational links correctly, run the sources
 * phase by phase, in order. A source in a later phase references — via `old_id`
 * — records created by the earlier phases; running out of order leaves those
 * references as non-fatal "parent not yet migrated" warnings instead of resolved
 * links. Sources inside the same phase have no cross-dependency and may run in
 * any order.
 *
 * This is ordering guidance, not an enforced gate: the engine still imports
 * whatever single source is requested. It is deliberately distinct from the
 * declaration order in config/migrations.php (a registry map, not a plan).
 *
 * Single source of truth for the order — update PHASES here as sources are
 * added or their dependencies change.
 */
final class MigrationOrder
{
    /**
     * Ordered import phases. Each value is a MigrationSource::key() as
     * registered in config('migrations.definitions').
     *
     * @var array<int, array<int, string>>
     */
    public const PHASES = [
        // Phase 1 — independent anchor entities that later phases link to.
        // `sources`, `tags` and `vat-rates` are plain lookups with no
        // cross-source reference; `sectors` references only itself (parent_id
        // remapped via old_id, relinked within its own run); `roles` are
        // adopted/created by name and are referenced by `users` via old_id, so
        // they anchor here too; `payment-methods` is likewise a plain lookup,
        // referenced by its consumer modules (quotes first), not referencing.
        ['business-functions', 'companies', 'operational-sites', 'referent-types', 'sources', 'tags', 'sectors', 'vat-rates', 'payment-methods', 'roles'],

        // Phase 2 — entities that reference the phase 1 anchors via old_id:
        // users (companies/sites/functions/roles), company-sites (companies)
        // and referents (referent-types).
        ['users', 'company-sites', 'referents'],

        // Phase 3 — associations that link phase 2 users onto phase 1 entities:
        // business-function operators (pivot) + responsible (manager_id) need
        // both the function and its users already migrated.
        ['business-function-members'],

        // Phase 4 — product anchors imported right before products: `attributes`
        // are custom-field definitions and `product-categories` reference only
        // themselves (parent_id remapped within their own run). Nothing in the
        // earlier phases references either, so they sit here next to `products`.
        ['attributes', 'product-categories'],

        // Phase 5 — products reference the phase 4 product-categories AND the
        // phase 1 vat-rates via old_id (only `supplier` is left unremapped); the
        // attribute/category pivot is the association pass that needs BOTH phase
        // 4 anchors migrated. The two have no cross-dependency, so they share
        // the phase.
        ['product-category-attributes', 'products'],
    ];

    /**
     * The ordered phases (each an ordered list of source keys).
     *
     * @return array<int, array<int, string>>
     */
    public static function phases(): array
    {
        return self::PHASES;
    }
}
