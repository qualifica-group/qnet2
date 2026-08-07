<?php

namespace Database\Seeders\QualificaCatalog;

use App\Enums\CategoryManagementMode;
use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryManagementModeInheritance;
use App\Services\ProductCategories\SingleQuotePerOpportunityInheritance;

/**
 * The ROOT-OWNED rules the Qualifica catalogue declares for its two roots.
 * Split out of QualificaCatalogSeeder (which stayed at its size limit,
 * engineering.md §6) — it holds the map AND the write, since the two are
 * meaningless apart.
 *
 * Both rules are REALIGNED on every run, in both directions, and the subtree
 * is re-synced after each: an installation seeded before a rule existed must
 * actually see its branch adopt it, which a create-only write would skip.
 *
 * "Formazione" is worked one product line and ONE offer at a time (user
 * directive 2026-08-03 for the line, 2026-08-07 for the offer): a training
 * opportunity is a single course sold once, never a basket of alternatives.
 * "Consulenza" stays unconstrained on both — listed explicitly rather than
 * left to the column defaults so a re-run realigns it too.
 */
final class CatalogRootRules
{
    /**
     * Root name => the rules it owns, as `product_categories` columns.
     *
     * @var array<string, array{management_mode: CategoryManagementMode, single_quote_per_opportunity: bool}>
     */
    private const array RULES = [
        'Formazione' => [
            'management_mode' => CategoryManagementMode::Single,
            'single_quote_per_opportunity' => true,
        ],
        'Consulenza' => [
            'management_mode' => CategoryManagementMode::Multiple,
            'single_quote_per_opportunity' => false,
        ],
    ];

    public function __construct(
        private readonly CategoryManagementModeInheritance $managementMode,
        private readonly SingleQuotePerOpportunityInheritance $singleQuote,
    ) {}

    public function apply(): void
    {
        foreach (self::RULES as $rootName => $rules) {
            /** @var ProductCategory $root */
            $root = ProductCategory::query()->where('name', $rootName)->whereNull('parent_id')->firstOrFail();

            // A clean save runs no UPDATE, so a re-run on an already-aligned
            // root costs nothing and logs no activity.
            $root->fill($rules)->save();

            $this->managementMode->syncSubtree($root);
            $this->singleQuote->syncSubtree($root);
        }
    }
}
