import { describe, expect, it } from 'vitest'
import { generateOrdersBlocker, groupBySupplier } from '@/features/purchase-requests/generate-orders'
import { toLineTarget, type LineTarget } from '@/features/purchase-requests/line-status-transitions'

function target(overrides: Partial<LineTarget> & Pick<LineTarget, 'id'>): LineTarget {
  return {
    status: 'approved',
    transitions: [],
    capabilities: ['fulfill'],
    description: `Line ${overrides.id}`,
    quantity: 1,
    unitSymbol: 'pc',
    totalAmount: 10,
    supplier: { id: 12, name: 'Rossi Srl' },
    canUpdateSupplier: true,
    purchaseOrder: null,
    purchaseRequestId: 5,
    purchaseRequestSubject: 'Laptops',
    ...overrides,
  }
}

describe('generateOrdersBlocker (spec 0210 AC-022)', () => {
  it('allows approved lines with a supplier and no ODA when the actor can create orders', () => {
    expect(generateOrdersBlocker([target({ id: 1 }), target({ id: 2 })], true)).toBeNull()
  })

  it.each([
    ['permission', [target({ id: 1 })], false],
    ['status', [target({ id: 1 }), target({ id: 2, status: 'ordered' })], true],
    ['supplier', [target({ id: 1, supplier: null })], true],
    ['linked', [target({ id: 1, purchaseOrder: { id: 9, code: 'ODA-9' } })], true],
  ] as const)('reports %s as the reason', (reason, targets, canCreate) => {
    expect(generateOrdersBlocker(targets, canCreate)).toBe(reason)
  })
})

describe('groupBySupplier', () => {
  it('groups lines by supplier in first-seen order', () => {
    const groups = groupBySupplier([
      target({ id: 1 }),
      target({ id: 2, supplier: { id: 13, name: 'Bianchi' } }),
      target({ id: 3 }),
    ])
    expect(groups.map((group) => [group.supplier.id, group.lines.map((line) => line.id)])).toEqual([
      [12, [1, 3]],
      [13, [2]],
    ])
  })
})

describe('toLineTarget purchase_order', () => {
  it('reads the linked ODA of a grid row', () => {
    expect(toLineTarget({ id: 1, actions: [], purchase_order: { id: 9, code: 'ODA-9' } }).purchaseOrder).toEqual({ id: 9, code: 'ODA-9' })
    expect(toLineTarget({ id: 1, actions: [], purchase_order: null }).purchaseOrder).toBeNull()
  })
})
