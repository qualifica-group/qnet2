<?php

namespace App\Services\ProductCategories;

use App\DataObjects\ProductCategories\CreateProductCategoryData;
use App\DataObjects\ProductCategories\UpdateProductCategoryData;
use App\Models\ProductCategory;
use Illuminate\Validation\ValidationException;

/**
 * The one cross-setting rule among the root-owned product-category settings
 * (user decision 2026-10-15, spec 0267): `multi_quote_work_order` requires
 * `generates_contract`, because the multi-offer work order is programmed only
 * from the Contratti grid. Judged on the PROSPECTIVE values of a root; a child
 * inherits both from its root, so it can never diverge. Nothing is written on
 * refusal: the check runs before the transaction.
 */
final class RootSettingsCoherence
{
    public function assertCreate(CreateProductCategoryData $data): void
    {
        if ($data->parentId !== null) {
            return;
        }

        // Same fresh-root defaults as RootOwnedSettingsWriter::resolvedColumnsFor().
        $this->assertCoherent(
            $data->multiQuoteWorkOrder ?? false,
            $data->generatesContract ?? true,
            $data->multiQuoteWorkOrder === true,
        );
    }

    public function assertUpdate(ProductCategory $category, UpdateProductCategoryData $data): void
    {
        $parentId = $data->hasParentId() ? $data->parentId : $category->parent_id;

        if ($parentId !== null) {
            return;
        }

        $this->assertCoherent(
            $data->multiQuoteWorkOrderSubmitted ? (bool) $data->multiQuoteWorkOrder : (bool) $category->multi_quote_work_order,
            $data->generatesContractSubmitted ? (bool) $data->generatesContract : (bool) $category->generates_contract,
            $data->multiQuoteWorkOrderSubmitted && $data->multiQuoteWorkOrder === true,
        );
    }

    /**
     * @param  bool  $multiSubmittedOn  the request itself turned multi on: blame that field, else the contract one
     */
    private function assertCoherent(bool $multi, bool $contract, bool $multiSubmittedOn): void
    {
        if (! $multi || $contract) {
            return;
        }

        $field = $multiSubmittedOn ? 'multi_quote_work_order' : 'generates_contract';

        throw ValidationException::withMessages([
            $field => __('product_categories.'.$field.'_incoherent'),
        ]);
    }
}
