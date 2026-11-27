import { act, type ReactNode } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, screen, within } from '@testing-library/react'
import i18n from '@/i18n'
import {
  PAID_LINE,
  CONTRACT_DATA,
  RECEIVED_LINE,
} from '@/features/work-order-contract-data/contract-data-fixtures'
import {
  STATUS_ITEMS,
  forceFocusVisible,
  lineFields,
  lineGroup,
  renderSection,
} from '@/features/work-order-contract-data/contract-data-test-helpers'
import type { WorkOrderContractData } from '@/features/work-order-contract-data/types'

vi.mock('@/components/detail/record-link', () => ({
  RecordLink: ({ domain, id, children }: { domain: string; id: number; children: ReactNode }) => (
    <a href={`/${domain}/${id}`}>{children}</a>
  ),
}))

vi.mock('@/features/invoices/invoice-detail-sheet', () => ({
  InvoiceDetailSheet: ({ invoiceId }: { invoiceId: number | null }) =>
    invoiceId === null ? null : <div role="dialog" aria-label={`invoice-${invoiceId}`} />,
}))

const fetchMock = vi.fn<(id: number) => Promise<WorkOrderContractData>>()
vi.mock('@/features/work-order-contract-data/api', () => ({
  fetchWorkOrderContractData: (id: number) => fetchMock(id),
  updateContractDataLine: vi.fn(),
}))

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>('@/features/for-select/api')
  return { ...actual, fetchForSelect: (resource: string, params: unknown) => fetchForSelectMock(resource, params) }
})

const hover = (element: HTMLElement) => fireEvent.pointerEnter(element, { pointerType: 'mouse' })

/** The calculation hint of the line "Amount". */
const amountHint = (code: string) => within(lineGroup(code)).getByRole('button', { name: /1,000\.00|2,000\.00/ })

/** Waits for the lines (spec 0403: one group of fields per product). */
const findLines = () => screen.findAllByRole('group')

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchMock.mockReset()
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockResolvedValue({
    items: STATUS_ITEMS,
    pagination: { offset: 0, limit: 25, total: null, has_more: false },
    export_link: null,
  })
  fetchMock.mockResolvedValue(CONTRACT_DATA)
})

// REQUIREMENT CHANGED (spec 0403, user directive 2026-11-16): each product is a block of fields
// (Title, Amount, Payment status, Document, Payment agreement, Unpaid), no more the wide table.
describe('WorkOrderContractDataSection - states', () => {
  it('shows a loading status, then the lines', async () => {
    renderSection(false)

    expect(screen.getByRole('status', { name: 'Loading contract data' })).toBeInTheDocument()
    expect(await findLines()).toHaveLength(2)
  })

  it('shows an error with retry', async () => {
    fetchMock.mockRejectedValueOnce(new Error('boom'))
    renderSection(false)

    expect(await screen.findByRole('alert')).toHaveTextContent('Unable to load the work order contract data.')
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }))

    expect(await findLines()).toHaveLength(2)
  })

  it('shows the empty state without lines', async () => {
    fetchMock.mockResolvedValue({ ...CONTRACT_DATA, lines: [] })
    renderSection(false)

    expect(await screen.findByText('The work order has no product lines.')).toBeInTheDocument()
    expect(screen.queryByRole('group')).not.toBeInTheDocument()
  })
})

describe('WorkOrderContractDataSection - line fields (spec 0403 AC-004)', () => {
  // REQUIREMENT CHANGED (user directive 2026-11-17): the typology badge left the title for the payment status label.
  it('shows the legacy fields of each product, in order', async () => {
    renderSection(false)
    await findLines()

    expect(lineFields(lineGroup('ENT-001'))).toEqual({
      Title: 'ENT-001Certificazione ente',
      Amount: '2,000.00',
      'Payment statusEnte': 'Pagato',
      Document: 'Proforma12/2026Seriously overdue',
      'Payment agreement': 'Saldo a 30 giorni',
      Unpaid: 'Yes',
    })
    expect(lineFields(lineGroup('CON-001'))).toMatchObject({
      'Payment statusConsulenza': 'No status',
      Document: 'No proforma issued',
      Unpaid: 'No',
    })
  })

  it('links the document number to the proforma with invoices.view (user request 2026-11-16)', async () => {
    renderSection(false, false, true)
    await findLines()

    fireEvent.click(within(lineGroup('ENT-001')).getByRole('button', { name: '12/2026' }))

    expect(screen.getByRole('dialog', { name: /^invoice-\d+$/ })).toBeInTheDocument()
  })

  it('keeps the document number as plain text without invoices.view', async () => {
    renderSection(false)
    await findLines()

    expect(within(lineGroup('ENT-001')).queryByRole('button', { name: '12/2026' })).not.toBeInTheDocument()
    expect(within(lineGroup('ENT-001')).getByText('12/2026')).toBeInTheDocument()
  })

  it('puts each product in its own tinted box', async () => {
    renderSection(false)
    await findLines()

    expect(lineGroup('CON-001')).toHaveClass('rounded-lg', 'bg-muted/40')
    expect(lineGroup('ENT-001')).toHaveClass('rounded-lg', 'bg-muted/40')
  })

  it('keeps code and name in the title', async () => {
    renderSection(false)
    await findLines()

    const line = lineGroup('CON-001')
    expect(within(line).getByText('Consulenza qualita')).toHaveAttribute('title', 'Consulenza qualita')
    expect(within(line).getByRole('link', { name: 'Consulenza qualita' })).toHaveAttribute('href', '/products/1')
  })

  // REQUIREMENT CHANGED (user directive 2026-11-17): the typology badge moved from the title to the
  // "Stato pagamento" label, so each payment status reads as Consulenza or Ente.
  it('badges the typology on the payment status label', async () => {
    renderSection(false)
    await findLines()

    const consultancyTerm = within(lineGroup('CON-001')).getByText('Payment status')
    expect(within(consultancyTerm).getByText('Consulenza')).toHaveClass('bg-blue-100')
    const institutionTerm = within(lineGroup('ENT-001')).getByText('Payment status')
    expect(within(institutionTerm).getByText('Ente')).toHaveClass('bg-violet-100')
  })

  it('names the client of each line on a multi-offer work order (spec 0267)', async () => {
    renderSection(false, true)
    await findLines()

    expect(within(lineGroup('CON-001')).getByRole('link', { name: 'Rossi S.r.l.' })).toHaveAttribute('href', '/registries/21')
    expect(within(lineGroup('ENT-001')).getByRole('link', { name: 'Bianchi S.p.A.' })).toHaveAttribute('href', '/registries/22')
  })

  it('omits the line client on a single-offer work order', async () => {
    renderSection(false)
    await findLines()

    expect(screen.queryByText('Rossi S.r.l.')).not.toBeInTheDocument()
  })

  it('colours the payment status and offers no pencil without manage_payments', async () => {
    renderSection(false)
    await findLines()

    expect(screen.getByText('Pagato').closest('[data-slot="badge"]')).toHaveClass('bg-green-100', 'text-green-700')
    expect(screen.getByText('No status')).toHaveClass('bg-muted')
    expect(screen.queryByRole('button', { name: /Edit the payment/ })).not.toBeInTheDocument()
  })
})

