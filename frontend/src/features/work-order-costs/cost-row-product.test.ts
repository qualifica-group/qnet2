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

  it('handles a product with no cost, VAT rate or unit', () => {
    const patch = costRowPatchFromProduct(
      productItem({ cost: null, vat_rate_id: null, vat_rate_name: null, vat_rate: null, unit_of_measure: null }),
      createEmptyCostRow(),
    )

    expect(patch).toMatchObject({ unit_price: null, vat_rate_id: null })
    expect(patch.display).toMatchObject({ unit_symbol: null, vat_name: null, vat_percent: null })
  })
})
