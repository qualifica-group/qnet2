import { describe, expect, it } from 'vitest'
import { productPricingVisibility } from '@/features/products/product-pricing-visibility'

describe('productPricingVisibility (spec 0191 D-9)', () => {
  it('shows only the price for a Sellable-only product', () => {
    expect(productPricingVisibility(['SALE'])).toEqual({ price: true, cost: false, margin: false })
  })

  it('shows only the cost for a cost-only product', () => {
    expect(productPricingVisibility(['COST'])).toEqual({ price: false, cost: true, margin: false })
  })

  it('shows both and the margin when both usages are set, whatever the order', () => {
    expect(productPricingVisibility(['COST', 'SALE'])).toEqual({ price: true, cost: true, margin: true })
  })

  it('shows nothing for an empty or missing set', () => {
    expect(productPricingVisibility([])).toEqual({ price: false, cost: false, margin: false })
    expect(productPricingVisibility(undefined)).toEqual({ price: false, cost: false, margin: false })
  })
})
