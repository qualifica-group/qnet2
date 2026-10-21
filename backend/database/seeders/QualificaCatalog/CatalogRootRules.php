<?php

namespace Database\Seeders\QualificaCatalog;

use App\Enums\CategoryManagementMode;
use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryManagementModeInheritance;
use App\Services\ProductCategories\ContractGenerationInheritance;
use App\Services\ProductCategories\MultiQuoteWorkOrderInheritance;
use App\Services\ProductCategories\SimplifiedOfferLineInheritance;
use App\Services\ProductCategories\SingleQuotePerOpportunityInheritance;

/**
 * The ROOT-OWNED rules the Qualifica catalogue declares for its roots.
 * Split out of QualificaCatalogSeeder (which stayed at its size limit,
 * engineering.md §6) — it holds the map AND the write, since the two are
 * meaningless apart.
 *
 * All four rules are REALIGNED on every run, in both directions, and the
 * subtree is re-synced after each: an installation seeded before a rule
 * existed must actually see its branch adopt it, which a create-only write
 * would skip.
 *
 * "Formazione" is worked one product line and ONE offer at a time (user
 * directive 2026-08-03 for the line, 2026-08-07 for the offer): a training
 * opportunity is a single course sold once, never a basket of alternatives.
 * It IS sold under a contract since user decision 2026-10-15, so it can be
 * grouped into a multi-offer work order (this supersedes the spec 0091 /
 * 2026-09-01 directive for Formazione only; the multi-offer rule requires the
 * contract). It also seeds its offer lines from the product alone
 * (spec 0114): an operator picks the course, the server freezes quantity,
 * price and VAT. "Consulenza" stays unconstrained on all four — listed
 * explicitly rather than left to the column defaults so a re-run realigns it
 * too.
 *
 * "APL" is worked exactly like "Formazione" (user directive 2026-10-05, "stessi
 * setting di formazione"): the same four rules and the same G.A. labels, except that APL
 * stays without a contract, so an APL deal closing positively does not surface
 * in the Contratti module. It
 * kept the column defaults (Consulenza's values) from 2026-09-07 until then.
 *
 * "SOA" and "Sicurezza" are worked like "Consulenza" (user decisions 2026-10-15),
 * and so are "Gare", "GDPR", "Finanza Agevolata", "Istruzione" and
 * "Accreditamenti" (user directives 2026-10-20).
 *
 * "Formazione" alone also allows the multi-offer work order (spec 0267, user
 * decision 2026-10-15). The key is declared on that root only, so the other
 * roots keep whatever the UI set instead of being realigned to false.
 *
 * `manager_labels` (spec 0080) rides along on the same root write but is NOT
 * mirrored on the subtree: descendants resolve it by climbing the tree
 * (CategoryManagerLabelResolver), so authoring it on the root alone is what
 * gives the whole branch its G.A. names. "Formazione" declares them (user
 * directive 2026-08-31), and "APL" with it; "Consulenza" omits the key entirely rather
 * than realigning to null, which would wipe labels configured from the UI.
 *
 * NODE_OVERRIDES then lets one node of a branch declare its own
 * `simplified_offer_line` (spec 0188), handed down to its subtree: applied
 * after the root re-sync, which leaves an override and its subtree alone.
 */
final class CatalogRootRules
{
    /**
     * The rules of a training deal, shared by "Formazione" and "APL".
     *
     * @var array{management_mode: CategoryManagementMode, single_quote_per_opportunity: bool, generates_contract: bool, simplified_offer_line: bool, manager_labels: array<string, string>}
     */
    private const array TRAINING_RULES = [
        'management_mode' => CategoryManagementMode::Single,
        'single_quote_per_opportunity' => true,
        'generates_contract' => false,
        'simplified_offer_line' => true,
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
    ];

    /**
     * The rules of a consultancy deal, shared by "Consulenza" and "SOA" (user
     * decision 2026-10-15): several lines and offers, a contract on a positive
     * close, the full offer line.
     *
     * @var array{management_mode: CategoryManagementMode, single_quote_per_opportunity: bool, generates_contract: bool, simplified_offer_line: bool}
     */
    private const array CONSULTING_RULES = [
        'management_mode' => CategoryManagementMode::Multiple,
        'single_quote_per_opportunity' => false,
        'generates_contract' => true,
        'simplified_offer_line' => false,
    ];

    /**
     * Root name => the rules it owns, as `product_categories` columns.
     *
     * @var array<string, array{management_mode: CategoryManagementMode, single_quote_per_opportunity: bool, generates_contract: bool, simplified_offer_line?: bool, multi_quote_work_order?: bool, manager_labels?: array<string, string>}>
     */
    private const array RULES = [
        'Formazione' => [...self::TRAINING_RULES, 'generates_contract' => true, 'multi_quote_work_order' => true],
        'APL' => self::TRAINING_RULES,
        'Consulenza' => self::CONSULTING_RULES,
        SoaCatalogue::ROOT => self::CONSULTING_RULES,
        SafetyCatalogue::ROOT => self::CONSULTING_RULES,
        TenderCatalogue::ROOT => self::CONSULTING_RULES,
        GdprCatalogue::ROOT => self::CONSULTING_RULES,
        SubsidisedFinanceCatalogue::ROOT => self::CONSULTING_RULES,
        EducationCatalogue::ROOT => self::CONSULTING_RULES,
        AccreditationCatalogue::ROOT => self::CONSULTING_RULES,
        IsoCatalogue::ROOT => self::CONSULTING_RULES,
    ];

    /**
     * Category name => the override it declares (spec 0188). "Corsi E-Campus"
     * keeps the full offer line under the simplified "Formazione", so the
     * operator can change the line's taxable amount (user directive
     * 2026-10-02).
     *
     * @var array<string, array{simplified_offer_line_override: bool}>
     */
    private const array NODE_OVERRIDES = [
        ECampusCourseCatalogue::CATEGORY => ['simplified_offer_line_override' => false],
    ];

    public function __construct(
        private readonly CategoryManagementModeInheritance $managementMode,
        private readonly SingleQuotePerOpportunityInheritance $singleQuote,
        private readonly ContractGenerationInheritance $contractGeneration,
        private readonly SimplifiedOfferLineInheritance $simplifiedOfferLine,
        private readonly MultiQuoteWorkOrderInheritance $multiQuoteWorkOrder,
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
            $this->simplifiedOfferLine->syncSubtree($root);
            $this->multiQuoteWorkOrder->syncSubtree($root);
        }

        foreach (self::NODE_OVERRIDES as $categoryName => $override) {
            /** @var ProductCategory $category */
            $category = ProductCategory::query()->where('name', $categoryName)->firstOrFail();

            $category->fill($override)->save();
            $this->simplifiedOfferLine->syncSubtree($category);
        }
    }
}
