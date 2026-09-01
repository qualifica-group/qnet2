<?php

namespace Database\Seeders\QualificaCatalog;

use App\Enums\CategoryManagementMode;
use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryManagementModeInheritance;
use App\Services\ProductCategories\ContractGenerationInheritance;
use App\Services\ProductCategories\SingleQuotePerOpportunityInheritance;

/**
 * The ROOT-OWNED rules the Qualifica catalogue declares for its two roots.
 * Split out of QualificaCatalogSeeder (which stayed at its size limit,
 * engineering.md §6) — it holds the map AND the write, since the two are
 * meaningless apart.
 *
 * All three rules are REALIGNED on every run, in both directions, and the
 * subtree is re-synced after each: an installation seeded before a rule
 * existed must actually see its branch adopt it, which a create-only write
 * would skip.
 *
 * "Formazione" is worked one product line and ONE offer at a time (user
 * directive 2026-08-03 for the line, 2026-08-07 for the offer): a training
 * opportunity is a single course sold once, never a basket of alternatives.
 * It is also NOT sold under a contract (spec 0091, user directive
 * 2026-09-01): a training deal closing positively must not surface in the
 * Contratti module. "Consulenza" stays unconstrained on all three — listed
 * explicitly rather than left to the column defaults so a re-run realigns it
 * too.
 *
 * `manager_labels` (spec 0080) rides along on the same root write but is NOT
 * mirrored on the subtree: descendants resolve it by climbing the tree
 * (CategoryManagerLabelResolver), so authoring it on the root alone is what
 * gives the whole branch its G.A. names. Only "Formazione" declares them
 * (user directive 2026-08-31); "Consulenza" omits the key entirely rather
 * than realigning to null, which would wipe labels configured from the UI.
 */
final class CatalogRootRules
{
    /**
     * Root name => the rules it owns, as `product_categories` columns.
     *
     * @var array<string, array{management_mode: CategoryManagementMode, single_quote_per_opportunity: bool, generates_contract: bool, manager_labels?: array<string, string>}>
     */
    private const array RULES = [
        'Formazione' => [
            'management_mode' => CategoryManagementMode::Single,
            'single_quote_per_opportunity' => true,
            'generates_contract' => false,
            // The four G.A. levels of a training deal (user directive
            // 2026-08-31). Position 2 stays "Operatore": it is the level
            // Gestione Richieste has hard-coded semantics for
            // (Opportunity::OPERATOR_MANAGER_POSITION).
            'manager_labels' => [
                '1' => 'Tutor',
                '2' => 'Operatore',
                '3' => 'Partner commerciale',
                '4' => 'Segnalatore',
            ],
        ],
        'Consulenza' => [
            'management_mode' => CategoryManagementMode::Multiple,
            'single_quote_per_opportunity' => false,
            'generates_contract' => true,
        ],
    ];

    public function __construct(
        private readonly CategoryManagementModeInheritance $managementMode,
        private readonly SingleQuotePerOpportunityInheritance $singleQuote,
        private readonly ContractGenerationInheritance $contractGeneration,
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
            $this->contractGeneration->syncSubtree($root);
        }
    }
}
