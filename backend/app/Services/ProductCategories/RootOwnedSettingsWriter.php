<?php

namespace App\Services\ProductCategories;

use App\DataObjects\ProductCategories\CreateProductCategoryData;
use App\DataObjects\ProductCategories\UpdateProductCategoryData;
use App\Enums\CategoryManagementMode;
use App\Models\ProductCategory;

/**
 * The write-side orchestration of the five ROOT-OWNED product-category
 * settings — `requires_quote`, `management_mode`,
 * `single_quote_per_opportunity` (user directive 2026-08-07),
 * `generates_contract` (spec 0091) and `simplified_offer_line` (spec 0114,
 * made overridable per node by spec 0188). Each has its own inheritance class
 * holding the MECHANICS — a RootOwnedCategorySetting subclass for the first
 * four, the nearest-ancestor SimplifiedOfferLineInheritance for the last;
 * this class holds the three things ProductCategoryService does with all five
 * at once, and which grew that file past its size limit (engineering.md §6):
 *
 *  - the no-override guard (only a root authors a value, a child may submit
 *    at most the value it already inherits — 422 otherwise; for
 *    `simplified_offer_line` a child declares its override instead),
 *  - the create-time resolution (inherited value, else submitted, else the
 *    setting's own fresh-root default),
 *  - the subtree resync after an update, triggered by a reparent (the branch
 *    root changed) or by an edit of the setting itself.
 *
 * The five defaults are deliberately NOT uniform: `generates_contract`
 * defaults to true and the other four to their permissive/legacy value,
 * because in each case that is what leaves an existing catalogue behaving
 * exactly as it did before the setting existed.
 */
final class RootOwnedSettingsWriter
{
    public function __construct(
        private readonly RequiresQuoteInheritance $requiresQuote,
        private readonly CategoryManagementModeInheritance $managementMode,
        private readonly SingleQuotePerOpportunityInheritance $singleQuote,
        private readonly ContractGenerationInheritance $contractGeneration,
        private readonly SimplifiedOfferLineInheritance $simplifiedOfferLine,
    ) {}

    /**
     * Rejects any of the five settings submitted on a CHILD with a value
     * diverging from the one its branch root already imposes.
     */
    public function assertCreateNotOverridden(CreateProductCategoryData $data): void
    {
        $this->assertNotOverridden($data->parentId, $data->requiresQuote, $data->managementMode, $data->singleQuotePerOpportunity, $data->generatesContract);
        $this->assertSimplifiedOfferLine($data->parentId, $data->simplifiedOfferLine, $data->simplifiedOfferLineOverride, $data->simplifiedOfferLineOverride);
    }

    /**
     * Same guard on update, against the PROSPECTIVE parent: a category being
     * reparented in this very request is judged by its new root, not its old.
     */
    public function assertUpdateNotOverridden(ProductCategory $category, UpdateProductCategoryData $data): void
    {
        $parentId = $data->hasParentId() ? $data->parentId : $category->parent_id;

        $this->assertNotOverridden(
            $parentId,
            $data->requiresQuoteSubmitted ? $data->requiresQuote : null,
            $data->managementModeSubmitted ? $data->managementMode : null,
            $data->singleQuotePerOpportunitySubmitted ? $data->singleQuotePerOpportunity : null,
            $data->generatesContractSubmitted ? $data->generatesContract : null,
        );

        // A child that is only reparented keeps its own override (spec 0188 D-5).
        $this->assertSimplifiedOfferLine(
            $parentId,
            $data->simplifiedOfferLineSubmitted ? $data->simplifiedOfferLine : null,
            $data->simplifiedOfferLineOverrideSubmitted ? $data->simplifiedOfferLineOverride : null,
            $data->simplifiedOfferLineOverrideSubmitted ? $data->simplifiedOfferLineOverride : $category->simplified_offer_line_override,
        );
    }

    /**
     * The five columns (plus the simplified-offer-line override) as they must
     * be written at CREATE time. A child never authors the four root-owned
     * ones: it takes its root's value, whatever was (or was not) submitted.
     * The simplified offer-line rule is the exception (spec 0188): a child
     * may declare its own override, and its effective value is that override
     * or else the parent's.
     *
     * @return array<string, mixed>
     */
    public function resolvedColumnsFor(CreateProductCategoryData $data): array
    {
        return [
            'requires_quote' => $this->requiresQuote->inheritedValueFor($data->parentId) ?? ($data->requiresQuote ?? false),
            // Spec 0077 D-8: a fresh root with no submitted value is "multiple".
            'management_mode' => $this->managementMode->inheritedValueFor($data->parentId) ?? ($data->managementMode ?? CategoryManagementMode::Multiple),
            'single_quote_per_opportunity' => $this->singleQuote->inheritedValueFor($data->parentId) ?? ($data->singleQuotePerOpportunity ?? false),
            // Spec 0091: a fresh root with no submitted value is TRUE — every
            // branch is sold under a contract until told otherwise.
            'generates_contract' => $this->contractGeneration->inheritedValueFor($data->parentId) ?? ($data->generatesContract ?? true),
            // Spec 0114: a fresh root with no submitted value is FALSE — the
            // pre-existing fully-manual offer line, until told otherwise.
            'simplified_offer_line' => $this->simplifiedOfferLineAtCreate($data),
            'simplified_offer_line_override' => $data->parentId === null ? null : $data->simplifiedOfferLineOverride,
        ];
    }

