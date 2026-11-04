import { describe, expect, it } from 'vitest'
import { lineFromAvailable, newLine } from '@/features/invoices/invoice-editor-lines'
import type { AvailableInvoiceLine } from '@/features/invoices/types'

function available(overrides: Partial<AvailableInvoiceLine> = {}): AvailableInvoiceLine {
  return {
    quote_line_id: 9,
    product: { id: 4, name: 'Prod' },
    description: 'Line',
    quantity: '2',
    unit_price: '10.00',
    vat_rate: { id: 1, name: '22%', rate: '22.00' },
    net_amount: '20.00',
    vat_amount: '4.40',
    total_amount: '24.40',
    commission: null,
    ...overrides,
  }
}

describe('revenue sub-account on editor lines (spec 0350 D-7)', () => {
  it('precompiles the draft proposal on a line taken from the draft', () => {
    expect(lineFromAvailable(available({ revenue_type_id: 7 }))).toMatchObject({ revenue_type_id: 7 })
  })

  it('leaves the key out when the draft has no proposal, so the server proposes again', () => {
    expect(lineFromAvailable(available({ revenue_type_id: null }))).not.toHaveProperty('revenue_type_id')
    expect(lineFromAvailable(available())).not.toHaveProperty('revenue_type_id')
  })

  it('does not send the key for a line added by hand', () => {
    expect(newLine({ description: 'Free' })).not.toHaveProperty('revenue_type_id')
  })
})
