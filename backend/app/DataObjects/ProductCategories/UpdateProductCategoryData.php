<?php

namespace App\DataObjects\ProductCategories;

use App\Enums\CategoryManagementMode;

/**
 * Validated payload for a partial (PATCH) product category update
 * (PUT/PATCH /api/product-categories/{productCategory}, spec 0017).
 *
 * Declared DTO (no "magic flying array") so the UpdateProductCategoryRequest
 * → ProductCategoryService contract is explicit. `parent_id` and
 * `description` are both legitimately nullable VALUES (moving to root,
 * clearing the description), so a plain null property cannot distinguish
 * "not submitted" from "submitted as null" — the `*Submitted` flags carry
 * that distinction explicitly, mirroring UpdateBusinessFunctionData.
 * `attributes`, when submitted, is a full-replace sync of the category's own
 * assignments. `businessFunctionId`/`businessFunctionIdSubmitted` follow the
 * same submitted-flag pattern (spec 0023): a category's own function is
 * legitimately nullable (explicit clear), so presence must be distinguished
 * from a null value.
 */
final readonly class UpdateProductCategoryData
{
    /**
     * @param  array<int, array{attribute_id: int, context: string, is_required?: bool, sort_order?: int}>|null  $attributes
     */
    public function __construct(
        public ?string $name = null,
        public ?int $parentId = null,
        public bool $parentIdSubmitted = false,
        public ?bool $inheritsProductAttributes = null,
        public bool $inheritsProductAttributesSubmitted = false,
        /** Spec 0084: the Offerta context's own inheritance barrier (App\Enums\AttributeContext::Quote), same submitted-flag convention as inheritsProductAttributes above. */
        public ?bool $inheritsQuoteAttributes = null,
        public bool $inheritsQuoteAttributesSubmitted = false,
        public ?string $description = null,
        public bool $descriptionSubmitted = false,
        public ?array $attributes = null,
        public ?int $businessFunctionId = null,
        public bool $businessFunctionIdSubmitted = false,
        public ?bool $requiresQuote = null,
        public bool $requiresQuoteSubmitted = false,
        public ?bool $isSelectable = null,
        public bool $isSelectableSubmitted = false,
        public ?CategoryManagementMode $managementMode = null,
        public bool $managementModeSubmitted = false,
        public ?bool $singleQuotePerOpportunity = null,
        public bool $singleQuotePerOpportunitySubmitted = false,
        /** Spec 0080: raw sparse position->label map — normalized (trim, empty removed) by ProductCategoryService, never here. */
        public ?array $managerLabels = null,
        public bool $managerLabelsSubmitted = false,
        public ?bool $inheritsManagerLabels = null,
        public bool $inheritsManagerLabelsSubmitted = false,
    ) {}

    /**
     * Build from the validated UpdateProductCategoryRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: array_key_exists('name', $data) ? (string) $data['name'] : null,
            parentId: array_key_exists('parent_id', $data) && $data['parent_id'] !== null ? (int) $data['parent_id'] : null,
            parentIdSubmitted: array_key_exists('parent_id', $data),
            inheritsProductAttributes: array_key_exists('inherits_product_attributes', $data) ? (bool) $data['inherits_product_attributes'] : null,
            inheritsProductAttributesSubmitted: array_key_exists('inherits_product_attributes', $data),
            inheritsQuoteAttributes: array_key_exists('inherits_quote_attributes', $data) ? (bool) $data['inherits_quote_attributes'] : null,
            inheritsQuoteAttributesSubmitted: array_key_exists('inherits_quote_attributes', $data),
            description: array_key_exists('description', $data) ? $data['description'] : null,
            descriptionSubmitted: array_key_exists('description', $data),
            attributes: array_key_exists('attributes', $data) ? (array) $data['attributes'] : null,
            businessFunctionId: array_key_exists('business_function_id', $data) && $data['business_function_id'] !== null ? (int) $data['business_function_id'] : null,
            businessFunctionIdSubmitted: array_key_exists('business_function_id', $data),
            requiresQuote: array_key_exists('requires_quote', $data) ? (bool) $data['requires_quote'] : null,
            requiresQuoteSubmitted: array_key_exists('requires_quote', $data),
            isSelectable: array_key_exists('is_selectable', $data) ? (bool) $data['is_selectable'] : null,
            isSelectableSubmitted: array_key_exists('is_selectable', $data),
            managementMode: array_key_exists('management_mode', $data) ? CategoryManagementMode::from((string) $data['management_mode']) : null,
            managementModeSubmitted: array_key_exists('management_mode', $data),
            singleQuotePerOpportunity: array_key_exists('single_quote_per_opportunity', $data) ? (bool) $data['single_quote_per_opportunity'] : null,
            singleQuotePerOpportunitySubmitted: array_key_exists('single_quote_per_opportunity', $data),
            managerLabels: array_key_exists('manager_labels', $data) ? (array) $data['manager_labels'] : null,
            managerLabelsSubmitted: array_key_exists('manager_labels', $data),
            inheritsManagerLabels: array_key_exists('inherits_manager_labels', $data) ? (bool) $data['inherits_manager_labels'] : null,
            inheritsManagerLabelsSubmitted: array_key_exists('inherits_manager_labels', $data),
        );
    }

    public function hasParentId(): bool
    {
        return $this->parentIdSubmitted;
    }

    public function hasDescription(): bool
    {
        return $this->descriptionSubmitted;
    }

    public function hasAttributes(): bool
    {
        return $this->attributes !== null;
    }

    /**
     * Only the attributes the client actually submitted, ready for a partial
     * mass-assignment update. `attributes` (the pivot) is synced separately.
     *
     * @return array<string, mixed>
     */
    public function submittedAttributes(): array
    {
        $attributes = [];

        if ($this->name !== null) {
            $attributes['name'] = $this->name;
        }

        if ($this->parentIdSubmitted) {
            $attributes['parent_id'] = $this->parentId;
        }

        if ($this->inheritsProductAttributesSubmitted) {
            $attributes['inherits_product_attributes'] = $this->inheritsProductAttributes;
        }

        if ($this->inheritsQuoteAttributesSubmitted) {
            $attributes['inherits_quote_attributes'] = $this->inheritsQuoteAttributes;
        }

        if ($this->descriptionSubmitted) {
            $attributes['description'] = $this->description;
        }

        if ($this->businessFunctionIdSubmitted) {
            $attributes['business_function_id'] = $this->businessFunctionId;
        }

        // Only a ROOT category authors this flag; on a child the value written
        // here is immediately re-aligned on the root's by
        // RequiresQuoteInheritance::syncSubtree (ProductCategoryService).
        if ($this->requiresQuoteSubmitted) {
            $attributes['requires_quote'] = $this->requiresQuote;
        }

        // Spec 0074: per-node flag, written verbatim — nothing to reconcile
        // across the subtree, since it is never inherited.
        if ($this->isSelectableSubmitted) {
            $attributes['is_selectable'] = $this->isSelectable;
        }

        // Spec 0077: only a ROOT category authors this mode; on a child the
        // value written here is immediately re-aligned on the root's by
        // CategoryManagementModeInheritance::syncSubtree (ProductCategoryService).
        if ($this->managementModeSubmitted) {
            $attributes['management_mode'] = $this->managementMode;
        }

        // User directive 2026-08-07: identical root-only handling — on a child
        // the value written here is immediately re-aligned on the root's by
        // SingleQuotePerOpportunityInheritance::syncSubtree.
        if ($this->singleQuotePerOpportunitySubmitted) {
            $attributes['single_quote_per_opportunity'] = $this->singleQuotePerOpportunity;
        }

        // Spec 0080: mirrors inherits_product_attributes/
        // inherits_quote_attributes — a plain boolean, written verbatim.
        // `manager_labels` itself is deliberately NOT added here: its values
        // need normalizing (trim, empty removed), which ProductCategoryService
        // applies on top of this array — this DTO stays a dumb data carrier.
        if ($this->inheritsManagerLabelsSubmitted) {
            $attributes['inherits_manager_labels'] = $this->inheritsManagerLabels;
        }

        return $attributes;
    }
}