    /**
     * Re-aligns $category's subtree on each setting whose invariant this
     * update could have disturbed. Only a reparent (the branch root changed)
     * or an edit of the setting itself can break "the whole subtree mirrors
     * its root", so an unrelated edit (name, description, attributes) syncs
     * nothing.
     */
    public function syncSubtrees(ProductCategory $category, UpdateProductCategoryData $data): void
    {
        $reparented = $data->hasParentId();

        if ($reparented || $data->requiresQuoteSubmitted) {
            $this->requiresQuote->syncSubtree($category);
        }

        if ($reparented || $data->managementModeSubmitted) {
            $this->managementMode->syncSubtree($category);
        }

        if ($reparented || $data->singleQuotePerOpportunitySubmitted) {
            $this->singleQuote->syncSubtree($category);
        }

        if ($reparented || $data->generatesContractSubmitted) {
            $this->contractGeneration->syncSubtree($category);
        }

        if ($reparented || $data->simplifiedOfferLineSubmitted || $data->simplifiedOfferLineOverrideSubmitted) {
            $this->simplifiedOfferLine->syncSubtree($category);
        }
    }

    /**
     * The category each setting of $category is inherited FROM — the root, or
     * for the simplified offer-line rule the nearest declaring ancestor.
     * Null where $category declares the value itself. The values themselves are real columns on
     * $category, already carried by the Resource; these feed the show
     * endpoint's read-only "inherited from X" hints.
     *
     * @return array<string, array{id: int, name: string}|null>
     */
    public function sourceCategories(ProductCategory $category): array
    {
        return [
            'requires_quote_source_category' => $this->requiresQuote->sourceCategoryFor($category),
            'management_mode_source_category' => $this->managementMode->sourceCategoryFor($category),
            'single_quote_per_opportunity_source_category' => $this->singleQuote->sourceCategoryFor($category),
            'generates_contract_source_category' => $this->contractGeneration->sourceCategoryFor($category),
            'simplified_offer_line_source_category' => $this->simplifiedOfferLine->sourceCategoryFor($category),
        ];
    }

    /**
     * Shared guard body for the four root-owned settings: a value submitted
     * under a parent that already imposes a different one is a 422. A null
     * argument means "not submitted", and is never checked.
     */
    private function assertNotOverridden(
        ?int $parentId,
        ?bool $requiresQuote,
        ?CategoryManagementMode $managementMode,
        ?bool $singleQuote,
        ?bool $generatesContract,
    ): void {
        $this->assertMatchesInherited($requiresQuote, $this->requiresQuote->inheritedValueFor($parentId), 'This category inherits the quote flag from its root category and cannot define its own.');
        $this->assertMatchesInherited($managementMode, $this->managementMode->inheritedValueFor($parentId), 'This category inherits the management mode from its root category and cannot define its own.');
        $this->assertMatchesInherited($singleQuote, $this->singleQuote->inheritedValueFor($parentId), 'This category inherits the single-quote rule from its root category and cannot define its own.');
        $this->assertMatchesInherited($generatesContract, $this->contractGeneration->inheritedValueFor($parentId), 'This category inherits the contract rule from its root category and cannot define its own.');
    }

    /**
     * Spec 0188 guards. A root sets the rule directly, so an override
     * submitted on it is a 422; a child may submit `simplified_offer_line`
     * only as the effective value it already has (override, else the
     * parent's). $effectiveOverride is the override that will be in force
     * (submitted, or the one the node keeps through a reparent).
     */
    private function assertSimplifiedOfferLine(?int $parentId, ?bool $submittedValue, ?bool $submittedOverride, ?bool $effectiveOverride): void
    {
        if ($parentId === null) {
            if ($submittedOverride !== null) {
                abort(422, 'A root category sets the simplified offer-line rule directly.');
            }

            return;
        }

        $effective = $effectiveOverride ?? $this->simplifiedOfferLine->inheritedValueFor($parentId);

        $this->assertMatchesInherited($submittedValue, $effective, 'This category inherits the simplified offer-line rule; set its override instead.');
    }

    private function simplifiedOfferLineAtCreate(CreateProductCategoryData $data): bool
    {
        if ($data->parentId === null) {
            return $data->simplifiedOfferLine ?? false;
        }

        return $data->simplifiedOfferLineOverride ?? $this->simplifiedOfferLine->inheritedValueFor($data->parentId) ?? ($data->simplifiedOfferLine ?? false);
    }

    private function assertMatchesInherited(mixed $submitted, mixed $inherited, string $message): void
    {
        if ($submitted !== null && $inherited !== null && $inherited !== $submitted) {
            abort(422, $message);
        }
    }
}
