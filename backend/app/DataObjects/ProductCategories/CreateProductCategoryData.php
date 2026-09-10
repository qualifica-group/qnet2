<?php

namespace App\DataObjects\ProductCategories;

use App\Enums\CategoryManagementMode;

/**
 * Validated payload for creating a product category
 * (POST /api/product-categories, spec 0017). Declared DTO (no "magic flying
 * array") so the StoreProductCategoryRequest → ProductCategoryService
 * contract is explicit — see standards/architecture.md → Data Transfer
 * Objects.
 */
final readonly class CreateProductCategoryData
{
    /**
     * @param  array<int, array{attribute_id: int, context: string, is_required?: bool, sort_order?: int}>|null  $attributes
     */
    public function __construct(
        public string $name,
        public ?int $parentId = null,
        public bool $inheritsProductAttributes = true,
        /** Spec 0084: the Offerta context's own inheritance barrier (App\Enums\AttributeContext::Quote), same default-true convention as inheritsProductAttributes. */
        public bool $inheritsQuoteAttributes = true,
        /** Spec 0098: the Commessa context's own inheritance barrier (App\Enums\AttributeContext::WorkOrder), same default-true convention. */
        public bool $inheritsWorkOrderAttributes = true,
        public ?string $description = null,
        public ?array $attributes = null,
        public ?int $businessFunctionId = null,
        /** Null = not submitted: the flag is then resolved server-side (root's value, or false at root). */
        public ?bool $requiresQuote = null,
        /** Spec 0074: a plain per-node flag, defaulting to selectable when omitted. */
        public bool $isSelectable = true,
        /** Spec 0077: null = not submitted, resolved server-side (root's value, or "multiple" at a fresh root — D-8). */
        public ?CategoryManagementMode $managementMode = null,
        /** User directive 2026-08-07: same root-only semantics, null = not submitted (root's value, or false at a fresh root). */
        public ?bool $singleQuotePerOpportunity = null,
        /** Spec 0091: same root-only semantics, null = not submitted (root's value, or TRUE at a fresh root — the pre-existing behaviour). */
        public ?bool $generatesContract = null,
        /** Spec 0114: same root-only semantics, null = not submitted (root's value, or FALSE at a fresh root — the pre-existing fully-manual offer line). */
        public ?bool $simplifiedOfferLine = null,
        /** Spec 0080: raw sparse position->label map — normalized (trim, empty removed) by ProductCategoryService, never here. */
        public ?array $managerLabels = null,
        /** Spec 0080: whether this category inherits its ancestors' manager labels. Defaults to true, same as the attribute barriers. */
        public bool $inheritsManagerLabels = true,
    ) {}

    /**
     * Build from the validated StoreProductCategoryRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            parentId: array_key_exists('parent_id', $data) && $data['parent_id'] !== null ? (int) $data['parent_id'] : null,
            inheritsProductAttributes: array_key_exists('inherits_product_attributes', $data) ? (bool) $data['inherits_product_attributes'] : true,
            inheritsQuoteAttributes: array_key_exists('inherits_quote_attributes', $data) ? (bool) $data['inherits_quote_attributes'] : true,
            inheritsWorkOrderAttributes: array_key_exists('inherits_work_order_attributes', $data) ? (bool) $data['inherits_work_order_attributes'] : true,
            description: array_key_exists('description', $data) ? $data['description'] : null,
            attributes: array_key_exists('attributes', $data) ? (array) $data['attributes'] : null,
            businessFunctionId: array_key_exists('business_function_id', $data) && $data['business_function_id'] !== null ? (int) $data['business_function_id'] : null,
            requiresQuote: array_key_exists('requires_quote', $data) ? (bool) $data['requires_quote'] : null,
            isSelectable: array_key_exists('is_selectable', $data) ? (bool) $data['is_selectable'] : true,
            managementMode: array_key_exists('management_mode', $data) ? CategoryManagementMode::from((string) $data['management_mode']) : null,
            singleQuotePerOpportunity: array_key_exists('single_quote_per_opportunity', $data) ? (bool) $data['single_quote_per_opportunity'] : null,
            generatesContract: array_key_exists('generates_contract', $data) ? (bool) $data['generates_contract'] : null,
            simplifiedOfferLine: array_key_exists('simplified_offer_line', $data) ? (bool) $data['simplified_offer_line'] : null,
            managerLabels: array_key_exists('manager_labels', $data) ? (array) $data['manager_labels'] : null,
            inheritsManagerLabels: array_key_exists('inherits_manager_labels', $data) ? (bool) $data['inherits_manager_labels'] : true,
        );
    }

    public function hasAttributes(): bool
    {
        return $this->attributes !== null;
    }
}
