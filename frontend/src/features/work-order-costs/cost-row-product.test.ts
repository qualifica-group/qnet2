import { describe, expect, it } from 'vitest'
import { costRowPatchFromProduct } from '@/features/work-order-costs/cost-row-product'
import { createEmptyCostRow } from '@/features/work-order-costs/work-order-costs-schema'
import type { QuoteProductForSelectItem } from '@/features/quotes/quote-product-select'

function productItem(meta: Partial<QuoteProductForSelectItem['meta']> = {}): QuoteProductForSelectItem {
  return {
    id: 9,
    label: 'Noleggio auto',
    subtitle: 'Spese',
    meta: {
      code: 'CST-0009',
      price: '99.00',
      cost: '12.50',
      vat_rate_id: 3,
      vat_rate_name: 'IVA 22%',
      vat_rate: '22.00',
      unit_of_measure: { id: 1, name: 'Giorno', symbol: 'gg' },
      product_typology: null,
      ...meta,
    },
  }
}

/** The for-select item as the backend sends it once spec 0251 adds `meta.cost_type`. */
function withCostType(costType: { id: number; name: string; color: string }): QuoteProductForSelectItem {
  const base = productItem()
  const meta = { ...base.meta, cost_type: costType }
  return { ...base, meta }
}

describe('costRowPatchFromProduct (D-8)', () => {
  it('prefills unit price from products.cost (never price), VAT rate and unit of measure', () => {
    const patch = costRowPatchFromProduct(productItem(), createEmptyCostRow())

    expect(patch).toMatchObject({ product_id: 9, unit_price: 12.5, vat_rate_id: 3 })
    expect(patch.display).toMatchObject({
      product_code: 'CST-0009',
      product_name: 'Noleggio auto',
      product_category: 'Spese',
      unit_symbol: 'gg',
      vat_name: 'IVA 22%',
      vat_percent: 22,
    })
  })

  it('defaults an empty quantity to 1 but keeps one already typed', () => {
    expect(costRowPatchFromProduct(productItem(), createEmptyCostRow()).quantity).toBe(1)
    expect(costRowPatchFromProduct(productItem(), { ...createEmptyCostRow(), quantity: 4 }).quantity).toBe(4)
  })

  it('leaves the supplier untouched', () => {
    const row = { ...createEmptyCostRow(), supplier_id: 40, display: { ...createEmptyCostRow().display, supplier_name: 'Fornitore Uno' } }
    const patch = costRowPatchFromProduct(productItem(), row)

    expect(patch).not.toHaveProperty('supplier_id')
    expect(patch.display?.supplier_name).toBe('Fornitore Uno')
  })

  it('spec 0251 AC-008: takes the cost type from the product default, keeps the row one when the product has none', () => {
    const costType = { id: 6, name: 'Travel', color: 'blue' }
    const patch = costRowPatchFromProduct(withCostType(costType), createEmptyCostRow())

    expect(patch.cost_type_id).toBe(6)
    expect(patch.display).toMatchObject({ cost_type_name: 'Travel', cost_type_color: 'blue' })

    const kept = costRowPatchFromProduct(productItem(), { ...createEmptyCostRow(), cost_type_id: 9 })
    expect(kept).not.toHaveProperty('cost_type_id')
  })

  it('handles a product with no cost, VAT rate or unit', () => {
    const patch = costRowPatchFromProduct(
      productItem({ cost: null, vat_rate_id: null, vat_rate_name: null, vat_rate: null, unit_of_measure: null }),
      createEmptyCostRow(),
    )

    expect(patch).toMatchObject({ unit_price: null, vat_rate_id: null })
    expect(patch.display).toMatchObject({ unit_symbol: null, vat_name: null, vat_percent: null })
  })

  describe('with product suppliers (spec 0320 D-9, amends 0190 D-8)', () => {
    const suppliers = [
      { supplier_id: 40, name: 'Alfa Srl', price: '108.00', cost: '8.00', is_default: false },
      { supplier_id: 41, name: 'Beta Srl', price: '109.50', cost: '9.50', is_default: true },
    ]

    it('proposes the default supplier and its cost, and keeps the options on the row display', () => {
      const patch = costRowPatchFromProduct(productItem({ suppliers }), createEmptyCostRow())

      expect(patch).toMatchObject({ supplier_id: 41, unit_price: 9.5 })
      expect(patch.display).toMatchObject({ supplier_name: 'Beta Srl', product_suppliers: suppliers })
    })

    it('without suppliers keeps products.cost and leaves the supplier alone', () => {
      const patch = costRowPatchFromProduct(productItem({ suppliers: [] }), createEmptyCostRow())

      expect(patch.unit_price).toBe(12.5)
      expect(patch).not.toHaveProperty('supplier_id')
      expect(patch.display?.product_suppliers).toEqual([])
    })
  })
})
