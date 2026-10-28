import { describe, expect, it } from 'vitest'
import {
  addSupplierRow,
  effectiveAmountOfRows,
  removeSupplierRow,
  rowsFromOptions,
  sameSupplierRows,
  setDefaultSupplierRow,
  toSuppliersPayload,
  type ProductSupplierRow,
} from '@/features/products/product-supplier-rows'

const A: ProductSupplierRow = { supplier_id: 1, name: 'Alfa', price: 110, cost: 10, is_default: true }
const B: ProductSupplierRow = { supplier_id: 2, name: 'Beta', price: 112.5, cost: 12.5, is_default: false }

describe('product supplier rows (spec 0320)', () => {
  it('makes the first added row the default, later ones not', () => {
    const first = addSupplierRow([])
    expect(first[0].is_default).toBe(true)
    expect(first[0]).toMatchObject({ price: 0, cost: 0 })
    expect(addSupplierRow([A])[1].is_default).toBe(false)
  })

  it('hands the default to the first remaining row when the default is removed', () => {
    expect(removeSupplierRow([A, B], 0)).toEqual([{ ...B, is_default: true }])
    expect(removeSupplierRow([A, B], 1)).toEqual([A])
  })

  it('keeps exactly one default', () => {
    expect(setDefaultSupplierRow([A, B], 1).map((row) => row.is_default)).toEqual([false, true])
  })

  it('maps the resource options to numeric rows', () => {
    expect(rowsFromOptions([{ supplier_id: 2, name: 'Beta', price: '112.50', cost: '12.50', is_default: false }])).toEqual([B])
    expect(rowsFromOptions(undefined)).toEqual([])
  })

  it('builds the wire payload without the name and skips rows with no supplier', () => {
    expect(toSuppliersPayload([A, { ...B, supplier_id: null }])).toEqual([
      { supplier_id: 1, price: 110, cost: 10, is_default: true },
    ])
  })

  // REQUIREMENT CHANGED (rev.3 D-20): price and cost always go as typed, whatever the usages.
  it('always sends both amounts as typed', () => {
    expect(toSuppliersPayload([A])).toEqual([{ supplier_id: 1, price: 110, cost: 10, is_default: true }])
    expect(toSuppliersPayload([{ ...A, price: null, cost: null }])).toEqual([
      { supplier_id: 1, price: 0, cost: 0, is_default: true },
    ])
  })

  it('compares order-insensitively', () => {
    expect(sameSupplierRows([B, A], [A, B])).toBe(true)
    expect(sameSupplierRows([A, { ...B, cost: 13 }], [A, B])).toBe(false)
    expect(sameSupplierRows([A, { ...B, price: 120 }], [A, B])).toBe(false)
    expect(sameSupplierRows([A], [A, B])).toBe(false)
  })
})

describe('effectiveAmountOfRows (spec 0320 D-4, rev.2 D-18)', () => {
  const rows = [
    { price: '110.00', cost: '10.00', is_default: false },
    { price: '112.50', cost: '12.50', is_default: true },
  ]

  it('reads the default supplier cost, else the standard cost', () => {
    expect(effectiveAmountOfRows(rows, 'cost', '5.00')).toBe('12.50')
    expect(effectiveAmountOfRows([], 'cost', '5.00')).toBe('5.00')
  })

  it('reads the default supplier price, else the standard price', () => {
    expect(effectiveAmountOfRows(rows, 'price', '99.00')).toBe('112.50')
    expect(effectiveAmountOfRows([], 'price', '99.00')).toBe('99.00')
  })
})
