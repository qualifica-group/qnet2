import { describe, expect, it } from 'vitest'
import type { TFunction } from 'i18next'
import { stockMovementFormValues } from '@/features/stock/stock-movement-payload'
import { buildStockMovementSchema } from '@/features/stock/stock-movement-schema'
import type { StockMovementType } from '@/features/stock/types'

const t = ((key: string) => key) as unknown as TFunction
const schema = buildStockMovementSchema(t)

function issuesOf(type: StockMovementType, overrides: Record<string, unknown> = {}): Record<string, string> {
  const result = schema.safeParse({
    ...stockMovementFormValues(type),
    product_id: 3,
    operational_site_id: 7,
    quantity: '2',
    counted_quantity: '2',
    note: 'x',
    user_id: 9,
    to_operational_site_id: 8,
    ...overrides,
  })
  return result.success ? {} : Object.fromEntries(result.error.issues.map((issue) => [String(issue.path[0]), issue.message]))
}

describe('stock movement schema (spec 0307 D-11/D-14)', () => {
  it.each(['LOAD', 'UNLOAD', 'TRANSFER', 'ASSIGN', 'RETURN', 'ADJUSTMENT'] as const)('accepts a complete %s', (type) => {
    expect(issuesOf(type)).toEqual({})
  })

  it('rejects a TRANSFER to the same site', () => {
    expect(issuesOf('TRANSFER', { to_operational_site_id: 7 })).toEqual({ to_operational_site_id: 'stock.form.toSiteSame' })
  })

  it.each(['ASSIGN', 'RETURN'] as const)('requires the user for %s', (type) => {
    expect(issuesOf(type, { user_id: null })).toEqual({ user_id: 'stock.form.userRequired' })
  })

  it.each(['UNLOAD', 'ADJUSTMENT'] as const)('requires the note for %s', (type) => {
    expect(issuesOf(type, { note: '  ' })).toEqual({ note: 'stock.form.noteRequired' })
  })

  it('refuses a zero or four-decimal quantity, but accepts a zero counted quantity', () => {
    expect(issuesOf('LOAD', { quantity: '0' })).toEqual({ quantity: 'stock.form.quantityInvalid' })
    expect(issuesOf('LOAD', { quantity: '1.2345' })).toEqual({ quantity: 'stock.form.quantityInvalid' })
    expect(issuesOf('ADJUSTMENT', { counted_quantity: '0' })).toEqual({})
  })

  it('refuses a future date', () => {
    expect(issuesOf('LOAD', { moved_on: '2999-01-01' })).toEqual({ moved_on: 'stock.form.dateInFuture' })
  })
})
