<?php

namespace Database\Seeders\QualificaCatalog;

use App\Models\ProductCategory;

/**
 * The per-node INHERITANCE BARRIERS the Qualifica catalogue declares.
 * Sibling of CatalogRootRules, which owns the rules of a ROOT and re-syncs its
 * whole subtree: a barrier is the opposite kind of rule — it belongs to one
 * node, it is not handed down, and nothing below it is touched.
 *
 * REALIGNED on every run, in both directions: an installation seeded before a
 * barrier existed must actually see the node stop resolving its ancestors'
 * fields, which a create-only write would skip.
 *
 * Runs AFTER the whole tree exists and BEFORE any layout is composed
 * (QualificaCatalogSeeder): AttributeLayoutService validates each code against
 * the category's EFFECTIVE set, which is exactly what a barrier changes.
 */
final class CategoryInheritanceRules
{
    /**
     * Category name => the barrier columns it owns. Names are bound by
     * identity to QualificaCatalogSeeder::CATALOG, so a rename there breaks
     * loudly here instead of silently leaving a node in inheritance.
     *
     * "DIL" sells an offer with a field set of its OWN (user directive
     * 2026-09-10): the six fields ContactProcessingAttributeCatalogue assigns
     * on it, not the whole Formazione set — "Dati corso", "Dati Aula" and the
     * training block of "Dati Lavorazione Contatto" — it would otherwise pull
     * down from the root. It stays a child of "Formazione": the barrier
     * changes what it resolves, never where it hangs.
     *
     * Only the OFFERTA is cut. `inherits_work_order_attributes` is left alone
     * on purpose — the directive is about the offer form, and the two contexts
     * are independent columns.
     *
     * @var array<string, array<string, bool>>
     */
    private const array BARRIERS = [
        'DIL' => ['inherits_quote_attributes' => false],
    ];

    public function apply(): void
    {
        foreach (self::BARRIERS as $categoryName => $columns) {
            /** @var ProductCategory $category */
            $category = ProductCategory::query()->where('name', $categoryName)->firstOrFail();

            // A clean save runs no UPDATE, so a re-run on an already-aligned
            // node costs nothing and logs no activity.
            $category->fill($columns)->save();
        }
    }
}
