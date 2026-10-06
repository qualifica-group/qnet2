import type { ProductUsage } from '@/features/products/types'

/** Which money fields (and the margin they feed) apply to a product with the given usages. */
export interface ProductPricingVisibility {
  price: boolean
  cost: boolean
  margin: boolean
}

/**
 * Single source of the rule (spec 0191, D-9): the price matters only to a
 * Sellable product, the cost only to a Usable-as-cost one, and the margin only
 * when both exist. Schema, form, summary and detail all read it from here.
 */
export function productPricingVisibility(usages: readonly ProductUsage[] | null | undefined): ProductPricingVisibility {
  const price = usages?.includes('SALE') ?? false
  const cost = usages?.includes('COST') ?? false

  return { price, cost, margin: price && cost }
}
