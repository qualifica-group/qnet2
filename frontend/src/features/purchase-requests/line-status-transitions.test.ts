import { describe, expect, it } from 'vitest'
import {
  commonCapabilities,
  commonTransitions,
  lineToTarget,
  sharedRequest,
  toLineTarget,
  type LineTarget,
} from '@/features/purchase-requests/line-status-transitions'
import { makeLine } from '@/features/purchase-requests/purchase-request-fixtures'
import type { TableRow } from '@/features/table/types'

function target(overrides: Partial<LineTarget> & Pick<LineTarget, 'id'>): LineTarget {
  return {
    status: null,
    transitions: [],
    capabilities: [],
    description: '',
    quantity: 0,
    unitSymbol: null,
    totalAmount: 0,
    purchaseRequestId: 5,
    purchaseRequestSubject: 'Laptops',
    ...overrides,
  }
}

describe('commonTransitions (AC-018)', () => {
  it('returns the intersection of the advertised transitions, in canonical order', () => {
    expect(
      commonTransitions([
        target({ id: 1, transitions: ['rejected', 'approved'] }),
        target({ id: 2, transitions: ['on_hold', 'approved', 'rejected'] }),
      ]),
    ).toEqual(['approved', 'rejected'])
  })

  it('is empty when the lines share no transition or nothing is selected', () => {
    expect(
      commonTransitions([target({ id: 1, transitions: ['approved'] }), target({ id: 2, transitions: ['received'] })]),
    ).toEqual([])
    expect(commonTransitions([])).toEqual([])
  })
})

describe('commonCapabilities and sharedRequest (D-17)', () => {
  it('keeps only the capabilities held on every line, in canonical order', () => {
    expect(
      commonCapabilities([
        target({ id: 1, capabilities: ['manage', 'approve'] }),
        target({ id: 2, capabilities: ['manage'] }),
      ]),
    ).toEqual(['manage'])
    expect(commonCapabilities([])).toEqual([])
  })

  it('returns the RDA only when every line belongs to the same one', () => {
    expect(sharedRequest([target({ id: 1 }), target({ id: 2 })])).toEqual({ id: 5, subject: 'Laptops' })
    expect(sharedRequest([target({ id: 1 }), target({ id: 2, purchaseRequestId: 6 })])).toBeNull()
    expect(sharedRequest([target({ id: 1, purchaseRequestId: null })])).toBeNull()
  })
})

describe('toLineTarget', () => {
  it('reads the summary, abilities.transitions and capabilities off the grid row and drops unknown values', () => {
    const row = {
      id: 7,
      purchase_request_id: 5,
      purchase_request_subject: 'Laptops',
      description: 'Laptop 14 inch',
      quantity: '3.00',
      unit_of_measure: { symbol: 'pc', name: 'Piece' },
      total_amount: '120.50',
      status: 'approved',
      actions: [],
      abilities: { transitions: ['ordered', 'bogus', 5], capabilities: ['fulfill', 'root'] },
    } as unknown as TableRow
    expect(toLineTarget(row)).toEqual({
      id: 7,
      status: 'approved',
      transitions: ['ordered'],
      capabilities: ['fulfill'],
      description: 'Laptop 14 inch',
      quantity: 3,
      unitSymbol: 'pc',
      totalAmount: 120.5,
      purchaseRequestId: 5,
      purchaseRequestSubject: 'Laptops',
    })
  })

  it('treats a row without abilities as having no transition or capability', () => {
    expect(toLineTarget({ id: '8', actions: [] } as TableRow)).toMatchObject({
      id: 8,
      status: null,
      transitions: [],
      capabilities: [],
      purchaseRequestId: null,
    })
  })
})

describe('lineToTarget', () => {
  it('maps a saved line, letting the caller override the transitions', () => {
    const line = makeLine({ id: 9, status: 'approved' })
    expect(lineToTarget(line, { id: 5, subject: 'Laptops' }, [])).toMatchObject({
      id: 9,
      status: 'approved',
      transitions: [],
      capabilities: ['approve'],
      unitSymbol: 'pc',
      purchaseRequestId: 5,
    })
  })
})
