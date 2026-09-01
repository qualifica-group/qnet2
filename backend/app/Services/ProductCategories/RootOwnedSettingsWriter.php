<?php

namespace App\Services\ProductCategories;

use App\DataObjects\ProductCategories\CreateProductCategoryData;
use App\DataObjects\ProductCategories\UpdateProductCategoryData;
use App\Enums\CategoryManagementMode;
use App\Models\ProductCategory;

/**
 * The write-side orchestration of the four ROOT-OWNED product-category
 * settings — `requires_quote`, `management_mode`,
 * `single_quote_per_opportunity` (user directive 2026-08-07) and
 * `generates_contract` (spec 0091). Each has its own
 * RootOwnedCategorySetting subclass holding the inheritance MECHANICS; this
 * class holds the three things ProductCategoryService does with all four at
 * once, and which grew that file past its size limit (engineering.md §6):
 *
 *  - the no-override guard (only a root authors a value, a child may submit
 *    at most the value it already inherits — 422 otherwise),
 *  - the create-time resolution (inherited value, else submitted, else the
 *    setting's own fresh-root default),
 *  - the subtree resync after an update, triggered by a reparent (the branch
 *    root changed) or by an edit of the setting itself.
 *
 * The four defaults are deliberately NOT uniform: `generates_contract`
 * defaults to true and the other three to their permissive/legacy value,
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
    ) {}

    /**
     * Rejects any of the four settings submitted on a CHILD with a value
     * diverging from the one its branch root already imposes.
     */
    public function assertCreateNotOverridden(CreateProductCategoryData $data): void
    {
        $this->assertNotOverridden($data->parentId, $data->requiresQuote, $data->managementMode, $data->singleQuotePerOpportunity, $data->generatesContract);
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
    }

    /**
     * The four columns as they must be written at CREATE time. A child never
     * authors any of them: it takes its root's value, whatever was (or was
     * not) submitted.
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
    }

    /**
     * The ROOT each setting of $category is inherited FROM — null on a root,
     * which owns its own values. The values themselves are real columns on
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
        ];
    }

    /**
     * Shared guard body: for each setting, a value submitted under a parent
     * that already imposes a different one is a 422. A null argument means
     * "not submitted", and is never checked.
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

    private function assertMatchesInherited(mixed $submitted, mixed $inherited, string $message): void
    {
        if ($submitted !== null && $inherited !== null && $inherited !== $submitted) {
            abort(422, $message);
        }
    }
}
