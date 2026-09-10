import { act, renderHook } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import {
  DEFAULT_LINE_QUANTITY,
  lineValuesFromProduct,
  useQuoteLinesField,
} from '@/features/quotes/use-quote-lines-field'
import type { QuoteProductForSelectItem } from '@/features/quotes/quote-product-select'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'

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

describe('useQuoteLinesField.setProduct', () => {
  const EMPTY_ROW: QuoteLineFormValues = {
    product_id: null,
    quantity: null,
    unit_of_measure: null,
    unit_price: null,
    vat_rate_id: null,
    commissions: [],
  }

  function pickProductOn(
    row: QuoteLineFormValues,
    product: QuoteProductForSelectItem = PRODUCT,
    simplified = false,
  ): QuoteLineFormValues[] {
    const onChange = vi.fn()
    const { result } = renderHook(() =>
      useQuoteLinesField({
        value: [row],
        onChange,
        variant: 'revenue',
        rememberVatRatePercent: vi.fn(),
      }),
    )

    act(() => result.current.setProduct(0, product.id, product, undefined, simplified))

    return onChange.mock.calls[0][0] as QuoteLineFormValues[]
  }

  it('opens an empty quantity on 1, so the picked row is savable as it stands', () => {
    expect(pickProductOn(EMPTY_ROW)[0]).toMatchObject({ product_id: 42, quantity: DEFAULT_LINE_QUANTITY })
  })

  it('never overwrites a quantity the operator already typed', () => {
    expect(pickProductOn({ ...EMPTY_ROW, quantity: 5 })[0]).toMatchObject({ quantity: 5 })
  })

  // Spec 0114 AC-019: a simplified row has no quantity/unit price input, so
  // the pick must already carry the values the server will congeal.
  describe('simplified (spec 0114)', () => {
    it('forces quantity to 1 even over a quantity already on the row', () => {
      expect(pickProductOn({ ...EMPTY_ROW, quantity: 5 }, PRODUCT, true)[0]).toMatchObject({
        quantity: DEFAULT_LINE_QUANTITY,
      })
    })

    it('carries the product price as unit_price', () => {
      expect(pickProductOn(EMPTY_ROW, PRODUCT, true)[0]).toMatchObject({ unit_price: 120 })
    })

    it('congeals unit_price to 0 when the product has no price, instead of leaving it null', () => {
      const priceless = { ...PRODUCT, meta: { ...PRODUCT.meta, price: null } }

      expect(pickProductOn(EMPTY_ROW, priceless, true)[0]).toMatchObject({
        quantity: DEFAULT_LINE_QUANTITY,
        unit_price: 0,
      })
    })
  })
})
