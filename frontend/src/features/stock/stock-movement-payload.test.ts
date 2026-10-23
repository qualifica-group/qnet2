import { describe, expect, it } from 'vitest'
import { stockMovementFormValues, toStockMovementPayload } from '@/features/stock/stock-movement-payload'

const base = { product_id: 3, operational_site_id: 7 }

describe('toStockMovementPayload (spec 0307 D-11)', () => {
  it('never sends a user for a LOAD', () => {
    const values = { ...stockMovementFormValues('LOAD'), ...base, user_id: 9, quantity: '2' }

    expect(toStockMovementPayload(values)).toEqual({ type: 'LOAD', ...base, quantity: '2' })
  })

  it('sends the destination site only for a TRANSFER, keeping the free row (null user)', () => {
    const values = { ...stockMovementFormValues('TRANSFER'), ...base, to_operational_site_id: 8, quantity: '1' }

    expect(toStockMovementPayload(values)).toEqual({
      type: 'TRANSFER',
      ...base,
      user_id: null,
      to_operational_site_id: 8,
      quantity: '1',
    })
  })

  it('sends the counted quantity instead of the quantity for an ADJUSTMENT', () => {
    const values = { ...stockMovementFormValues('ADJUSTMENT'), ...base, counted_quantity: '0', quantity: '4', note: ' Conta ' }

    expect(toStockMovementPayload(values)).toEqual({
      type: 'ADJUSTMENT',
      ...base,
      user_id: null,
      counted_quantity: '0',
      note: 'Conta',
    })
  })

  it('returns null when the schema was bypassed', () => {
    expect(toStockMovementPayload(stockMovementFormValues('LOAD'))).toBeNull()
  })

  it('prefills an ASSIGN from a free row without a recipient, a RETURN from an assigned row with it', () => {
    const row = { product_id: 3, operational_site_id: 7, user_id: 9 }

    expect(stockMovementFormValues('ASSIGN', row).user_id).toBeNull()
    expect(stockMovementFormValues('RETURN', row).user_id).toBe(9)
  })
})