describe('WorkOrderContractDataSection - amount hint', () => {
  it('explains the amount on hover', async () => {
    renderSection(false)
    await findLines()

    hover(amountHint('CON-001'))

    expect(await screen.findByText('Quantity × unit price.')).toBeInTheDocument()
    expect(screen.getByText('2 × 500.00 = 1,000.00')).toBeInTheDocument()
  })

  it('opens on keyboard focus', async () => {
    renderSection(false)
    await findLines()

    forceFocusVisible()
    act(() => amountHint('CON-001').focus())

    expect(await screen.findByText('Quantity × unit price.')).toBeInTheDocument()
  })

  it('opens on a touch tap and closes on the next tap', async () => {
    renderSection(false)
    await findLines()
    const trigger = amountHint('CON-001')

    fireEvent.pointerDown(trigger, { pointerType: 'touch' })
    fireEvent.click(trigger)
    expect(await screen.findByText('Quantity × unit price.')).toBeInTheDocument()

    fireEvent.pointerDown(trigger, { pointerType: 'touch' })
    fireEvent.click(trigger)
    expect(screen.queryByText('Quantity × unit price.')).not.toBeInTheDocument()
  })

  it('does not close a hover-opened hint on the click that follows', async () => {
    renderSection(false)
    await findLines()
    const trigger = amountHint('CON-001')

    hover(trigger)
    fireEvent.pointerDown(trigger, { pointerType: 'mouse' })
    fireEvent.click(trigger)

    expect(await screen.findByText('Quantity × unit price.')).toBeInTheDocument()
  })
})

describe('WorkOrderContractDataSection - totals', () => {
  // REQUIREMENT CHANGED (user directive 2026-11-16): the totals follow the lines as legacy-like rows.
  // REQUIREMENT CHANGED (user directive 2026-11-27): no Commissioni / Netto commissioni rows, even when visible.
  it('lists the totals after the lines: one per typology, the total revenue last, no commission totals', async () => {
    renderSection(false)
    const lines = await findLines()

    const totals = screen.getAllByText(/^Total /, { selector: 'dt' })
    expect(totals.map((term) => term.textContent)).toEqual([
      'Total Consulenza',
      'Total Ente',
      'Total Formazione',
      'Total revenue',
    ])
    expect(lines[1].compareDocumentPosition(totals[0]) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy()
    expect(totals.map((term) => term.nextElementSibling?.textContent)).toEqual([
      '1,000.00',
      '2,000.00revenue 200.00',
      '0.00',
      '1,200.00',
    ])
    expect(screen.getByText('0.00', { selector: 'dd span' })).toHaveClass('text-muted-foreground')
    expect(screen.queryByText(/^(Commissions|Net of commissions)$/, { selector: 'dt' })).not.toBeInTheDocument()

    hover(within(screen.getByText('Total revenue', { selector: 'dt' }).parentElement as HTMLElement).getByRole('button'))
    expect(await screen.findByText("Sum of the lines' effective revenue: the received commission, otherwise the net amount.")).toBeInTheDocument()
  })
})

describe('WorkOrderContractDataSection - warnings (AC-018)', () => {
  it('shows each warning as an icon with accessible text, and the text in its hint', async () => {
    fetchMock.mockResolvedValue({
      ...CONTRACT_DATA,
      lines: [
        { ...RECEIVED_LINE, supplier_commission: null, effective_revenue: '0.00', warnings: ['missing_supplier_commission'] },
        { ...PAID_LINE, warnings: ['stale_commission_base'] },
      ],
    })
    renderSection(false)
    await findLines()

    const missing = screen.getByText('Supplier commission received missing: the revenue is 0.00.')
    expect(missing).toHaveClass('sr-only')
    expect(screen.getByText('Amount calculated on a previous base: save the quote again to update it.')).toHaveClass('sr-only')

    hover(missing.closest('button') as HTMLElement)
    expect(await screen.findByText('Warning')).toBeInTheDocument()
  })
})
