<?php

declare(strict_types=1);

namespace App\Products;

use App\DataObjects\Commissions\CommissionResolutionContext;
use App\DataObjects\Commissions\ResolvedCommissionRule;
use App\Enums\CommissionRecipientRole;
use App\Models\Product;
use App\Models\ProductTypology;
use App\Services\Commissions\CommissionRecipientResolver;
use App\Services\Commissions\CommissionRuleResolver;
use App\Services\ProductService;
use App\Services\Quotes\SupplierCommissionEnablement;
use Illuminate\Support\Carbon;

/**
 * The Supplier commission a supplier carries on a product (spec 0320, D-21):
 * the rule the Configurator would apply today to that supplier on that product
 * (CommissionRuleResolver: rules dedicated to the supplier, then the generic
 * ones of the Supplier role), and only when the product's typology calculates
 * the Supplier commission, exactly as in an offer. Used by the product form
 * (D-22), the product detail and the cost-line writers (D-23).
 */
final class SupplierCommissionLookup
{
    public function __construct(
        private readonly CommissionRuleResolver $resolver,
        private readonly CommissionRecipientResolver $recipients,
        private readonly SupplierCommissionEnablement $enablement,
    ) {}

    /**
     * @param  array<int, int>  $supplierIds
     * @param  int|null  $productId  a saved product; wins over $categoryId and $typologyId when its row exists
     * @param  int|null  $categoryId  category of a product being created
     * @param  int|null  $typologyId  typology of a product being created; absent = the default typology (same fallback ProductService::create applies)
     * @return array<int, ResolvedCommissionRule|null> keyed by supplier id; null = no commission
     */
    public function forSuppliers(array $supplierIds, ?int $productId, ?int $categoryId = null, ?int $typologyId = null): array
    {
        $supplierIds = array_values(array_unique($supplierIds));
        $none = array_fill_keys($supplierIds, null);
        $product = $productId === null ? null : Product::query()->find($productId);

        if ($supplierIds === [] || ! $this->isEnabled($product, $typologyId)) {
            return $none;
        }

        $result = [];

        foreach ($supplierIds as $supplierId) {
            $result[$supplierId] = $this->resolveFor($supplierId, $product?->id, $product?->category_id ?? $categoryId);
        }

        return $result;
    }

    /** Whether the supplier carries a commission on the saved product today. */
    public function hasCommission(int $productId, int $supplierId): bool
    {
        return $this->forSuppliers([$supplierId], $productId)[$supplierId] !== null;
    }

    private function isEnabled(?Product $product, ?int $typologyId): bool
    {
        if ($product !== null) {
            return $this->enablement->enabledForProduct($product->id);
        }

        return $this->enablement->enabledForTypology($typologyId ?? $this->defaultTypologyId());
    }

    private function defaultTypologyId(): ?int
    {
        $id = ProductTypology::query()->where('code', ProductService::DEFAULT_PRODUCT_TYPOLOGY_CODE)->value('id');

        return $id === null ? null : (int) $id;
    }

    private function resolveFor(int $supplierId, ?int $productId, ?int $categoryId): ?ResolvedCommissionRule
    {
        $rules = $this->resolver->resolve(new CommissionResolutionContext(
            productId: $productId,
            productCategoryId: $categoryId,
            roles: [CommissionRecipientRole::Supplier],
            referenceDate: Carbon::today()->toDateTimeImmutable(),
            recipients: $this->recipients->resolve($supplierId, null, null, null),
        ));

        return $rules[0] ?? null;
    }
}
