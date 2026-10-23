import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/react'
import i18n from '@/i18n'
import { QuoteCommissionDetails } from '@/features/quotes/quote-commission-details'
import { quoteLineFixture } from '@/features/quotes/quote-fixtures'
import type { QuoteLineCommission } from '@/features/quotes/types'

/** No record is viewable here: recipients render as plain names (their links are covered by `quote-commission-recipient.test.tsx`). */
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false }),
}))


/** User directive 2026-10-23: the legacy "Dettaglio Provvigioni per Servizio", in the quote summary. */

const MINUS = '\u2212'

const hover = (element: HTMLElement) => fireEvent.pointerEnter(element, { pointerType: 'mouse' })

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function commission(overrides: Partial<QuoteLineCommission>): QuoteLineCommission {
  return {
    id: 1,
    recipient_role: 'COMMERCIAL',
    recipient_type: 'referent',
    recipient_id: 1,
    recipient: { id: 1, name: 'Luigi Cirillo' },
    commission_type: 'PERCENTAGE',
    value: '40.0000',
    calculated_amount: '1960.00',
    internal_note: null,
    origin: 'MANUAL_OVERRIDE',
    commission_configuration_id: null,
    ...overrides,
  }
}

const LINES = [
  quoteLineFixture({ id: 1, product: { ...quoteLineFixture().product, name: 'Consultancy' }, commissions: [] }),
  quoteLineFixture({
    id: 2,
    product: { ...quoteLineFixture().product, name: 'Institution' },
    net_amount: '5000.00',
    supplier_commission_direction: 'RECEIVED',
    commissions: [
      commission({ id: 10 }),
      commission({
        id: 11,
        recipient_role: 'SUPPLIER',
        recipient_type: 'registry',
        recipient: { id: 2, name: 'Perry Johnson' },
        origin: 'RECIPIENT',
      }),
    ],
  }),
  quoteLineFixture({
    id: 3,
    product: { ...quoteLineFixture().product, name: 'Course' },
    commissions: [
      commission({
        id: 12,
        recipient_role: 'REPORTER',
        recipient: { id: 3, name: 'Anna Rossi' },
        commission_type: 'FIXED_AMOUNT',
        value: '150.0000',
        calculated_amount: '150.00',
      }),
    ],
  }),
]

/** 100.00 of costs imputed to the Institution row: the percentage base is 5,000.00 - 100.00 = 4,900.00. */
const COSTS = [quoteLineFixture({ id: 20, net_amount: '100.00', offer_line_id: 2 })]

describe('QuoteCommissionDetails', () => {
  it('lists every line with commissions, by role, with value, amount and the Supplier direction', () => {
    render(<QuoteCommissionDetails lines={LINES} costLines={COSTS} />)

    const table = screen.getByRole('table', { name: 'Commission details per product' })
    const rows = within(table).getAllByRole('row')
    // Header, two lines with commissions (the first one has none), totals.
    expect(rows).toHaveLength(4)

    expect(within(rows[1]).getByText('Institution (row 2)')).toBeInTheDocument()
    expect(within(rows[1]).getByText('Luigi Cirillo')).toBeInTheDocument()
    expect(within(rows[1]).getByText('Perry Johnson')).toBeInTheDocument()
    expect(within(rows[1]).getAllByText('40%')).toHaveLength(2)
    // The Commercial commission goes out, the received Supplier one comes in: the row balance is zero.
    expect(within(rows[1]).getByText(`${MINUS}1,960.00`)).toBeInTheDocument()
    expect(within(rows[1]).getByText('+1,960.00')).toBeInTheDocument()
    expect(within(rows[1]).getByText('Received')).toBeInTheDocument()
    expect(within(rows[1]).getByText('0.00')).toBeInTheDocument()

    expect(within(rows[2]).getByText('Course (row 3)')).toBeInTheDocument()
    expect(within(rows[2]).getByText('Fixed amount')).toBeInTheDocument()
    expect(within(rows[2]).getAllByText(`${MINUS}150.00`)).toHaveLength(2)

    const totals = within(rows[3]).getAllByRole('cell').map((cell) => cell.textContent)
    expect(totals).toEqual(['Total', `${MINUS}1,960.00`, `${MINUS}150.00`, '0.00', '+1,960.00', `${MINUS}150.00`])
  })

  it('explains what Received means behind the (?) of the Supplier direction', async () => {
    render(<QuoteCommissionDetails lines={LINES} costLines={COSTS} />)

    fireEvent.focus(screen.getByRole('button', { name: 'What Received means' }))

    expect(await screen.findByRole('tooltip')).toHaveTextContent('we collect the commission from the supplier')
  })

  it('explains a percentage on its base: net amount minus the imputed costs', async () => {
    render(<QuoteCommissionDetails lines={LINES} costLines={COSTS} />)
    const institutionRow = within(screen.getByRole('table', { name: 'Commission details per product' })).getAllByRole('row')[1]
    const commercialAmount = within(institutionRow).getByRole('button', { name: `${MINUS}1,960.00` })

    hover(commercialAmount)
    expect(await screen.findByText('Commercial · Luigi Cirillo')).toBeInTheDocument()
    expect(screen.getByText('5,000.00 − 100.00 = 4,900.00')).toBeInTheDocument()
    expect(screen.getByText('40% × 4,900.00 = 1,960.00')).toBeInTheDocument()
  })

  it('explains a row total as the balance of its commissions', async () => {
    render(<QuoteCommissionDetails lines={LINES} costLines={COSTS} />)
    const institutionRow = within(screen.getByRole('table', { name: 'Commission details per product' })).getAllByRole('row')[1]

    hover(within(institutionRow).getByRole('button', { name: '0.00' }))

    expect(await screen.findByText(`${MINUS}1,960.00 + 1,960.00 = 0.00`)).toBeInTheDocument()
  })

  it('says a fixed amount does not depend on the base', async () => {
    render(<QuoteCommissionDetails lines={LINES} costLines={COSTS} />)
    const courseRow = within(screen.getByRole('table', { name: 'Commission details per product' })).getAllByRole('row')[2]

    hover(within(courseRow).getAllByRole('button', { name: `${MINUS}150.00` })[0])

    expect(await screen.findByText('Fixed amount: 150.00. It does not depend on the net amount or the costs.')).toBeInTheDocument()
  })

  it('marks a paid Supplier commission as going out', () => {
    const paidLine = quoteLineFixture({
      id: 4,
      supplier_commission_direction: 'PAID',
      commissions: [commission({ id: 13, recipient_role: 'SUPPLIER', recipient_type: 'registry', calculated_amount: '90.00' })],
    })
    render(<QuoteCommissionDetails lines={[paidLine]} costLines={[]} />)

    const rows = within(screen.getByRole('table', { name: 'Commission details per product' })).getAllByRole('row')
    expect(within(rows[1]).getAllByText(`${MINUS}90.00`)).toHaveLength(2)
    expect(within(rows[1]).getByText('Paid')).toBeInTheDocument()
  })

  it('renders nothing when no line carries commissions', () => {
    const { container } = render(<QuoteCommissionDetails lines={[LINES[0]]} costLines={[]} />)

    expect(container).toBeEmptyDOMElement()
  })
})
