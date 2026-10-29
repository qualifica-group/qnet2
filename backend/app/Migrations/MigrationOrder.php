<?php

namespace App\Migrations;

/**
 * Recommended chronological order for running the migration sources (spec 0013).
 * Each source's MigrationSource::dependencies() must sit in an earlier phase
 * (tests/Unit/Migrations/MigrationDependenciesTest).
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
        // referenced by its consumer modules (quotes first), not referencing;
        // `email-templates` and
        // `document-bundles` (spec 0175, D-13) are likewise plain, unlinked
        // lookups — `document-bundles` additionally copies its own files in,
        // referencing nothing outside the pair of legacy tables it reads.
        // `business-functions` carries user ids it cannot resolve yet (users
        // come in phase 2): `business-function-members` links them in phase 3.
        ['business-functions', 'companies', 'operational-sites', 'referent-types', 'sources', 'tags', 'sectors', 'vat-rates', 'payment-methods', 'roles', 'email-templates', 'document-bundles'],

        // Phase 2 — entities that reference the phase 1 anchors via old_id:
        // users (companies/sites/roles), company-sites (companies) and
        // referents (referent-types). `financial-accounts` (spec 0297) link
        // their company only; invoices and installments link them later.
        ['users', 'company-sites', 'referents', 'financial-accounts'],

        // Phase 3 — associations that link phase 2 users onto phase 1 entities:
        // business-function operators (pivot) + responsible (manager_id) need
        // both the function and its users already migrated.
        ['business-function-members'],

        // Phase 4 — product anchors imported right before products: `attributes`
        // are custom-field definitions and `product-categories` reference
        // themselves (parent_id remapped within their own run) and the phase 1
        // business-functions. Nothing in the earlier phases references either,
        // so they sit here next to `products`.
        ['attributes', 'product-categories'],

        // Phase 5 — products reference the phase 4 product-categories AND the
        // phase 1 vat-rates via old_id (only `supplier` is left unremapped); the
        // attribute/category pivot is the association pass that needs BOTH phase
        // 4 anchors migrated. The two have no cross-dependency, so they share
        // the phase. `cost-products` (spec 0174) references only the phase 1
        // vat-rates and provisions its own "Costi" branch, so it sits beside
        // `products`. `work-order-workflows` (spec 0322) link the phase 1
        // business functions and the phase 4 product-categories by old_id and
        // nothing else, so they sit here too.
        ['product-category-attributes', 'products', 'cost-products', 'work-order-workflows'],

        // Phase 6 — category form layouts (spec 0181): AttributeLayoutValidator
        // only accepts codes the category has ALREADY linked for the context,
        // so this needs the phase 5 `product-category-attributes` pivot.
        // `task-templates` (spec 0172, D-13; moved here by spec 0323, D-9): its
        // stages/items remap only within the record being imported, but each
        // action links a working status by the `old_id` of the phase 5
        // `work-order-workflows`.
        ['attribute-layouts', 'task-templates'],

        // Phases 7 and 9-11 — the legacy operational records (spec 0189), a strict
        // parent chain, one phase per link. Registries remap users, referents,
        // sources and sectors (phases 1-2); opportunities need their registry
        // plus categories and operational sites; quotes need their opportunity
        // plus products, VAT rates, payment methods and company sites; work
        // orders need their quote and its lines (and write the payment data of
        // those lines, spec 0201, against the seeded payment statuses).
        ['registries'],

        // Phase 8 — spec 0203: the product supplier link and the legacy
        // commission rules. Both resolve registries via old_id (supplier /
        // company recipient) and need referents, users, categories and products
        // from the earlier phases; they have no dependency on each other.
        ['product-suppliers', 'commission-configurations'],
        ['opportunities'],
        ['quotes'],
        ['work-orders'],

        // Phase 12 — spec 0289: the legacy tasks. They link users (phase 2) and
        // the commessa and its stages (phase 11), and optionally registries,
        // referents and opportunities (phases 7, 9). `proforma-requests`
        // (spec 0300) link the commessa and its quote, no task: same phase, as
        // `work-order-progress` (spec 0321), which only needs the commessa;
        // `work-order-workflow-statuses` (spec 0322) set the current status of
        // the commessa from the phase 5 workflows.
        ['tasks', 'proforma-requests', 'work-order-progress', 'work-order-workflow-statuses'],

        // Phase 13 — spec 0294, start of the accounting part: the legacy RDA.
        // They link users, companies, company sites, operational sites and
        // business functions (phases 1-3) and the commessa (phase 11), and
        // optionally the customer/supplier registries and the VAT rates.
        ['purchase-requests'],

        // Phase 14 — spec 0296: the legacy ODA (open ones and those turned into
        // supplier invoices). They need the supplier registries and the
        // company sites, and link the RDA lines of phase 13 and the work
        // orders of phase 11 when migrated.
        ['purchase-orders'],

        // Phase 15 — spec 0298: the legacy supplier invoices. Each bills the
        // orders of phase 14 migrated from the same legacy records, and its
        // lines their order lines.
        ['purchase-invoices'],

        // Phase 16 — spec 0301: the legacy active documents (customer and
        // institution proformas). They link the proforma requests of phase 12,
        // the work orders and quotes, the products and the financial accounts.
        ['invoices'],

        // Phase 17 — spec 0302: the legacy collections, recorded on the
        // installments of the phase 16 documents where still missing.
        ['collections'],

        // Phase 18 — spec 0311: the notes of every legacy module (work orders,
        // opportunities, tasks; registries, referents and purchase requests
        // since spec 0312), last so every record they belong to exists.
        ['notes'],
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

    /**
     * The 1-based phase a source belongs to, or null when it is not ordered.
     */
    public static function phaseOf(string $key): ?int
    {
        foreach (self::PHASES as $index => $keys) {
            if (in_array($key, $keys, true)) {
                return $index + 1;
            }
        }

        return null;
    }
}
