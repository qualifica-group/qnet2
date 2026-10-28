import { describe, expect, it } from 'vitest'
import {
  defaultProductSupplierOf,
  hasProductSuppliers,
  isProductSupplier,
  productCostFor,
  productPriceFor,
  type ProductSupplierSource,
} from '@/features/products/product-suppliers'

const MULTI: ProductSupplierSource = {
  price: '99.00',
  cost: '5.00',
  suppliers: [
    { supplier_id: 2, name: 'Beta', price: '112.50', cost: '12.50', is_default: true },
    { supplier_id: 1, name: 'Alfa', price: '110.00', cost: '10.00', is_default: false },
  ],
}

const NONE: ProductSupplierSource = { price: '70.00', cost: '7.00', suppliers: [] }

describe('product suppliers helpers (spec 0320)', () => {
  it('resolves the default supplier, or null without suppliers', () => {
    expect(defaultProductSupplierOf(MULTI)?.supplier_id).toBe(2)
    expect(defaultProductSupplierOf(NONE)).toBeNull()
    expect(hasProductSuppliers(NONE)).toBe(false)
  })

  it('uses the chosen supplier cost when the product has suppliers', () => {
    expect(productCostFor(MULTI, 1)).toBe(10)
    expect(productCostFor(MULTI, 2)).toBe(12.5)
  })

  it('returns null for a supplier that is not one of the product', () => {
    expect(productCostFor(MULTI, 99)).toBeNull()
    expect(productCostFor(MULTI, null)).toBeNull()
    expect(isProductSupplier(MULTI, 99)).toBe(false)
  })

  it('falls back to the standard cost without suppliers', () => {
    expect(productCostFor(NONE, null)).toBe(7)
    expect(productCostFor({ cost: null, suppliers: [] }, null)).toBeNull()
  })

  it('uses the chosen supplier price when the product has suppliers (rev.2 D-16)', () => {
    expect(productPriceFor(MULTI, 1)).toBe(110)
    expect(productPriceFor(MULTI, 2)).toBe(112.5)
    expect(productPriceFor(MULTI, 99)).toBeNull()
    expect(productPriceFor(MULTI, null)).toBeNull()
  })

  it('falls back to the standard price without suppliers', () => {
    expect(productPriceFor(NONE, null)).toBe(70)
    expect(productPriceFor({ price: null, suppliers: [] }, null)).toBeNull()
  })
})
