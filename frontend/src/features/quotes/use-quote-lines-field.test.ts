import { describe, expect, it } from 'vitest'
import { lineValuesFromProduct } from '@/features/quotes/use-quote-lines-field'
import type { QuoteProductForSelectItem } from '@/features/quotes/quote-product-select'

/**
 * Spec 0088: the line's own `unit_of_measure_id` is congelated server-side on
 * save (D-5), so the row can only show a unit during create/edit if the pick
 * itself carries the product's one — otherwise the column stays "—" for the
 * whole form.
 */

const PRODUCT: QuoteProductForSelectItem = {
  id: 42,
  label: 'Widget Pro',
  meta: {
    code: 'PRD-0042',
    price: '120.00',
    cost: '80.00',
    vat_rate_id: 7,
    vat_rate_name: 'IVA 22%',
    vat_rate: '22.00',
    unit_of_measure: { id: 1, name: 'Kilogram', symbol: 'kg' },
    product_typology: { id: 3, name: 'Consulenza' },
  },
}

describe('lineValuesFromProduct', () => {
  it('carries the product unit onto the row, next to the variant price (AC-074)', () => {
    expect(lineValuesFromProduct(PRODUCT, 'revenue')).toEqual({
      product_id: 42,
      unit_of_measure: { id: 1, name: 'Kilogram', symbol: 'kg' },
      unit_price: 120,
      vat_rate_id: 7,
    })
  })

  it('carries the same unit on a Cost row, priced from meta.cost (D-6)', () => {
    expect(lineValuesFromProduct(PRODUCT, 'cost')).toMatchObject({
      unit_of_measure: { id: 1, name: 'Kilogram', symbol: 'kg' },
      unit_price: 80,
    })
  })

  it('leaves the unit null for a product without one', () => {
    const product = { ...PRODUCT, meta: { ...PRODUCT.meta, unit_of_measure: null } }

    expect(lineValuesFromProduct(product, 'revenue').unit_of_measure).toBeNull()
  })
})
